<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerData;
use App\Models\DayTask;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-trip-context')]
#[Description('Get a compact trip context with variants, route points, day counts, and open task counts.')]
#[IsReadOnly]
class GetTripContextTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
        ]);

        $trip = Trip::query()
            ->with(['variants.routePoints'])
            ->where('slug', $validated['trip_slug'])
            ->firstOrFail();

        $variants = $trip->variants->map(function ($variant) use ($data): array {
            $variant->loadCount(['dayNodes', 'routePoints']);

            return $data->variant($variant) + [
                'day_nodes_count' => $variant->day_nodes_count,
                'route_points_count' => $variant->route_points_count,
                'open_tasks_count' => DayTask::query()
                    ->where('trip_variant_id', $variant->id)
                    ->where('status', 'open')
                    ->count(),
                'route_points' => $variant->routePoints->map(fn ($point): array => $data->routePoint($point))->all(),
            ];
        })->all();

        return Response::structured([
            'trip' => $data->trip($trip, includeVariants: false),
            'variants' => $variants,
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
            'trip_slug' => $schema->string()->description('Trip slug, for example japan-summer-2027.')->required(),
        ];
    }
}
