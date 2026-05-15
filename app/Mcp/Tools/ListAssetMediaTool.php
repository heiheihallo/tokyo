<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerAssetResolver;
use App\Mcp\Support\TripPlannerData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-asset-media')]
#[Description('List Spatie media records and conversion URLs for a shared asset.')]
#[IsReadOnly]
class ListAssetMediaTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerAssetResolver $resolver, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:accommodation,accommodations,activity,activities,food,food_spot,food_spots,transport,transport_leg,transport_legs'],
            'asset_id' => ['nullable', 'integer'],
            'stable_key' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);

        return Response::structured([
            'asset_id' => $asset->id,
            'media' => $data->media($asset),
        ]);
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
        ];
    }
}
