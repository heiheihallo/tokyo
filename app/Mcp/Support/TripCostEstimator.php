<?php

namespace App\Mcp\Support;

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\BonusGrabTrip;
use App\Models\FoodSpot;
use App\Models\LoyaltyProgramSnapshot;
use App\Models\LoyaltyVoucher;
use App\Models\TransportFareOption;
use App\Models\TransportLeg;
use App\Models\TripVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TripCostEstimator
{
    /**
     * @return array<string, mixed>
     */
    public function estimate(TripVariant $variant, int $staleFlightDays = 14): array
    {
        $variant->loadMissing([
            'trip',
            'dayNodes.accommodations',
            'dayNodes.transportLegs.fareOptions.awardAvailabilityChecks',
            'dayNodes.activities',
            'dayNodes.foodSpots',
            'trip.loyaltyProgramSnapshots.vouchers',
            'trip.loyaltyProgramSnapshots.bonusGrabTrips.legs',
        ]);

        $included = collect();
        $excluded = collect();

        foreach ($variant->dayNodes as $day) {
            foreach ([
                'accommodation' => $day->accommodations,
                'transport' => $day->transportLegs,
                'activity' => $day->activities,
                'food' => $day->foodSpots,
            ] as $type => $assets) {
                foreach ($assets as $asset) {
                    $line = $this->lineItem($type, $asset, [
                        'day_node_id' => $day->id,
                        'day' => $day->stable_key,
                        'day_number' => $day->day_number,
                    ], $staleFlightDays);

                    if ($line['included']) {
                        $included->push($line);
                    } else {
                        $excluded->push($line);
                    }
                }
            }
        }

        return [
            'trip_slug' => $variant->trip->slug,
            'variant_slug' => $variant->slug,
            'currency' => [
                'primary' => $variant->trip->currency_primary,
                'secondary' => $variant->trip->currency_secondary,
            ],
            'totals_by_basis' => $this->totalsByBasis($included),
            'loyalty' => $this->loyaltyEstimate($variant),
            'included_items' => $included->values()->all(),
            'excluded_items' => $excluded->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function lineItem(string $type, Accommodation|Activity|FoodSpot|TransportLeg $asset, array $context, int $staleFlightDays): array
    {
        $basis = $asset->price_basis ?: 'unknown';
        $missingPrice = $asset->price_min_nok === null
            || $asset->price_max_nok === null
            || $asset->price_min_jpy === null
            || $asset->price_max_jpy === null;

        $reasons = [];

        if ($basis === 'unknown') {
            $reasons[] = 'unknown_basis';
        }

        if ($missingPrice) {
            $reasons[] = 'missing_price';
        }

        $fare = $asset instanceof TransportLeg ? $this->fareMetadata($asset) : [];

        if (($asset->mode ?? null) === 'flight' && isset($fare['observed_at'])) {
            $observedAt = Carbon::parse($fare['observed_at']);

            if ($observedAt->lt(now()->subDays($staleFlightDays))) {
                $reasons[] = 'stale_flight_price';
            }
        }

        return [
            'included' => $reasons === [],
            'excluded_reasons' => $reasons,
            'type' => $type,
            'asset_id' => $asset->id,
            'stable_key' => $asset->stable_key,
            'name' => $asset->name ?? $asset->route_label,
            'basis' => $basis,
            'price_min_nok' => $asset->price_min_nok,
            'price_max_nok' => $asset->price_max_nok,
            'price_min_jpy' => $asset->price_min_jpy,
            'price_max_jpy' => $asset->price_max_jpy,
            'price_notes' => $asset->price_notes,
            'fare' => $fare,
            'context' => $context,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, array<string, int>>
     */
    private function totalsByBasis(Collection $items): array
    {
        return $items
            ->groupBy('basis')
            ->map(fn (Collection $lines): array => [
                'min_nok' => (int) $lines->sum('price_min_nok'),
                'max_nok' => (int) $lines->sum('price_max_nok'),
                'min_jpy' => (int) $lines->sum('price_min_jpy'),
                'max_jpy' => (int) $lines->sum('price_max_jpy'),
                'items_count' => $lines->count(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fareMetadata(TransportLeg $asset): array
    {
        if ($asset->price_notes === null) {
            return [];
        }

        $decoded = json_decode($asset->price_notes, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function loyaltyEstimate(TripVariant $variant): array
    {
        $snapshot = $variant->trip->loyaltyProgramSnapshots->first();

        if (! $snapshot instanceof LoyaltyProgramSnapshot) {
            return [
                'snapshot' => null,
                'fare_options' => [],
                'bonus_grab_trips' => [],
                'recommendation' => [
                    'summary' => 'Add a loyalty plan to compare EuroBonus, vouchers, and bonus grab trips.',
                    'warnings' => ['missing_loyalty_plan'],
                ],
            ];
        }

        $basePoints = $snapshot->current_points + $snapshot->signup_bonus_points + $snapshot->projected_card_points;
        $baseLevelPoints = $snapshot->current_level_points + $snapshot->expected_trip_level_points + $snapshot->projected_card_level_points;
        $levelPointGap = max(0, $snapshot->target_level_points - $baseLevelPoints);
        $availableVouchers = $snapshot->vouchers
            ->filter(fn (LoyaltyVoucher $voucher): bool => in_array($voucher->status, ['earned', 'expected'], true))
            ->sum('quantity');

        $fareOptions = $variant->dayNodes
            ->flatMap(fn ($day): Collection => $day->transportLegs)
            ->unique('id')
            ->flatMap(fn (TransportLeg $leg): Collection => $leg->fareOptions->map(fn (TransportFareOption $option): array => $this->fareOptionEstimate($option, $basePoints, $availableVouchers)))
            ->values();

        $bonusGrabTrips = $snapshot->bonusGrabTrips
            ->map(fn (BonusGrabTrip $trip): array => $this->bonusGrabTripEstimate($trip, $basePoints, $baseLevelPoints, $snapshot->target_level_points))
            ->values();

        return [
            'snapshot' => [
                'program_name' => $snapshot->program_name,
                'target_tier' => $snapshot->target_tier,
                'qualification_ends_on' => $snapshot->qualification_ends_on?->toDateString(),
                'projected_points_before_fare_options' => $basePoints,
                'projected_level_points_before_bonus_grabs' => $baseLevelPoints,
                'remaining_level_points_to_target' => $levelPointGap,
                'available_vouchers' => $availableVouchers,
            ],
            'fare_options' => $fareOptions->all(),
            'bonus_grab_trips' => $bonusGrabTrips->all(),
            'recommendation' => $this->recommendation($fareOptions, $bonusGrabTrips, $levelPointGap),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fareOptionEstimate(TransportFareOption $option, int $projectedPoints, int $availableVouchers): array
    {
        $latestAvailability = $option->awardAvailabilityChecks->sortByDesc('checked_on')->first();
        $warnings = [];

        if ($option->fresh_until !== null && $option->fresh_until->isPast()) {
            $warnings[] = 'stale_fare_option';
        }

        if (($option->points_max ?? $option->points_min ?? 0) > $projectedPoints) {
            $warnings[] = 'points_shortfall';
        }

        if ($option->voucher_count > $availableVouchers) {
            $warnings[] = 'voucher_shortfall';
        }

        if ($option->fare_type === 'award' && (! $latestAvailability || $latestAvailability->availability_status !== 'available')) {
            $warnings[] = 'award_availability_missing';
        }

        return [
            'id' => $option->id,
            'transport_leg_id' => $option->transport_leg_id,
            'label' => $option->label,
            'fare_type' => $option->fare_type,
            'cabin' => $option->cabin,
            'carrier' => $option->carrier,
            'status' => $option->status,
            'cash_min_nok' => $option->cash_min_nok,
            'cash_max_nok' => $option->cash_max_nok,
            'points_min' => $option->points_min,
            'points_max' => $option->points_max,
            'taxes_fees_min_nok' => $option->taxes_fees_min_nok,
            'taxes_fees_max_nok' => $option->taxes_fees_max_nok,
            'voucher_count' => $option->voucher_count,
            'expected_level_points' => $option->expected_level_points,
            'expected_bonus_points' => $option->expected_bonus_points,
            'fresh_until' => $option->fresh_until?->toDateString(),
            'latest_award_availability' => $latestAvailability?->availability_status,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bonusGrabTripEstimate(BonusGrabTrip $trip, int $projectedPoints, int $projectedLevelPoints, int $targetLevelPoints): array
    {
        $levelPointsAfter = $projectedLevelPoints + $trip->expected_level_points;
        $cashMax = $trip->cash_cost_max_nok ?? $trip->cash_cost_min_nok;

        return [
            'id' => $trip->id,
            'title' => $trip->title,
            'route_label' => $trip->route_label,
            'status' => $trip->status,
            'cash_cost_min_nok' => $trip->cash_cost_min_nok,
            'cash_cost_max_nok' => $trip->cash_cost_max_nok,
            'expected_bonus_points' => $trip->expected_bonus_points,
            'expected_level_points' => $trip->expected_level_points,
            'projected_points_after' => $projectedPoints + $trip->expected_bonus_points,
            'projected_level_points_after' => $levelPointsAfter,
            'remaining_level_points_after' => max(0, $targetLevelPoints - $levelPointsAfter),
            'nok_per_level_point' => $cashMax && $trip->expected_level_points > 0 ? round($cashMax / $trip->expected_level_points, 2) : null,
            'nights_away' => $trip->nights_away,
            'feasibility_score' => $trip->feasibility_score,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $fareOptions
     * @param  Collection<int, array<string, mixed>>  $bonusGrabTrips
     * @return array<string, mixed>
     */
    private function recommendation(Collection $fareOptions, Collection $bonusGrabTrips, int $levelPointGap): array
    {
        $cleanAward = $fareOptions->first(fn (array $option): bool => $option['fare_type'] === 'award' && $option['warnings'] === []);
        $bestBonusGrab = $bonusGrabTrips
            ->filter(fn (array $trip): bool => $trip['expected_level_points'] > 0)
            ->sortBy(fn (array $trip): array => [$trip['remaining_level_points_after'], $trip['nok_per_level_point'] ?? PHP_INT_MAX])
            ->first();

        $warnings = $fareOptions
            ->flatMap(fn (array $option): array => $option['warnings'])
            ->unique()
            ->values()
            ->all();

        if ($levelPointGap > 0) {
            $warnings[] = 'gold_gap_remaining';
        }

        $summary = $cleanAward
            ? 'Use the available award option as the leading redemption candidate and keep paid SAS Premium as the status fallback.'
            : 'Keep paid SAS Premium or Business as the fallback while watching award availability.';

        if ($bestBonusGrab) {
            $summary .= ' Best bonus grab candidate: '.$bestBonusGrab['title'].' leaves '.$bestBonusGrab['remaining_level_points_after'].' level points to target.';
        }

        return [
            'summary' => $summary,
            'best_award_option_id' => $cleanAward['id'] ?? null,
            'best_bonus_grab_trip_id' => $bestBonusGrab['id'] ?? null,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
