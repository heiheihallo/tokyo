<?php

namespace Database\Factories;

use App\Models\DayNode;
use App\Models\DayTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DayTask>
 */
class DayTaskFactory extends Factory
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
            'stable_key' => 'task-'.fake()->unique()->numberBetween(1, 9999),
            'task_type' => 'todo',
            'title' => fake()->sentence(4),
            'status' => 'open',
            'priority' => 'medium',
            'details' => [],
        ];
    }
}
