<?php

namespace Database\Factories;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->words(3, true),
            'summary' => fake()->sentence(),
            'starts_on' => fake()->dateTimeBetween('+1 month', '+2 months')->format('Y-m-d'),
            'ends_on' => fake()->dateTimeBetween('+2 months', '+3 months')->format('Y-m-d'),
            'currency_primary' => 'NOK',
            'currency_secondary' => 'JPY',
            'arrival_preference' => 'HND',
            'is_public' => false,
            'published_at' => null,
            'metadata' => [],
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
