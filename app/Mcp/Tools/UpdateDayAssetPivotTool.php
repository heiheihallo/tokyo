<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerAssetResolver;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-day-asset-pivot')]
#[Description('Update day-to-asset pivot planning fields after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateDayAssetPivotTool extends Tool
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
            'role' => ['nullable', 'string', 'max:255'],
            'confirmation_status' => ['nullable', 'string', 'max:255'],
            'booking_status' => ['nullable', 'string', 'max:255'],
            'reservation_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'time_block' => ['nullable', 'string', 'max:255'],
            'meal_type' => ['nullable', 'string', 'max:255'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $day = $variant->dayNodes()
            ->where(fn ($query) => $query->where('stable_key', $validated['day'])->orWhere('day_number', (int) $validated['day']))
            ->firstOrFail();
        $relation = $resolver->relationForDay($day, $validated['asset_type']);
        $asset = $relation->getRelated()::query()->findOrFail($validated['asset_id']);
        $allowed = match ($validated['asset_type']) {
            'accommodation' => ['role', 'confirmation_status', 'reservation_url', 'notes'],
            'transport' => ['sequence', 'booking_status', 'reservation_url', 'notes'],
            'activity' => ['sequence', 'time_block', 'booking_status', 'reservation_url', 'notes'],
            'food' => ['sequence', 'meal_type', 'notes'],
        };
        $updates = collect(Arr::only($validated, $allowed))->filter(fn ($value): bool => $value !== null)->all();

        $preview = new MutationPreview(
            action: 'update-day-asset-pivot',
            summary: 'Update '.$validated['asset_type'].' pivot on '.$day->title.'.',
            changes: [[
                'operation' => 'update_pivot',
                'model' => $asset::class,
                'asset_id' => $asset->id,
                'day_node_id' => $day->id,
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($relation, $asset, $updates): array {
            $relation->updateExistingPivot($asset->id, $updates);

            return ['updated_asset_id' => $asset->id, 'pivot' => $updates];
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
            'variant_slug' => $schema->string()->description('Variant slug.')->required(),
            'day' => $schema->string()->description('Day stable key or number.')->required(),
            'asset_type' => $schema->string()->description('accommodation, transport, activity, or food.')->required(),
            'asset_id' => $schema->integer()->description('Asset id.')->required(),
            'role' => $schema->string()->description('Accommodation role.'),
            'confirmation_status' => $schema->string()->description('Accommodation confirmation status.'),
            'booking_status' => $schema->string()->description('Booking status.'),
            'reservation_url' => $schema->string()->description('Reservation URL.'),
            'notes' => $schema->string()->description('Pivot notes.'),
            'sequence' => $schema->integer()->description('Sequence.'),
            'time_block' => $schema->string()->description('Activity time block.'),
            'meal_type' => $schema->string()->description('Food meal type.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
