<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerAssetResolver;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('detach-asset-from-day')]
#[Description('Detach an existing shared asset from a day after preview and confirmation.')]
#[IsDestructive(false)]
class DetachAssetFromDayTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerAssetResolver $resolver): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['required', 'string'],
            'day' => ['required'],
            'asset_type' => ['required', 'in:accommodation,transport,activity,food'],
            'asset_id' => ['required', 'integer'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $day = $variant->dayNodes()
            ->where(fn ($query) => $query->where('stable_key', $validated['day'])->orWhere('day_number', (int) $validated['day']))
            ->firstOrFail();
        $relation = $resolver->relationForDay($day, $validated['asset_type']);
        $asset = $relation->getRelated()::query()->findOrFail($validated['asset_id']);

        $preview = new MutationPreview(
            action: 'detach-asset-from-day',
            summary: 'Detach '.$validated['asset_type'].' asset from '.$day->title.'.',
            changes: [[
                'operation' => 'detach',
                'model' => $asset::class,
                'id' => $asset->id,
                'day_node_id' => $day->id,
            ]],
            risk: 'medium',
        );

        return $guard->handle($request, $preview, function () use ($relation, $asset): array {
            $relation->detach($asset->id);

            return ['detached_asset_id' => $asset->id];
        });
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trip_slug' => $schema->string()->description('Trip slug.')->required(),
            'variant_slug' => $schema->string()->description('Timeline variant slug.')->required(),
            'day' => $schema->string()->description('Day stable key or number.')->required(),
            'asset_type' => $schema->string()->description('accommodation, transport, activity, or food.')->required(),
            'asset_id' => $schema->integer()->description('Asset id to detach.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
