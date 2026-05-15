<?php

namespace App\Mcp\Support;

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
        $trip->loadMissing($includeVariants ? ['variants'] : []);

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
}
