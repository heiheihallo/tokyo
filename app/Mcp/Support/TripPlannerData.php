<?php

namespace App\Mcp\Support;

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\AwardAvailabilityCheck;
use App\Models\BonusGrabTrip;
use App\Models\BonusGrabTripLeg;
use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\DayTask;
use App\Models\FoodSpot;
use App\Models\LoyaltyProgramSnapshot;
use App\Models\LoyaltyVoucher;
use App\Models\RoutePoint;
use App\Models\Source;
use App\Models\TransportFareOption;
use App\Models\TransportLeg;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TripPlannerData
{
    /**
     * @return array<string, mixed>
     */
    public function trip(Trip $trip, bool $includeVariants = true): array
    {
        $trip->loadMissing($includeVariants ? ['variants', 'loyaltyProgramSnapshots.vouchers', 'loyaltyProgramSnapshots.bonusGrabTrips.legs'] : ['loyaltyProgramSnapshots.vouchers', 'loyaltyProgramSnapshots.bonusGrabTrips.legs']);

        return [
            'id' => $trip->id,
            'slug' => $trip->slug,
            'name' => $trip->name,
            'summary' => $trip->summary,
            'starts_on' => $trip->starts_on?->toDateString(),
            'ends_on' => $trip->ends_on?->toDateString(),
            'currency_primary' => $trip->currency_primary,
            'currency_secondary' => $trip->currency_secondary,
            'arrival_preference' => $trip->arrival_preference,
            'is_public' => $trip->is_public,
            'published_at' => $trip->published_at?->toIso8601String(),
            'metadata' => $trip->metadata ?? [],
            'loyalty_programs' => $trip->loyaltyProgramSnapshots->map(fn (LoyaltyProgramSnapshot $snapshot): array => $this->loyaltyProgramSnapshot($snapshot))->all(),
            'variants' => $includeVariants
                ? $trip->variants->map(fn (TripVariant $variant): array => $this->variant($variant, includeCounts: true))->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function variant(TripVariant $variant, bool $includeCounts = false): array
    {
        $data = [
            'id' => $variant->id,
            'trip_id' => $variant->trip_id,
            'slug' => $variant->slug,
            'name' => $variant->name,
            'budget_scenario' => $variant->budget_scenario,
            'stopover_type' => $variant->stopover_type,
            'flight_strategy' => $variant->flight_strategy,
            'description' => $variant->description,
            'is_default' => $variant->is_default,
            'is_public' => $variant->is_public,
            'published_at' => $variant->published_at?->toIso8601String(),
            'sort_order' => $variant->sort_order,
            'overrides' => $variant->overrides ?? [],
        ];

        if ($includeCounts) {
            $variant->loadCount(['dayNodes', 'routePoints']);
            $data['day_nodes_count'] = $variant->day_nodes_count;
            $data['route_points_count'] = $variant->route_points_count;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function day(DayNode $day, bool $includeRelations = true): array
    {
        if ($includeRelations) {
            $day->loadMissing([
                'accommodations',
                'transportLegs',
                'activities',
                'foodSpots',
                'sources',
                'itineraryItems.subject',
                'tasks',
            ]);
        }

        return [
            'id' => $day->id,
            'trip_id' => $day->trip_id,
            'trip_variant_id' => $day->trip_variant_id,
            'stable_key' => $day->stable_key,
            'day_number' => $day->day_number,
            'starts_on' => $day->starts_on?->toDateString(),
            'ends_on' => $day->ends_on?->toDateString(),
            'location' => $day->location,
            'title' => $day->title,
            'summary' => $day->summary,
            'node_types' => $day->node_types,
            'booking_priority' => $day->booking_priority,
            'booking_status' => $day->booking_status,
            'weather_class' => $day->weather_class,
            'kid_energy_level' => $day->kid_energy_level,
            'luggage_complexity' => $day->luggage_complexity,
            'transport_method' => $day->transport_method,
            'duration_label' => $day->duration_label,
            'costs' => [
                'value_nok' => [$day->cost_value_min_nok, $day->cost_value_max_nok],
                'premium_nok' => [$day->cost_premium_min_nok, $day->cost_premium_max_nok],
                'value_jpy' => [$day->cost_value_min_jpy, $day->cost_value_max_jpy],
                'premium_jpy' => [$day->cost_premium_min_jpy, $day->cost_premium_max_jpy],
            ],
            'reservation_url' => $day->reservation_url,
            'cancellation_window_at' => $day->cancellation_window_at?->toIso8601String(),
            'ics_exportable' => $day->ics_exportable,
            'details' => $day->details ?? [],
            'accommodations' => $includeRelations ? $day->accommodations->map(fn (Accommodation $asset): array => $this->asset($asset))->all() : [],
            'transport_legs' => $includeRelations ? $day->transportLegs->map(fn (TransportLeg $asset): array => $this->asset($asset))->all() : [],
            'activities' => $includeRelations ? $day->activities->map(fn (Activity $asset): array => $this->asset($asset))->all() : [],
            'food_spots' => $includeRelations ? $day->foodSpots->map(fn (FoodSpot $asset): array => $this->asset($asset))->all() : [],
            'sources' => $includeRelations ? $day->sources->map(fn (Source $source): array => $this->source($source))->all() : [],
            'itinerary_items' => $includeRelations ? $day->itineraryItems->map(fn (DayItineraryItem $item): array => $this->slot($item))->all() : [],
            'tasks' => $includeRelations ? $day->tasks->map(fn (DayTask $task): array => $this->task($task))->all() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function slot(DayItineraryItem $item): array
    {
        return [
            'id' => $item->id,
            'stable_key' => $item->stable_key,
            'item_type' => $item->item_type,
            'starts_at' => $item->starts_at?->format('H:i'),
            'ends_at' => $item->ends_at?->format('H:i'),
            'time_label' => $item->time_label,
            'title' => $item->title,
            'summary' => $item->summary,
            'location_label' => $item->location_label,
            'subject_type' => $item->subject_type,
            'subject_id' => $item->subject_id,
            'latitude' => $item->latitude,
            'longitude' => $item->longitude,
            'is_public' => $item->is_public,
            'sort_order' => $item->sort_order,
            'details' => $item->details ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function task(DayTask $task): array
    {
        return [
            'id' => $task->id,
            'stable_key' => $task->stable_key,
            'task_type' => $task->task_type,
            'title' => $task->title,
            'notes' => $task->notes,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_on' => $task->due_on?->toDateString(),
            'details' => $task->details ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asset(Accommodation|Activity|FoodSpot|TransportLeg $asset): array
    {
        if ($asset instanceof TransportLeg) {
            $asset->loadMissing('fareOptions.awardAvailabilityChecks');
        }

        return [
            'id' => $asset->id,
            'type' => class_basename($asset),
            'stable_key' => $asset->stable_key,
            'name' => $asset->name ?? $asset->route_label,
            'city' => $asset->city ?? null,
            'country' => $asset->country ?? null,
            'area' => $asset->area ?? null,
            'neighborhood' => $asset->neighborhood ?? null,
            'mode' => $asset->mode ?? null,
            'route_label' => $asset->route_label ?? null,
            'origin' => $asset->origin ?? null,
            'destination' => $asset->destination ?? null,
            'duration_label' => $asset->duration_label ?? null,
            'operator' => $asset->operator ?? null,
            'reservation_url' => $asset->reservation_url ?? null,
            'latitude' => $asset->latitude ?? null,
            'longitude' => $asset->longitude ?? null,
            'price' => [
                'min_nok' => $asset->price_min_nok,
                'max_nok' => $asset->price_max_nok,
                'min_jpy' => $asset->price_min_jpy,
                'max_jpy' => $asset->price_max_jpy,
                'basis' => $asset->price_basis,
                'notes' => $asset->price_notes,
            ],
            'fare_options' => $asset instanceof TransportLeg
                ? $asset->fareOptions->map(fn (TransportFareOption $option): array => $this->fareOption($option))->all()
                : [],
            'media' => $asset instanceof HasMedia ? $this->media($asset) : [],
            'notes' => $asset->notes ?? null,
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function media(HasMedia $model): array
    {
        return collect(['main_image', 'images'])
            ->mapWithKeys(fn (string $collection): array => [
                $collection => $model->getMedia($collection)
                    ->map(fn (Media $media): array => [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
                        'order_column' => $media->order_column,
                        'url' => $media->getUrl(),
                        'conversions' => [
                            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : null,
                            'card' => $media->hasGeneratedConversion('card') ? $media->getUrl('card') : null,
                            'hero' => $media->hasGeneratedConversion('hero') ? $media->getUrl('hero') : null,
                        ],
                        'custom_properties' => $media->custom_properties,
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function source(Source $source): array
    {
        return [
            'id' => $source->id,
            'source_key' => $source->source_key,
            'title' => $source->title,
            'source_type' => $source->source_type,
            'authority' => $source->authority,
            'url' => $source->url,
            'notes' => $source->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function routePoint(RoutePoint $point): array
    {
        return [
            'id' => $point->id,
            'stable_key' => $point->stable_key,
            'name' => $point->name,
            'category' => $point->category,
            'latitude' => $point->latitude,
            'longitude' => $point->longitude,
            'sequence' => $point->sequence,
            'route_group' => $point->route_group,
            'external_url' => $point->external_url,
            'notes' => $point->notes,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function assets(string $type, ?string $query = null): Collection
    {
        $model = $this->assetModel($type);
        $nameColumn = $model === TransportLeg::class ? 'route_label' : 'name';

        return $model::query()
            ->when($query, fn ($builder) => $builder->where($nameColumn, 'like', '%'.$query.'%'))
            ->orderBy($nameColumn)
            ->limit(25)
            ->get()
            ->map(fn ($asset): array => $this->asset($asset));
    }

    /**
     * @return class-string<Accommodation|Activity|FoodSpot|TransportLeg>
     */
    public function assetModel(string $type): string
    {
        return match ($type) {
            'activity', 'activities' => Activity::class,
            'food', 'food_spot', 'food_spots' => FoodSpot::class,
            'transport', 'transport_leg', 'transport_legs' => TransportLeg::class,
            default => Accommodation::class,
        };
    }

    /**
     * @return EloquentCollection<int, DayNode>
     */
    public function timeline(TripVariant $variant): EloquentCollection
    {
        return $variant->dayNodes()
            ->with(['accommodations', 'transportLegs', 'activities', 'foodSpots', 'sources'])
            ->orderBy('day_number')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function loyaltyProgramSnapshot(LoyaltyProgramSnapshot $snapshot): array
    {
        $snapshot->loadMissing(['vouchers', 'bonusGrabTrips.legs']);

        return [
            'id' => $snapshot->id,
            'trip_id' => $snapshot->trip_id,
            'program_name' => $snapshot->program_name,
            'current_points' => $snapshot->current_points,
            'current_level_points' => $snapshot->current_level_points,
            'qualification_starts_on' => $snapshot->qualification_starts_on?->toDateString(),
            'qualification_ends_on' => $snapshot->qualification_ends_on?->toDateString(),
            'target_tier' => $snapshot->target_tier,
            'target_level_points' => $snapshot->target_level_points,
            'target_qualifying_flights' => $snapshot->target_qualifying_flights,
            'expected_trip_level_points' => $snapshot->expected_trip_level_points,
            'signup_bonus_points' => $snapshot->signup_bonus_points,
            'card_spend_target_nok' => $snapshot->card_spend_target_nok,
            'card_points_per_100_nok' => $snapshot->card_points_per_100_nok,
            'card_level_points_per_100_nok' => $snapshot->card_level_points_per_100_nok,
            'projected_card_points' => $snapshot->projected_card_points,
            'projected_card_level_points' => $snapshot->projected_card_level_points,
            'assumptions' => $snapshot->assumptions ?? [],
            'notes' => $snapshot->notes,
            'vouchers' => $snapshot->vouchers->map(fn (LoyaltyVoucher $voucher): array => $this->loyaltyVoucher($voucher))->all(),
            'bonus_grab_trips' => $snapshot->bonusGrabTrips->map(fn (BonusGrabTrip $trip): array => $this->bonusGrabTrip($trip))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function loyaltyVoucher(LoyaltyVoucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_type' => $voucher->voucher_type,
            'status' => $voucher->status,
            'quantity' => $voucher->quantity,
            'earned_threshold_nok' => $voucher->earned_threshold_nok,
            'valid_from' => $voucher->valid_from?->toDateString(),
            'valid_until' => $voucher->valid_until?->toDateString(),
            'notes' => $voucher->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fareOption(TransportFareOption $option): array
    {
        $option->loadMissing('awardAvailabilityChecks');

        return [
            'id' => $option->id,
            'transport_leg_id' => $option->transport_leg_id,
            'label' => $option->label,
            'fare_type' => $option->fare_type,
            'cabin' => $option->cabin,
            'carrier' => $option->carrier,
            'passengers' => $option->passengers,
            'cash_min_nok' => $option->cash_min_nok,
            'cash_max_nok' => $option->cash_max_nok,
            'cash_min_jpy' => $option->cash_min_jpy,
            'cash_max_jpy' => $option->cash_max_jpy,
            'points_min' => $option->points_min,
            'points_max' => $option->points_max,
            'taxes_fees_min_nok' => $option->taxes_fees_min_nok,
            'taxes_fees_max_nok' => $option->taxes_fees_max_nok,
            'voucher_count' => $option->voucher_count,
            'expected_level_points' => $option->expected_level_points,
            'expected_bonus_points' => $option->expected_bonus_points,
            'travel_dates' => $option->travel_dates,
            'observed_at' => $option->observed_at?->toDateString(),
            'fresh_until' => $option->fresh_until?->toDateString(),
            'source_priority' => $option->source_priority,
            'source_url' => $option->source_url,
            'status' => $option->status,
            'notes' => $option->notes,
            'award_availability_checks' => $option->awardAvailabilityChecks->map(fn (AwardAvailabilityCheck $check): array => $this->awardAvailabilityCheck($check))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function awardAvailabilityCheck(AwardAvailabilityCheck $check): array
    {
        return [
            'id' => $check->id,
            'checked_on' => $check->checked_on?->toDateString(),
            'route_label' => $check->route_label,
            'travel_dates' => $check->travel_dates,
            'cabin' => $check->cabin,
            'seats_seen' => $check->seats_seen,
            'availability_status' => $check->availability_status,
            'source_url' => $check->source_url,
            'notes' => $check->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bonusGrabTrip(BonusGrabTrip $trip): array
    {
        $trip->loadMissing('legs');

        return [
            'id' => $trip->id,
            'title' => $trip->title,
            'route_label' => $trip->route_label,
            'starts_on' => $trip->starts_on?->toDateString(),
            'ends_on' => $trip->ends_on?->toDateString(),
            'cash_cost_min_nok' => $trip->cash_cost_min_nok,
            'cash_cost_max_nok' => $trip->cash_cost_max_nok,
            'expected_bonus_points' => $trip->expected_bonus_points,
            'expected_level_points' => $trip->expected_level_points,
            'nights_away' => $trip->nights_away,
            'cabin' => $trip->cabin,
            'feasibility_score' => $trip->feasibility_score,
            'status' => $trip->status,
            'source_url' => $trip->source_url,
            'notes' => $trip->notes,
            'legs' => $trip->legs->map(fn (BonusGrabTripLeg $leg): array => $this->bonusGrabTripLeg($leg))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bonusGrabTripLeg(BonusGrabTripLeg $leg): array
    {
        return [
            'id' => $leg->id,
            'sequence' => $leg->sequence,
            'origin' => $leg->origin,
            'destination' => $leg->destination,
            'carrier' => $leg->carrier,
            'flight_number' => $leg->flight_number,
            'cabin' => $leg->cabin,
            'departs_at' => $leg->departs_at?->toIso8601String(),
            'arrives_at' => $leg->arrives_at?->toIso8601String(),
            'expected_bonus_points' => $leg->expected_bonus_points,
            'expected_level_points' => $leg->expected_level_points,
        ];
    }
}
