<?php

namespace Database\Factories;

use App\Models\FoodSpot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodSpot>
 */
class FoodSpotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stable_key' => fake()->unique()->slug(3),
            'name' => fake()->company(),
            'area' => fake()->streetName(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'default_meal_type' => 'lunch',
            'fallback_type' => 'casual',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'price_min_nok' => 120,
            'price_max_nok' => 220,
            'price_min_jpy' => 1700,
            'price_max_jpy' => 3100,
            'price_basis' => 'per_meal',
            'notes' => fake()->sentence(),
        ];
    }
}
