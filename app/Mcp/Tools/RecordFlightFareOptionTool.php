<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\TransportFareOption;
use App\Models\TransportLeg;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('record-flight-fare-option')]
#[Description('Record a cash, award, upgrade, or status-run fare option for a flight transport leg.')]
#[IsDestructive(false)]
class RecordFlightFareOptionTool extends Tool
{
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'transport_leg_id' => ['required', 'integer', 'exists:transport_legs,id'],
            'fare_option_id' => ['nullable', 'integer', 'exists:transport_fare_options,id'],
            'label' => ['required', 'string', 'max:255'],
            'fare_type' => ['required', 'in:cash,award,cash_upgrade,status_run'],
            'cabin' => ['required', 'in:economy,premium_economy,business,first'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:9'],
            'cash_min_nok' => ['nullable', 'integer', 'min:0'],
            'cash_max_nok' => ['nullable', 'integer', 'min:0'],
            'cash_min_jpy' => ['nullable', 'integer', 'min:0'],
            'cash_max_jpy' => ['nullable', 'integer', 'min:0'],
            'points_min' => ['nullable', 'integer', 'min:0'],
            'points_max' => ['nullable', 'integer', 'min:0'],
            'taxes_fees_min_nok' => ['nullable', 'integer', 'min:0'],
            'taxes_fees_max_nok' => ['nullable', 'integer', 'min:0'],
            'voucher_count' => ['nullable', 'integer', 'min:0', 'max:9'],
            'expected_level_points' => ['nullable', 'integer', 'min:0'],
            'expected_bonus_points' => ['nullable', 'integer', 'min:0'],
            'travel_dates' => ['nullable', 'string', 'max:255'],
            'observed_at' => ['nullable', 'date'],
            'fresh_until' => ['nullable', 'date'],
            'source_priority' => ['nullable', 'in:official,primary,secondary,tertiary'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'status' => ['nullable', 'in:candidate,preferred,booked,rejected,stale'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $leg = TransportLeg::query()->findOrFail($validated['transport_leg_id']);
        $option = isset($validated['fare_option_id']) ? $leg->fareOptions()->whereKey($validated['fare_option_id'])->firstOrFail() : null;
        $updates = Arr::except($validated, ['fare_option_id']);
        $updates['passengers'] ??= 2;
        $updates['voucher_count'] ??= 0;
        $updates['expected_level_points'] ??= 0;
        $updates['expected_bonus_points'] ??= 0;
        $updates['source_priority'] ??= 'secondary';
        $updates['status'] ??= 'candidate';

        $preview = new MutationPreview(
            action: 'record-flight-fare-option',
            summary: 'Record '.$updates['label'].' for '.$leg->route_label.'.',
            changes: [[
                'operation' => $option ? 'update' : 'create',
                'model' => TransportFareOption::class,
                'id' => $option?->id,
                'before' => $option ? collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $option->{$key}])->all() : [],
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($leg, $option, $updates, $data): array {
            $saved = $option
                ? tap($option)->update($updates)
                : $leg->fareOptions()->create($updates);

            return ['fare_option' => $data->fareOption($saved->refresh())];
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transport_leg_id' => $schema->integer()->description('Transport leg id.')->required(),
            'fare_option_id' => $schema->integer()->description('Optional existing fare option id to update.'),
            'label' => $schema->string()->description('Fare option label.')->required(),
            'fare_type' => $schema->string()->description('cash, award, cash_upgrade, or status_run.')->required(),
            'cabin' => $schema->string()->description('economy, premium_economy, business, or first.')->required(),
            'carrier' => $schema->string()->description('Optional carrier.'),
            'passengers' => $schema->integer()->description('Passenger count. Defaults to 2.'),
            'cash_min_nok' => $schema->integer()->description('Minimum cash cost in NOK.'),
            'cash_max_nok' => $schema->integer()->description('Maximum cash cost in NOK.'),
            'cash_min_jpy' => $schema->integer()->description('Minimum cash cost in JPY.'),
            'cash_max_jpy' => $schema->integer()->description('Maximum cash cost in JPY.'),
            'points_min' => $schema->integer()->description('Minimum EuroBonus points required.'),
            'points_max' => $schema->integer()->description('Maximum EuroBonus points required.'),
            'taxes_fees_min_nok' => $schema->integer()->description('Minimum taxes and fees in NOK.'),
            'taxes_fees_max_nok' => $schema->integer()->description('Maximum taxes and fees in NOK.'),
            'voucher_count' => $schema->integer()->description('2-for-1 vouchers used.'),
            'expected_level_points' => $schema->integer()->description('Expected level points earned.'),
            'expected_bonus_points' => $schema->integer()->description('Expected redeemable points earned.'),
            'travel_dates' => $schema->string()->description('Optional travel date summary.'),
            'observed_at' => $schema->string()->description('Observation date.'),
            'fresh_until' => $schema->string()->description('Date when this observation should be considered stale.'),
            'source_priority' => $schema->string()->description('official, primary, secondary, or tertiary.'),
            'source_url' => $schema->string()->description('Source URL.'),
            'status' => $schema->string()->description('candidate, preferred, booked, rejected, or stale.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'dry_run' => $schema->boolean()->description('Optional. When true, previews the update without writing.'),
        ];
    }
}
