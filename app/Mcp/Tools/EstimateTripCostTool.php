<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripCostEstimator;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('estimate-trip-cost')]
#[Description('Estimate trip costs from attached shared asset price ranges grouped by price basis.')]
#[IsReadOnly]
class EstimateTripCostTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripCostEstimator $estimator): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['required', 'string'],
            'stale_flight_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();

        return Response::structured([
            'estimate' => $estimator->estimate($variant, $validated['stale_flight_days'] ?? 14),
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
            'trip_slug' => $schema->string()->description('Trip slug.')->required(),
            'variant_slug' => $schema->string()->description('Timeline variant slug.')->required(),
            'stale_flight_days' => $schema->integer()->description('Number of days after which observed flight fares are stale. Defaults to 14.'),
        ];
    }
}
