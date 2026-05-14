<?php

namespace App\Console\Commands;

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\DayTask;
use App\Models\FoodSpot;
use App\Models\RoutePoint;
use App\Models\Source;
use App\Models\TransportLeg;
use App\Models\Trip;
use App\Models\TripVariant;
use App\Support\JapanTripReference;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('trip:import-japan-reference {--sync-reference : Update existing reference records instead of only creating missing records}')]
#[Description('Import the non-destructive Tokyo 2027 reference trip, variants, timelines, and shared assets.')]
class ImportJapanTrip extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        DB::transaction(function (): void {
            $sync = (bool) $this->option('sync-reference');
            $trip = $this->upsertTrip($sync);
            $sources = $this->upsertSources($sync);
            $assets = $this->upsertAssets($sync);
            $variants = $this->upsertVariants($trip, $sync);

            foreach ($variants as $variant) {
                $this->upsertTimeline($trip, $variant, $sources, $assets, $sync);
                $this->upsertRoutePoints($variant, $sync);
            }
        });

        $this->components->info('Japan 2027 reference trip imported without truncating or deleting existing records.');

        return self::SUCCESS;
    }

    private function upsertTrip(bool $sync): Trip
    {
        $data = JapanTripReference::trip();
        $trip = Trip::firstOrNew(['slug' => $data['slug']]);

        if (! $trip->exists || $sync) {
            $trip->fill($data)->save();
        }

        return $trip;
    }

    /**
     * @return array<string, Source>
     */
    private function upsertSources(bool $sync): array
    {
        return collect(JapanTripReference::sources())
            ->mapWithKeys(function (array $data) use ($sync): array {
                $source = Source::firstOrNew(['source_key' => $data['source_key']]);

                if (! $source->exists || $sync) {
                    $source->fill($data)->save();
                }

                return [$source->source_key => $source];
            })
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function upsertAssets(bool $sync): array
    {
        $reference = JapanTripReference::assets();

        return [
            'accommodations' => $this->upsertAssetSet(Accommodation::class, $reference['accommodations'], $sync),
            'activities' => $this->upsertAssetSet(Activity::class, $reference['activities'], $sync),
            'food_spots' => $this->upsertAssetSet(FoodSpot::class, $reference['food_spots'], $sync),
            'transport_legs' => $this->upsertAssetSet(TransportLeg::class, $reference['transport_legs'], $sync),
        ];
    }

    /**
     * @param  class-string<Accommodation|Activity|FoodSpot|TransportLeg>  $modelClass
     * @return array<string, Accommodation|Activity|FoodSpot|TransportLeg>
     */
    private function upsertAssetSet(string $modelClass, array $records, bool $sync): array
    {
        return collect($records)
            ->mapWithKeys(function (array $data) use ($modelClass, $sync): array {
                $asset = $modelClass::firstOrNew(['stable_key' => $data['stable_key']]);

                if (! $asset->exists || $sync) {
                    $asset->fill($data)->save();
                }

                return [$asset->stable_key => $asset];
            })
            ->all();
    }

    /**
     * @return array<string, TripVariant>
     */
    private function upsertVariants(Trip $trip, bool $sync): array
    {
        return collect(JapanTripReference::variants())
            ->mapWithKeys(function (array $data) use ($trip, $sync): array {
                $variant = TripVariant::firstOrNew([
                    'trip_id' => $trip->id,
                    'slug' => $data['slug'],
                ]);

                if (! $variant->exists || $sync) {
                    $variant->fill($data + ['trip_id' => $trip->id])->save();
                }

                return [$variant->slug => $variant];
            })
            ->all();
    }

    /**
     * @param  array<string, Source>  $sources
     * @param  array<string, array<string, mixed>>  $assets
     */
    private function upsertTimeline(Trip $trip, TripVariant $variant, array $sources, array $assets, bool $sync): void
    {
        foreach (JapanTripReference::dayTemplates() as $template) {
            [$dayNumber, $date, $location, $title, $nodeTypes, $priority, $valueCost, $premiumCost, $sourceKeys, $accommodationKeys, $transportKeys, $activityKeys, $foodKeys] = $template;

            $day = DayNode::firstOrNew([
                'trip_variant_id' => $variant->id,
                'stable_key' => 'day-'.$dayNumber,
            ]);

            if (! $day->exists || $sync) {
                $day->fill([
                    'trip_id' => $trip->id,
                    'trip_variant_id' => $variant->id,
                    'stable_key' => 'day-'.$dayNumber,
                    'day_number' => $dayNumber,
                    'starts_on' => $date,
                    'ends_on' => $date,
                    'location' => $this->variantLocation($variant, $dayNumber, $location),
                    'title' => $this->variantTitle($variant, $dayNumber, $title),
                    'summary' => $this->variantSummary($variant, $dayNumber),
                    'node_types' => $nodeTypes,
                    'booking_priority' => $priority,
                    'booking_status' => 'unbooked',
                    'weather_class' => in_array('activity', $nodeTypes, true) ? 'mixed' : null,
                    'kid_energy_level' => $priority === 'high' ? 'medium' : 'low',
                    'luggage_complexity' => in_array('travel', $nodeTypes, true) ? 'medium' : 'low',
                    'transport_method' => $this->transportMethod($transportKeys),
                    'duration_label' => in_array('travel', $nodeTypes, true) ? 'travel day' : 'full day',
                    'cost_value_min_nok' => $valueCost[0],
                    'cost_value_max_nok' => $valueCost[1],
                    'cost_premium_min_nok' => $premiumCost[0],
                    'cost_premium_max_nok' => $premiumCost[1],
                    'details' => [
                        'rain_backup' => $this->rainBackup($location),
                        'variant_note' => $variant->description,
                    ],
                ])->save();
            }

            $this->attachMissingSources($day, $sourceKeys, $sources);
            $this->attachMissingAssets($day, $accommodationKeys, $transportKeys, $activityKeys, $foodKeys, $assets);
            $this->upsertDaySlots($trip, $variant, $day, $accommodationKeys, $transportKeys, $activityKeys, $foodKeys, $assets, $sync);
            $this->upsertDayTasks($trip, $variant, $day, $priority, $sync);
        }
    }

    private function variantLocation(TripVariant $variant, int $dayNumber, string $location): string
    {
        if ($variant->slug === 'premium-seoul-stopover' && $dayNumber >= 21) {
            return match ($dayNumber) {
                21 => 'Kyoto > Osaka',
                22 => 'Osaka > Seoul',
                23 => 'Seoul',
                24 => 'Seoul > Oslo',
                default => $location,
            };
        }

        if ($variant->slug === 'value-airport-connection' && $dayNumber <= 2) {
            return match ($dayNumber) {
                1 => 'Oslo > Tokyo',
                2 => 'Tokyo',
                default => $location,
            };
        }

        return $location;
    }

    private function variantTitle(TripVariant $variant, int $dayNumber, string $title): string
    {
        if ($variant->slug === 'premium-airport-connection' && in_array($dayNumber, [3, 12], true)) {
            return $dayNumber === 3 ? 'Premium arrival to Tokyo' : 'Premium Kyoto Station base';
        }

        if ($variant->slug === 'premium-seoul-stopover' && $dayNumber >= 21) {
            return match ($dayNumber) {
                21 => 'Position to Osaka',
                22 => 'Fly or connect to Seoul',
                23 => 'Seoul stopover day',
                24 => 'Fly home from Seoul',
                default => $title,
            };
        }

        return $title;
    }

    private function variantSummary(TripVariant $variant, int $dayNumber): string
    {
        if ($variant->slug === 'value-airport-connection' && $dayNumber <= 2) {
            return 'Variant overlay: no intentional Copenhagen stay; use these days as Tokyo/Kyoto buffer once flights are known.';
        }

        if ($variant->slug === 'premium-airport-connection') {
            return 'Variant overlay: premium hotel and open-jaw flight assumptions can be edited per day.';
        }

        if ($variant->slug === 'premium-seoul-stopover' && $dayNumber >= 21) {
            return 'Variant overlay: replace the final Tokyo return with Osaka/Kansai positioning and a Seoul stopover.';
        }

        return 'Reference day imported from the research context. Edit this copy freely; future imports will not overwrite it unless --sync-reference is used.';
    }

    /**
     * @param  array<int, string>  $transportKeys
     */
    private function transportMethod(array $transportKeys): ?string
    {
        if ($transportKeys === []) {
            return null;
        }

        return implode(' + ', $transportKeys);
    }

    private function rainBackup(string $location): string
    {
        return str_contains($location, 'Tokyo') || str_contains($location, 'Kyoto')
            ? 'Use station food halls, indoor malls, museums, or the next flex block.'
            : 'Keep the day light and avoid adding extra transfers.';
    }

    /**
     * @param  array<int, string>  $sourceKeys
     * @param  array<string, Source>  $sources
     */
    private function attachMissingSources(DayNode $day, array $sourceKeys, array $sources): void
    {
        foreach ($sourceKeys as $sourceKey) {
            if (! isset($sources[$sourceKey])) {
                continue;
            }

            if (! $day->sources()->whereKey($sources[$sourceKey]->id)->exists()) {
                $day->sources()->attach($sources[$sourceKey]->id);
            }
        }
    }

    /**
     * @param  array<int, string>  $accommodationKeys
     * @param  array<int, string>  $transportKeys
     * @param  array<int, string>  $activityKeys
     * @param  array<int, string>  $foodKeys
     * @param  array<string, array<string, mixed>>  $assets
     */
    private function attachMissingAssets(DayNode $day, array $accommodationKeys, array $transportKeys, array $activityKeys, array $foodKeys, array $assets): void
    {
        foreach ($accommodationKeys as $key) {
            $this->attachMissing($day->accommodations(), $assets['accommodations'][$key] ?? null, ['role' => 'overnight']);
        }

        foreach ($transportKeys as $sequence => $key) {
            $this->attachMissing($day->transportLegs(), $assets['transport_legs'][$key] ?? null, ['sequence' => $sequence + 1]);
        }

        foreach ($activityKeys as $sequence => $key) {
            $this->attachMissing($day->activities(), $assets['activities'][$key] ?? null, ['sequence' => $sequence + 1]);
        }

        foreach ($foodKeys as $sequence => $key) {
            $this->attachMissing($day->foodSpots(), $assets['food_spots'][$key] ?? null, ['sequence' => $sequence + 1]);
        }
    }

    private function attachMissing(mixed $relation, mixed $model, array $pivot = []): void
    {
        if ($model === null) {
            return;
        }

        if (! $relation->whereKey($model->id)->exists()) {
            $relation->attach($model->id, $pivot);
        }
    }

    /**
     * @param  array<int, string>  $accommodationKeys
     * @param  array<int, string>  $transportKeys
     * @param  array<int, string>  $activityKeys
     * @param  array<int, string>  $foodKeys
     * @param  array<string, array<string, mixed>>  $assets
     */
    private function upsertDaySlots(Trip $trip, TripVariant $variant, DayNode $day, array $accommodationKeys, array $transportKeys, array $activityKeys, array $foodKeys, array $assets, bool $sync): void
    {
        $slots = collect();

        foreach ($accommodationKeys as $key) {
            $slots->push($this->slotPayload($trip, $variant, $day, 'stay', $assets['accommodations'][$key] ?? null, 'Stay base', 'Hotel or overnight base for this day.'));
        }

        foreach ($transportKeys as $key) {
            $slots->push($this->slotPayload($trip, $variant, $day, 'move', $assets['transport_legs'][$key] ?? null, 'Move', 'Main transport anchor for the day.'));
        }

        foreach ($activityKeys as $key) {
            $slots->push($this->slotPayload($trip, $variant, $day, 'activity', $assets['activities'][$key] ?? null, 'Main activity', 'Primary traveler-facing activity slot.'));
        }

        foreach ($foodKeys as $key) {
            $slots->push($this->slotPayload($trip, $variant, $day, 'food', $assets['food_spots'][$key] ?? null, 'Food plan', 'Meal or fallback food slot.'));
        }

        if ($slots->isEmpty()) {
            $slots->push([
                'trip_id' => $trip->id,
                'trip_variant_id' => $variant->id,
                'day_node_id' => $day->id,
                'stable_key' => 'day-slot-buffer',
                'item_type' => 'buffer',
                'time_label' => null,
                'title' => 'Flexible buffer',
                'summary' => 'Keep this part of the day open.',
                'location_label' => $day->location,
                'is_public' => true,
                'sort_order' => 10,
                'details' => [],
            ]);
        }

        $slots->values()->each(function (array $payload, int $index) use ($day, $sync): void {
            $payload['sort_order'] = ($index + 1) * 10;

            $slot = DayItineraryItem::firstOrNew([
                'day_node_id' => $day->id,
                'stable_key' => $payload['stable_key'],
            ]);

            if (! $slot->exists || $sync) {
                $slot->fill($payload)->save();
            }
        });
    }

    private function slotPayload(Trip $trip, TripVariant $variant, DayNode $day, string $type, mixed $subject, string $fallbackTitle, string $summary): array
    {
        $title = $subject?->name ?? $subject?->route_label ?? $fallbackTitle;
        $location = collect([$subject?->neighborhood, $subject?->area, $subject?->city, $subject?->origin])->filter()->join(' · ');

        return [
            'trip_id' => $trip->id,
            'trip_variant_id' => $variant->id,
            'day_node_id' => $day->id,
            'stable_key' => 'day-slot-'.$type.'-'.($subject?->stable_key ?? str($title)->slug()),
            'item_type' => $type,
            'time_label' => null,
            'title' => $title,
            'summary' => $summary,
            'location_label' => $location ?: $day->location,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->id,
            'latitude' => $subject?->latitude,
            'longitude' => $subject?->longitude,
            'is_public' => true,
            'sort_order' => 10,
            'details' => [],
        ];
    }

    private function upsertDayTasks(Trip $trip, TripVariant $variant, DayNode $day, string $priority, bool $sync): void
    {
        $task = DayTask::firstOrNew([
            'day_node_id' => $day->id,
            'stable_key' => 'confirm-day-plan',
        ]);

        if (! $task->exists || $sync) {
            $task->fill([
                'trip_id' => $trip->id,
                'trip_variant_id' => $variant->id,
                'day_node_id' => $day->id,
                'stable_key' => 'confirm-day-plan',
                'task_type' => 'fix',
                'title' => 'Confirm day slots and movement timing',
                'notes' => 'Adjust only the slots that need a real departure or booking time.',
                'status' => 'open',
                'priority' => $priority === 'high' ? 'high' : 'medium',
                'details' => [],
            ])->save();
        }
    }

    private function upsertRoutePoints(TripVariant $variant, bool $sync): void
    {
        foreach (JapanTripReference::routePoints() as $data) {
            $point = RoutePoint::firstOrNew([
                'trip_variant_id' => $variant->id,
                'stable_key' => $data['stable_key'],
            ]);

            if (! $point->exists || $sync) {
                $point->fill($data + ['trip_variant_id' => $variant->id])->save();
            }
        }
    }
}
