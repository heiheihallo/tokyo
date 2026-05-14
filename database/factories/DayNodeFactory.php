<?php

namespace Database\Factories;

use App\Models\DayNode;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DayNode>
 */
class DayNodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $trip = Trip::factory();

        return [
            'trip_id' => $trip,
            'trip_variant_id' => TripVariant::factory()->for($trip),
            'stable_key' => 'day-'.fake()->unique()->numberBetween(1, 9999),
            'day_number' => fake()->numberBetween(1, 30),
            'starts_on' => fake()->dateTimeBetween('+1 month', '+2 months')->format('Y-m-d'),
            'ends_on' => fake()->dateTimeBetween('+1 month', '+2 months')->format('Y-m-d'),
            'location' => fake()->city(),
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'node_types' => ['stay'],
            'booking_priority' => 'low',
            'booking_status' => 'unbooked',
            'weather_class' => 'mixed',
            'kid_energy_level' => 'low',
            'luggage_complexity' => 'low',
            'cost_value_min_nok' => 1000,
            'cost_value_max_nok' => 2000,
            'cost_premium_min_nok' => 3000,
            'cost_premium_max_nok' => 5000,
            'ics_exportable' => false,
            'details' => [],
        ];
    }
}
