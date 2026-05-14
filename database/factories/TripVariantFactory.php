<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripVariant>
 */
class TripVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->words(3, true),
            'budget_scenario' => fake()->randomElement(['value', 'premium']),
            'stopover_type' => fake()->randomElement(['connection', 'copenhagen', 'seoul']),
            'flight_strategy' => fake()->sentence(),
            'description' => fake()->sentence(),
            'is_default' => false,
            'is_public' => false,
            'published_at' => null,
            'sort_order' => fake()->numberBetween(1, 100),
            'overrides' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
            'published_at' => now(),
        ]);
    }
}
