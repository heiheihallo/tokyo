<?php

namespace App\Mcp\Tools;

use App\Support\JapanTripReference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-reference-context')]
#[Description('Summarize the canonical Japan 2027 reference data used by the importer.')]
#[IsReadOnly]
class GetReferenceContextTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'trip' => JapanTripReference::trip(),
            'variants' => JapanTripReference::variants(),
            'source_count' => count(JapanTripReference::sources()),
            'asset_counts' => collect(JapanTripReference::assets())->map(fn (array $items): int => count($items))->all(),
            'day_count' => count(JapanTripReference::dayTemplates()),
            'route_point_count' => count(JapanTripReference::routePoints()),
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
            //
        ];
    }
}
