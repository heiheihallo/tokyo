<?php

namespace Database\Factories;

use App\Models\Accommodation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Accommodation>
 */
class AccommodationFactory extends Factory
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
            'name' => fake()->company().' Hotel',
            'city' => fake()->city(),
            'country' => fake()->country(),
            'neighborhood' => fake()->streetName(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'price_min_nok' => 1000,
            'price_max_nok' => 1500,
            'price_min_jpy' => 14000,
            'price_max_jpy' => 21000,
            'price_basis' => 'per_night',
            'notes' => fake()->sentence(),
        ];
    }
}
