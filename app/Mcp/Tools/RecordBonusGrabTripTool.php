<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\BonusGrabTrip;
use App\Models\LoyaltyProgramSnapshot;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('record-bonus-grab-trip')]
#[Description('Record a private bonus grab trip candidate that earns EuroBonus or level points for the Japan trip strategy.')]
#[IsDestructive(false)]
class RecordBonusGrabTripTool extends Tool
{
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'loyalty_program_snapshot_id' => ['nullable', 'integer', 'exists:loyalty_program_snapshots,id'],
            'bonus_grab_trip_id' => ['nullable', 'integer', 'exists:bonus_grab_trips,id'],
            'title' => ['required', 'string', 'max:255'],
            'route_label' => ['required', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'cash_cost_min_nok' => ['nullable', 'integer', 'min:0'],
            'cash_cost_max_nok' => ['nullable', 'integer', 'min:0'],
            'expected_bonus_points' => ['nullable', 'integer', 'min:0'],
            'expected_level_points' => ['nullable', 'integer', 'min:0'],
            'nights_away' => ['nullable', 'integer', 'min:0', 'max:30'],
            'cabin' => ['nullable', 'in:economy,premium_economy,business,first'],
            'feasibility_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:candidate,watching,preferred,booked,rejected'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'legs' => ['nullable', 'array'],
            'legs.*.sequence' => ['required_with:legs', 'integer', 'min:1', 'max:20'],
            'legs.*.origin' => ['required_with:legs', 'string', 'max:10'],
            'legs.*.destination' => ['required_with:legs', 'string', 'max:10'],
            'legs.*.carrier' => ['nullable', 'string', 'max:255'],
            'legs.*.flight_number' => ['nullable', 'string', 'max:50'],
            'legs.*.cabin' => ['nullable', 'string', 'max:50'],
            'legs.*.departs_at' => ['nullable', 'date'],
            'legs.*.arrives_at' => ['nullable', 'date'],
            'legs.*.expected_bonus_points' => ['nullable', 'integer', 'min:0'],
            'legs.*.expected_level_points' => ['nullable', 'integer', 'min:0'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $snapshot = isset($validated['loyalty_program_snapshot_id'])
            ? $trip->loyaltyProgramSnapshots()->whereKey($validated['loyalty_program_snapshot_id'])->firstOrFail()
            : $trip->loyaltyProgramSnapshots()->first();

        if (! $snapshot instanceof LoyaltyProgramSnapshot) {
            $snapshot = $trip->loyaltyProgramSnapshots()->create(['program_name' => 'EuroBonus']);
        }

        $bonusGrabTrip = isset($validated['bonus_grab_trip_id'])
            ? $snapshot->bonusGrabTrips()->whereKey($validated['bonus_grab_trip_id'])->firstOrFail()
            : null;
        $updates = Arr::except($validated, ['trip_slug', 'loyalty_program_snapshot_id', 'bonus_grab_trip_id', 'legs']);
        $updates['expected_bonus_points'] ??= 0;
        $updates['expected_level_points'] ??= 0;
        $updates['nights_away'] ??= 0;
        $updates['cabin'] ??= 'premium_economy';
        $updates['status'] ??= 'candidate';
        $legs = $validated['legs'] ?? [];

        $preview = new MutationPreview(
            action: 'record-bonus-grab-trip',
            summary: 'Record bonus grab trip '.$updates['title'].' for '.$trip->name.'.',
            changes: [[
                'operation' => $bonusGrabTrip ? 'update' : 'create',
                'model' => BonusGrabTrip::class,
                'id' => $bonusGrabTrip?->id,
                'before' => $bonusGrabTrip ? collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $bonusGrabTrip->{$key}])->all() : [],
                'after' => $updates + ['legs_count' => count($legs)],
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($snapshot, $bonusGrabTrip, $updates, $legs, $data): array {
            $saved = DB::transaction(function () use ($snapshot, $bonusGrabTrip, $updates, $legs): BonusGrabTrip {
                $saved = $bonusGrabTrip
                    ? tap($bonusGrabTrip)->update($updates)
                    : $snapshot->bonusGrabTrips()->create($updates);

                if ($legs !== []) {
                    $saved->legs()->delete();

                    foreach ($legs as $leg) {
                        $leg['expected_bonus_points'] ??= 0;
                        $leg['expected_level_points'] ??= 0;

                        $saved->legs()->create($leg);
                    }
                }

                return $saved;
            });

            return ['bonus_grab_trip' => $data->bonusGrabTrip($saved->refresh())];
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trip_slug' => $schema->string()->description('Trip slug.')->required(),
            'loyalty_program_snapshot_id' => $schema->integer()->description('Optional loyalty snapshot id.'),
            'bonus_grab_trip_id' => $schema->integer()->description('Optional existing bonus grab trip id to update.'),
            'title' => $schema->string()->description('Candidate title.')->required(),
            'route_label' => $schema->string()->description('Route summary such as OSL-CPH-TYO-CPH-OSL.')->required(),
            'starts_on' => $schema->string()->description('Optional start date.'),
            'ends_on' => $schema->string()->description('Optional end date.'),
            'cash_cost_min_nok' => $schema->integer()->description('Minimum cash cost in NOK.'),
            'cash_cost_max_nok' => $schema->integer()->description('Maximum cash cost in NOK.'),
            'expected_bonus_points' => $schema->integer()->description('Expected redeemable points earned.'),
            'expected_level_points' => $schema->integer()->description('Expected level points earned.'),
            'nights_away' => $schema->integer()->description('Nights away from home.'),
            'cabin' => $schema->string()->description('economy, premium_economy, business, or first.'),
            'feasibility_score' => $schema->integer()->description('0-100 practical feasibility score.'),
            'status' => $schema->string()->description('candidate, watching, preferred, booked, or rejected.'),
            'source_url' => $schema->string()->description('Source URL.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'legs' => $schema->array()->description('Optional flight legs array.'),
            'dry_run' => $schema->boolean()->description('Optional. When true, previews the update without writing.'),
        ];
    }
}
