<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
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

#[Name('update-loyalty-plan')]
#[Description('Create or update a trip loyalty planning snapshot, including projected EuroBonus points, level points, and 2-for-1 vouchers.')]
#[IsDestructive(false)]
class UpdateLoyaltyPlanTool extends Tool
{
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'program_name' => ['nullable', 'string', 'max:255'],
            'current_points' => ['nullable', 'integer', 'min:0'],
            'current_level_points' => ['nullable', 'integer', 'min:0'],
            'qualification_starts_on' => ['nullable', 'date'],
            'qualification_ends_on' => ['nullable', 'date'],
            'target_tier' => ['nullable', 'string', 'max:255'],
            'target_level_points' => ['nullable', 'integer', 'min:0'],
            'target_qualifying_flights' => ['nullable', 'integer', 'min:0'],
            'expected_trip_level_points' => ['nullable', 'integer', 'min:0'],
            'signup_bonus_points' => ['nullable', 'integer', 'min:0'],
            'card_spend_target_nok' => ['nullable', 'integer', 'min:0'],
            'card_points_per_100_nok' => ['nullable', 'integer', 'min:0'],
            'card_level_points_per_100_nok' => ['nullable', 'integer', 'min:0'],
            'projected_card_points' => ['nullable', 'integer', 'min:0'],
            'projected_card_level_points' => ['nullable', 'integer', 'min:0'],
            'expected_voucher_quantity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'assumptions' => ['nullable', 'array'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $snapshot = $trip->loyaltyProgramSnapshots()->where('program_name', $validated['program_name'] ?? 'EuroBonus')->first();
        $updates = collect(Arr::except($validated, ['trip_slug', 'expected_voucher_quantity']))
            ->filter(fn ($value): bool => $value !== null)
            ->all();
        $updates['program_name'] ??= 'EuroBonus';

        $preview = new MutationPreview(
            action: 'update-loyalty-plan',
            summary: 'Update '.$updates['program_name'].' loyalty plan for '.$trip->name.'.',
            changes: [[
                'operation' => $snapshot ? 'update' : 'create',
                'model' => LoyaltyProgramSnapshot::class,
                'id' => $snapshot?->id,
                'before' => $snapshot ? collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $snapshot->{$key}])->all() : [],
                'after' => $updates + ['expected_voucher_quantity' => $validated['expected_voucher_quantity'] ?? null],
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($trip, $snapshot, $updates, $validated, $data): array {
            $saved = DB::transaction(function () use ($trip, $snapshot, $updates, $validated): LoyaltyProgramSnapshot {
                $saved = $snapshot
                    ? tap($snapshot)->update($updates)
                    : $trip->loyaltyProgramSnapshots()->create($updates);

                if (array_key_exists('expected_voucher_quantity', $validated)) {
                    $saved->vouchers()->updateOrCreate(
                        ['voucher_type' => '2-for-1', 'status' => 'expected'],
                        [
                            'quantity' => $validated['expected_voucher_quantity'],
                            'earned_threshold_nok' => $saved->card_spend_target_nok >= 300000 ? 300000 : 150000,
                        ],
                    );
                }

                return $saved;
            });

            return ['loyalty_plan' => $data->loyaltyProgramSnapshot($saved->refresh())];
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trip_slug' => $schema->string()->description('Trip slug.')->required(),
            'program_name' => $schema->string()->description('Program name. Defaults to EuroBonus.'),
            'current_points' => $schema->integer()->description('Current redeemable points.'),
            'current_level_points' => $schema->integer()->description('Current level points.'),
            'qualification_starts_on' => $schema->string()->description('Qualification period start date.'),
            'qualification_ends_on' => $schema->string()->description('Qualification period end date.'),
            'target_tier' => $schema->string()->description('Target tier label.'),
            'target_level_points' => $schema->integer()->description('Target level points.'),
            'target_qualifying_flights' => $schema->integer()->description('Alternative qualifying-flight target.'),
            'expected_trip_level_points' => $schema->integer()->description('Expected level points from already planned trips.'),
            'signup_bonus_points' => $schema->integer()->description('Expected signup bonus points.'),
            'card_spend_target_nok' => $schema->integer()->description('Card spend target in NOK.'),
            'card_points_per_100_nok' => $schema->integer()->description('Redeemable points per 100 NOK.'),
            'card_level_points_per_100_nok' => $schema->integer()->description('Level points per 100 NOK.'),
            'projected_card_points' => $schema->integer()->description('Projected redeemable points from card spend.'),
            'projected_card_level_points' => $schema->integer()->description('Projected level points from card spend.'),
            'expected_voucher_quantity' => $schema->integer()->description('Expected 2-for-1 voucher quantity.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'assumptions' => $schema->object()->description('Optional structured assumptions.'),
            'dry_run' => $schema->boolean()->description('Optional. When true, previews the update without writing.'),
        ];
    }
}
