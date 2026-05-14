<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerData;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-trips')]
#[Description('List trips with variants, date ranges, publication state, and summary metadata.')]
#[IsReadOnly]
class ListTripsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerData $data): ResponseFactory
    {
        $trips = Trip::query()
            ->with('variants')
            ->orderBy('starts_on')
            ->orderBy('name')
            ->get()
            ->map(fn (Trip $trip): array => $data->trip($trip))
            ->all();

        return Response::structured(['trips' => $trips]);
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
