<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerAssetResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete-shared-asset')]
#[Description('Delete an unused shared asset after preview and confirmation. Used assets are blocked with usage details.')]
#[IsDestructive]
class DeleteSharedAssetTool extends Tool
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
        ]);

        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);
        $usage = $resolver->usage($asset);

        if ($usage['day_nodes_count'] > 0 || $usage['slots_count'] > 0) {
            return Response::make(Response::error('Shared asset is still used and cannot be deleted.'))
                ->withStructuredContent([
                    'status' => 'blocked',
                    'asset_id' => $asset->id,
                    'usage' => $usage,
                ]);
        }

        $preview = new MutationPreview(
            action: 'delete-shared-asset',
            summary: 'Delete unused shared asset '.($asset->name ?? $asset->route_label).'.',
            changes: [[
                'operation' => 'delete',
                'model' => $asset::class,
                'id' => $asset->id,
                'stable_key' => $asset->stable_key,
            ]],
            risk: 'high',
        );

        return $guard->handle($request, $preview, function () use ($asset): array {
            $assetId = $asset->id;
            $asset->delete();

            return ['deleted_asset_id' => $assetId];
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
            'type' => $schema->string()->description('Asset type: accommodation, activity, food, or transport.')->required(),
            'asset_id' => $schema->integer()->description('Optional asset id.'),
            'stable_key' => $schema->string()->description('Optional asset stable key.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
