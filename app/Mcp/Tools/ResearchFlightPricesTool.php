<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerAssetResolver;
use App\Mcp\Support\TripPlannerData;
use App\Models\TransportLeg;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('research-flight-prices')]
#[Description('Prepare flight fare research context and booking-site links for a flight transport leg without writing.')]
#[IsReadOnly]
class ResearchFlightPricesTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerAssetResolver $resolver, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'transport_leg_id' => ['nullable', 'integer', 'exists:transport_legs,id'],
            'stable_key' => ['nullable', 'string', 'max:255'],
            'origin' => ['nullable', 'string', 'max:10'],
            'destination' => ['nullable', 'string', 'max:10'],
            'depart_on' => ['nullable', 'date'],
            'return_on' => ['nullable', 'date'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:9'],
            'cabin' => ['nullable', 'in:economy,premium_economy,business,first'],
        ]);

        $leg = isset($validated['transport_leg_id']) || isset($validated['stable_key'])
            ? $resolver->find('transport', $validated['transport_leg_id'] ?? null, $validated['stable_key'] ?? null)
            : null;

        abort_unless($leg === null || $leg instanceof TransportLeg, 404);

        $origin = $validated['origin'] ?? $leg?->origin;
        $destination = $validated['destination'] ?? $leg?->destination;
        $passengers = $validated['passengers'] ?? 2;
        $cabin = $validated['cabin'] ?? 'economy';

        $query = collect([$origin, $destination, $validated['depart_on'] ?? null, $validated['return_on'] ?? null, $passengers.' passengers', $cabin])
            ->filter()
            ->implode(' ');

        return Response::structured([
            'transport_leg' => $leg ? $data->asset($leg) : null,
            'research_request' => Arr::only($validated, ['depart_on', 'return_on', 'passengers', 'cabin']) + [
                'origin' => $origin,
                'destination' => $destination,
            ],
            'candidate_sources' => [
                [
                    'name' => 'Google Flights search',
                    'url' => 'https://www.google.com/travel/flights?q='.urlencode($query),
                ],
                [
                    'name' => 'ITA Matrix search',
                    'url' => 'https://matrix.itasoftware.com/search',
                    'notes' => 'Use the route/date/passenger details from research_request.',
                ],
                [
                    'name' => 'Airline direct search',
                    'url' => 'https://www.google.com/search?q='.urlencode($query.' airline direct fare'),
                ],
            ],
            'recording_hint' => 'After reviewing fares, use record-flight-price to store the observed range, source URL, and assumptions.',
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
            'transport_leg_id' => $schema->integer()->description('Optional transport leg id.'),
            'stable_key' => $schema->string()->description('Optional transport leg stable key.'),
            'origin' => $schema->string()->description('Optional airport origin code.'),
            'destination' => $schema->string()->description('Optional airport destination code.'),
            'depart_on' => $schema->string()->description('Optional departure date.'),
            'return_on' => $schema->string()->description('Optional return date.'),
            'passengers' => $schema->integer()->description('Passenger count. Defaults to 2.'),
            'cabin' => $schema->string()->description('economy, premium_economy, business, or first.'),
        ];
    }
}
