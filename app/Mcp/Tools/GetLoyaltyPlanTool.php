<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripCostEstimator;
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

#[Name('get-loyalty-plan')]
#[Description('Read EuroBonus loyalty assumptions, vouchers, fare options, award checks, bonus grab trips, and recommendation signals for a trip.')]
#[IsReadOnly]
class GetLoyaltyPlanTool extends Tool
{
    public function handle(Request $request, TripPlannerData $data, TripCostEstimator $estimator): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['nullable', 'string'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = isset($validated['variant_slug'])
            ? $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail()
            : $trip->defaultVariant();

        return Response::structured([
            'trip' => $data->trip($trip),
            'estimate' => $variant ? $estimator->estimate($variant)['loyalty'] : null,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trip_slug' => $schema->string()->description('Trip slug.')->required(),
            'variant_slug' => $schema->string()->description('Optional timeline variant slug. Defaults to the trip default variant.'),
        ];
    }
}
