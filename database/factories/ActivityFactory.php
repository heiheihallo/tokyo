<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
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
            'name' => fake()->words(3, true),
            'area' => fake()->streetName(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'rain_fit' => 'mixed',
            'age_fit' => 'family',
            'prebooking_status' => 'not_needed',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'price_min_nok' => 200,
            'price_max_nok' => 350,
            'price_min_jpy' => 2800,
            'price_max_jpy' => 4900,
            'price_basis' => 'per_ticket',
            'notes' => fake()->sentence(),
        ];
    }
}
