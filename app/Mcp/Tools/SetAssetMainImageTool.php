<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerAssetResolver;
use App\Mcp\Support\TripPlannerData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Name('set-asset-main-image')]
#[Description('Promote one existing asset media item to the single main_image collection after preview and confirmation.')]
#[IsDestructive(false)]
class SetAssetMainImageTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerAssetResolver $resolver, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:accommodation,accommodations,activity,activities,food,food_spot,food_spots,transport,transport_leg,transport_legs'],
            'asset_id' => ['nullable', 'integer'],
            'stable_key' => ['nullable', 'string', 'max:255'],
            'media_id' => ['required', 'integer', 'exists:media,id'],
        ]);

        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);
        $media = $asset->media()->whereKey($validated['media_id'])->firstOrFail();

        $preview = new MutationPreview(
            action: 'set-asset-main-image',
            summary: 'Set media '.$media->id.' as main image for '.($asset->name ?? $asset->route_label).'.',
            changes: [[
                'operation' => 'set_main_image',
                'model' => Media::class,
                'id' => $media->id,
                'asset_id' => $asset->id,
            ]],
            risk: 'low',
            requiresConfirmation: true,
        );

        return $guard->handle($request, $preview, function () use ($asset, $media, $data): array {
            $asset->media()
                ->where('collection_name', 'main_image')
                ->whereKeyNot($media->id)
                ->get()
                ->each
                ->delete();

            $media->update(['collection_name' => 'main_image']);

            return ['media' => $data->media($asset->refresh())];
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
            'type' => $schema->string()->description('Asset type.')->required(),
            'asset_id' => $schema->integer()->description('Optional asset id.'),
            'stable_key' => $schema->string()->description('Optional asset stable key.'),
            'media_id' => $schema->integer()->description('Media id to promote.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
