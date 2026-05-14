<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-assets')]
#[Description('Search reusable accommodations, activities, food spots, and transport legs.')]
#[IsReadOnly]
class SearchAssetsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:accommodations,activities,food,transport'],
            'query' => ['nullable', 'string', 'max:255'],
        ]);

        $types = isset($validated['type'])
            ? [$validated['type']]
            : ['accommodations', 'activities', 'food', 'transport'];

        $assets = collect($types)
            ->mapWithKeys(fn (string $type): array => [$type => $data->assets($type, $validated['query'] ?? null)->all()])
            ->all();

        return Response::structured(['assets' => $assets]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Optional asset type: accommodations, activities, food, or transport.'),
            'query' => $schema->string()->description('Optional text search.'),
        ];
    }
}
