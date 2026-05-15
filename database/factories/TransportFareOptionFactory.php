<?php

namespace Database\Factories;

use App\Models\TransportFareOption;
use App\Models\TransportLeg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportFareOption>
 */
class TransportFareOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transport_leg_id' => TransportLeg::factory(),
            'label' => fake()->randomElement(['SAS Premium cash', 'EuroBonus 2-for-1 award', 'Discounted Business']),
            'fare_type' => fake()->randomElement(['cash', 'award', 'cash_upgrade', 'status_run']),
            'cabin' => fake()->randomElement(['economy', 'premium_economy', 'business']),
            'carrier' => 'SAS',
            'passengers' => 2,
            'cash_min_nok' => 12000,
            'cash_max_nok' => 18000,
            'cash_min_jpy' => 170000,
            'cash_max_jpy' => 255000,
            'points_min' => null,
            'points_max' => null,
            'taxes_fees_min_nok' => null,
            'taxes_fees_max_nok' => null,
            'voucher_count' => 0,
            'expected_level_points' => 12000,
            'expected_bonus_points' => 3000,
            'travel_dates' => 'Summer 2027',
            'observed_at' => now()->toDateString(),
            'fresh_until' => now()->addDays(14)->toDateString(),
            'source_priority' => 'secondary',
            'source_url' => 'https://example.test/flights',
            'status' => 'candidate',
            'notes' => fake()->sentence(),
        ];
    }
}
