<?php

namespace Database\Factories;

use App\Models\DayItineraryItem;
use App\Models\DayNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DayItineraryItem>
 */
class DayItineraryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => fn (array $attributes) => DayNode::find($attributes['day_node_id'])->trip_id,
            'trip_variant_id' => fn (array $attributes) => DayNode::find($attributes['day_node_id'])->trip_variant_id,
            'day_node_id' => DayNode::factory(),
            'stable_key' => 'item-'.fake()->unique()->numberBetween(1, 9999),
            'item_type' => 'activity',
            'time_label' => fake()->time('H:i'),
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'is_public' => true,
            'sort_order' => fake()->numberBetween(10, 90),
            'details' => [],
        ];
    }
}
