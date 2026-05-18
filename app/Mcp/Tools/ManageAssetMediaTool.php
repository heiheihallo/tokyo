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

#[Name('manage-asset-media')]
#[Description('Manage shared asset media by adding URLs, promoting main images, removing items, or reordering collections.')]
#[IsDestructive(false)]
class ManageAssetMediaTool extends Tool
{
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerAssetResolver $resolver, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'operation' => ['required', 'in:add_from_url,set_main_image,remove,reorder'],
            'type' => ['required', 'string', 'in:accommodation,accommodations,activity,activities,food,food_spot,food_spots,transport,transport_leg,transport_legs'],
            'asset_id' => ['nullable', 'integer'],
            'stable_key' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'in:main_image,images'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'media_ids' => ['nullable', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'exists:media,id'],
            'image_url' => ['nullable', 'url', 'starts_with:https://,http://', 'max:2000'],
            'name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'attribution' => ['nullable', 'string', 'max:1000'],
        ]);

        return match ($validated['operation']) {
            'add_from_url' => $this->addFromUrl($request, $validated, $guard, $resolver, $data),
            'set_main_image' => $this->setMainImage($request, $validated, $guard, $resolver, $data),
            'remove' => $this->remove($request, $validated, $guard, $resolver),
            default => $this->reorder($request, $validated, $guard, $resolver, $data),
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function addFromUrl(Request $request, array $validated, McpMutationGuard $guard, TripPlannerAssetResolver $resolver, TripPlannerData $data): ResponseFactory
    {
        abort_unless(isset($validated['collection'], $validated['image_url']), 422, 'collection and image_url are required for add_from_url.');
        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);

        $preview = new MutationPreview(
            action: 'manage-asset-media',
            summary: 'Add image to '.($asset->name ?? $asset->route_label).'.',
            changes: [[
                'operation' => 'add_media_from_url',
                'model' => $asset::class,
                'id' => $asset->id,
                'collection' => $validated['collection'],
                'image_url' => $validated['image_url'],
            ]],
            risk: 'medium',
        );

        return $guard->handle($request, $preview, function () use ($asset, $validated, $data): array {
            $media = $asset
                ->addMediaFromUrl($validated['image_url'])
                ->usingName($validated['name'] ?? ($asset->name ?? $asset->route_label))
                ->withCustomProperties([
                    'source_url' => $validated['source_url'] ?? $validated['image_url'],
                    'attribution' => $validated['attribution'] ?? null,
                ])
                ->toMediaCollection($validated['collection']);

            return [
                'media_id' => $media->id,
                'media' => $data->media($asset->refresh()),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function setMainImage(Request $request, array $validated, McpMutationGuard $guard, TripPlannerAssetResolver $resolver, TripPlannerData $data): ResponseFactory
    {
        abort_unless(isset($validated['media_id']), 422, 'media_id is required for set_main_image.');
        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);
        $media = $asset->media()->whereKey($validated['media_id'])->firstOrFail();

        $preview = new MutationPreview(
            action: 'manage-asset-media',
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
     * @param  array<string, mixed>  $validated
     */
    private function remove(Request $request, array $validated, McpMutationGuard $guard, TripPlannerAssetResolver $resolver): ResponseFactory
    {
        abort_unless(isset($validated['media_id']), 422, 'media_id is required for remove.');
        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);
        $media = $asset->media()->whereKey($validated['media_id'])->firstOrFail();

        $preview = new MutationPreview(
            action: 'manage-asset-media',
            summary: 'Remove media '.$media->id.' from '.($asset->name ?? $asset->route_label).'.',
            changes: [[
                'operation' => 'delete',
                'model' => Media::class,
                'id' => $media->id,
                'file_name' => $media->file_name,
            ]],
            risk: 'high',
            requiresConfirmation: true,
        );

        return $guard->handle($request, $preview, function () use ($media): array {
            $mediaId = $media->id;
            $media->delete();

            return ['deleted_media_id' => $mediaId];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function reorder(Request $request, array $validated, McpMutationGuard $guard, TripPlannerAssetResolver $resolver, TripPlannerData $data): ResponseFactory
    {
        abort_unless(isset($validated['collection'], $validated['media_ids']), 422, 'collection and media_ids are required for reorder.');

        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);
        $mediaIds = collect($validated['media_ids'])->values();
        $ownedIds = $asset->media()
            ->where('collection_name', $validated['collection'])
            ->whereIn('id', $mediaIds)
            ->pluck('id')
            ->all();

        abort_unless($mediaIds->diff($ownedIds)->isEmpty(), 404);

        $preview = new MutationPreview(
            action: 'manage-asset-media',
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
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()->description('add_from_url, set_main_image, remove, or reorder.')->required(),
            'type' => $schema->string()->description('Asset type.')->required(),
            'asset_id' => $schema->integer()->description('Optional asset id.'),
            'stable_key' => $schema->string()->description('Optional asset stable key.'),
            'collection' => $schema->string()->description('main_image or images. Required for add_from_url and reorder.'),
            'media_id' => $schema->integer()->description('Media id for set_main_image or remove.'),
            'media_ids' => $schema->array()->description('Ordered media ids for reorder.'),
            'image_url' => $schema->string()->description('Remote image URL for add_from_url.'),
            'name' => $schema->string()->description('Optional media name for add_from_url.'),
            'source_url' => $schema->string()->description('Optional page/source URL for add_from_url.'),
            'attribution' => $schema->string()->description('Optional attribution for add_from_url.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write when confirmation is required.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true for destructive operations.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response for confirmed operations.'),
        ];
    }
}
