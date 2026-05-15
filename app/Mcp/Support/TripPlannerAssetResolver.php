<?php

namespace App\Mcp\Support;

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\FoodSpot;
use App\Models\TransportLeg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

class TripPlannerAssetResolver
{
    /**
     * @return class-string<Accommodation|Activity|FoodSpot|TransportLeg>
     */
    public function modelFor(string $type): string
    {
        return match ($type) {
            'accommodation', 'accommodations' => Accommodation::class,
            'activity', 'activities' => Activity::class,
            'food', 'food_spot', 'food_spots' => FoodSpot::class,
            'transport', 'transport_leg', 'transport_legs' => TransportLeg::class,
            default => throw new InvalidArgumentException('Unsupported asset type: '.$type),
        };
    }

    public function find(string $type, ?int $id = null, ?string $stableKey = null): Accommodation|Activity|FoodSpot|TransportLeg
    {
        $model = $this->modelFor($type);

        return $model::query()
            ->when($id !== null, fn ($query) => $query->whereKey($id))
            ->when($stableKey !== null, fn ($query) => $query->where('stable_key', $stableKey))
            ->firstOrFail();
    }

    /**
     * @return BelongsToMany<Accommodation|Activity|FoodSpot|TransportLeg, DayNode>
     */
    public function relationForDay(DayNode $day, string $type): BelongsToMany
    {
        return match ($type) {
            'transport', 'transport_leg', 'transport_legs' => $day->transportLegs(),
            'activity', 'activities' => $day->activities(),
            'food', 'food_spot', 'food_spots' => $day->foodSpots(),
            default => $day->accommodations(),
        };
    }

    /**
     * @return array{day_nodes_count: int, slots_count: int, day_nodes: array<int, array<string, mixed>>, slots: array<int, array<string, mixed>>}
     */
    public function usage(Model $asset): array
    {
        $dayNodes = $asset->dayNodes()
            ->select(['day_nodes.id', 'day_nodes.stable_key', 'day_nodes.day_number', 'day_nodes.title'])
            ->orderBy('day_number')
            ->get()
            ->map(fn (DayNode $day): array => [
                'id' => $day->id,
                'stable_key' => $day->stable_key,
                'day_number' => $day->day_number,
                'title' => $day->title,
            ])
            ->all();

        $slots = DayItineraryItem::query()
            ->where('subject_type', $asset::class)
            ->where('subject_id', $asset->getKey())
            ->orderBy('day_node_id')
            ->orderBy('sort_order')
            ->get(['id', 'day_node_id', 'stable_key', 'title', 'sort_order'])
            ->map(fn (DayItineraryItem $slot): array => [
                'id' => $slot->id,
                'day_node_id' => $slot->day_node_id,
                'stable_key' => $slot->stable_key,
                'title' => $slot->title,
                'sort_order' => $slot->sort_order,
            ])
            ->all();

        return [
            'day_nodes_count' => count($dayNodes),
            'slots_count' => count($slots),
            'day_nodes' => $dayNodes,
            'slots' => $slots,
        ];
    }
}
