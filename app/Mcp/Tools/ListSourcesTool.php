<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerData;
use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-sources')]
#[Description('List source records used by trip planning.')]
#[IsReadOnly]
class ListSourcesTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
        ]);

        return Response::structured([
            'sources' => Source::query()
                ->when($validated['query'] ?? null, fn ($query, string $term) => $query->where('title', 'like', '%'.$term.'%')->orWhere('source_key', 'like', '%'.$term.'%'))
                ->orderBy('source_key')
                ->limit(100)
                ->get()
                ->map(fn (Source $source): array => $data->source($source))
                ->all(),
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
            'query' => $schema->string()->description('Optional source title or key search.'),
        ];
    }
}
