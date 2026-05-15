<?php

namespace Database\Factories;

use App\Models\TransportLeg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportLeg>
 */
class TransportLegFactory extends Factory
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
            'mode' => 'rail',
            'route_label' => fake()->city().' to '.fake()->city(),
            'duration_label' => fake()->numberBetween(30, 180).' min',
            'operator' => fake()->company(),
            'origin' => fake()->city(),
            'destination' => fake()->city(),
            'geo_path' => [],
            'price_min_nok' => 300,
            'price_max_nok' => 500,
            'price_min_jpy' => 4200,
            'price_max_jpy' => 7000,
            'price_basis' => 'per_person',
            'notes' => fake()->sentence(),
        ];
    }
}
