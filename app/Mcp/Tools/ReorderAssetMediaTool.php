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

#[Name('reorder-asset-media')]
#[Description('Reorder media items directly for a shared asset collection.')]
#[IsDestructive(false)]
class ReorderAssetMediaTool extends Tool
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
            'collection' => ['required', 'in:main_image,images'],
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'exists:media,id'],
        ]);

        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);
        $mediaIds = collect($validated['media_ids'])->values();
        $ownedIds = $asset->media()
            ->where('collection_name', $validated['collection'])
            ->whereIn('id', $mediaIds)
            ->pluck('id')
            ->all();

        abort_unless($mediaIds->diff($ownedIds)->isEmpty(), 404);

        $preview = new MutationPreview(
            action: 'reorder-asset-media',
            summary: 'Reorder '.$validated['collection'].' media for '.($asset->name ?? $asset->route_label).'.',
            changes: [[
                'operation' => 'reorder',
                'model' => $asset::class,
                'id' => $asset->id,
                'media_ids' => $mediaIds->all(),
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($asset, $mediaIds, $data): array {
            $mediaIds->each(function (int $mediaId, int $index) use ($asset): void {
                $asset->media()->whereKey($mediaId)->update(['order_column' => $index + 1]);
            });

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
            'collection' => $schema->string()->description('main_image or images.')->required(),
            'media_ids' => $schema->array()->description('Ordered media ids.')->required(),
            'dry_run' => $schema->boolean()->description('Optional. When true, previews the media reorder without writing.'),
        ];
    }
}
