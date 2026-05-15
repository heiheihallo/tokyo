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

#[Name('add-asset-media-from-url')]
#[Description('Add an image URL directly to a shared asset media collection.')]
#[IsDestructive(false)]
class AddAssetMediaFromUrlTool extends Tool
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
            'image_url' => ['required', 'url', 'starts_with:https://,http://', 'max:2000'],
            'name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'attribution' => ['nullable', 'string', 'max:1000'],
        ]);

        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);

        $preview = new MutationPreview(
            action: 'add-asset-media-from-url',
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
            'image_url' => $schema->string()->description('Remote image URL.')->required(),
            'name' => $schema->string()->description('Optional media name.'),
            'source_url' => $schema->string()->description('Optional page/source URL.'),
            'attribution' => $schema->string()->description('Optional attribution.'),
            'dry_run' => $schema->boolean()->description('Optional. When true, previews the media addition without writing.'),
        ];
    }
}
