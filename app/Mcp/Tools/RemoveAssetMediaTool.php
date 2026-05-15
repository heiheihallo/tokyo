<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerAssetResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Name('remove-asset-media')]
#[Description('Remove one media item from a shared asset after preview and confirmation.')]
#[IsDestructive]
class RemoveAssetMediaTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerAssetResolver $resolver): ResponseFactory
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
            action: 'remove-asset-media',
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
            'media_id' => $schema->integer()->description('Media id to remove.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
