<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\TransportLeg;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('record-flight-price')]
#[Description('Record reviewed flight fare ranges and research metadata directly on a flight transport leg.')]
#[IsDestructive(false)]
class RecordFlightPriceTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'transport_leg_id' => ['required', 'integer', 'exists:transport_legs,id'],
            'price_min_nok' => ['required', 'integer', 'min:0'],
            'price_max_nok' => ['required', 'integer', 'min:0'],
            'price_min_jpy' => ['required', 'integer', 'min:0'],
            'price_max_jpy' => ['required', 'integer', 'min:0'],
            'price_basis' => ['required', 'in:per_person,per_group,per_leg'],
            'source_url' => ['required', 'url', 'max:2000'],
            'observed_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:observed,estimated,booked'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'fare_class' => ['nullable', 'string', 'max:255'],
            'luggage_assumptions' => ['nullable', 'string', 'max:1000'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:9'],
            'travel_dates' => ['nullable', 'string', 'max:255'],
        ]);

        $leg = TransportLeg::query()->findOrFail($validated['transport_leg_id']);

        $fare = [
            'source_url' => $validated['source_url'],
            'observed_at' => $validated['observed_at'] ?? now()->toDateString(),
            'status' => $validated['status'] ?? 'observed',
            'carrier' => $validated['carrier'] ?? null,
            'fare_class' => $validated['fare_class'] ?? null,
            'luggage_assumptions' => $validated['luggage_assumptions'] ?? null,
            'passengers' => $validated['passengers'] ?? null,
            'travel_dates' => $validated['travel_dates'] ?? null,
        ];

        $updates = [
            'mode' => 'flight',
            'price_min_nok' => $validated['price_min_nok'],
            'price_max_nok' => $validated['price_max_nok'],
            'price_min_jpy' => $validated['price_min_jpy'],
            'price_max_jpy' => $validated['price_max_jpy'],
            'price_basis' => $validated['price_basis'],
            'price_notes' => json_encode($fare, JSON_THROW_ON_ERROR),
        ];

        $preview = new MutationPreview(
            action: 'record-flight-price',
            summary: 'Record flight price for '.$leg->route_label.'.',
            changes: [[
                'operation' => 'update',
                'model' => TransportLeg::class,
                'id' => $leg->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $leg->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($leg, $updates, $data): array {
            $leg->update($updates);

            return ['asset' => $data->asset($leg->refresh())];
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
            'transport_leg_id' => $schema->integer()->description('Transport leg id.')->required(),
            'price_min_nok' => $schema->integer()->description('Minimum NOK fare.')->required(),
            'price_max_nok' => $schema->integer()->description('Maximum NOK fare.')->required(),
            'price_min_jpy' => $schema->integer()->description('Minimum JPY fare.')->required(),
            'price_max_jpy' => $schema->integer()->description('Maximum JPY fare.')->required(),
            'price_basis' => $schema->string()->description('per_person, per_group, or per_leg.')->required(),
            'source_url' => $schema->string()->description('Fare source URL.')->required(),
            'observed_at' => $schema->string()->description('Optional observed date. Defaults today.'),
            'status' => $schema->string()->description('observed, estimated, or booked.'),
            'carrier' => $schema->string()->description('Optional carrier.'),
            'fare_class' => $schema->string()->description('Optional fare class/cabin.'),
            'luggage_assumptions' => $schema->string()->description('Optional luggage assumptions.'),
            'passengers' => $schema->integer()->description('Optional passenger count.'),
            'travel_dates' => $schema->string()->description('Optional travel date summary.'),
            'dry_run' => $schema->boolean()->description('Optional. When true, previews the update without writing.'),
        ];
    }
}
