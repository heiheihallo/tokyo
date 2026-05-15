<?php

namespace App\Mcp\Support;

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\FoodSpot;
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
            'dayNodes.transportLegs',
            'dayNodes.activities',
            'dayNodes.foodSpots',
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
}
