<?php

namespace Database\Factories;

use App\Models\LoyaltyProgramSnapshot;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyProgramSnapshot>
 */
class LoyaltyProgramSnapshotFactory extends Factory
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
            'program_name' => 'EuroBonus',
            'current_points' => 8899,
            'current_level_points' => 200,
            'qualification_starts_on' => '2026-04-01',
            'qualification_ends_on' => '2027-03-31',
            'target_tier' => 'Gold',
            'target_level_points' => 45000,
            'target_qualifying_flights' => 45,
            'expected_trip_level_points' => 4000,
            'signup_bonus_points' => 15000,
            'card_spend_target_nok' => 300000,
            'card_points_per_100_nok' => 20,
            'card_level_points_per_100_nok' => 6,
            'projected_card_points' => 60000,
            'projected_card_level_points' => 18000,
            'assumptions' => [
                'card' => 'SAS Amex Elite',
                'voucher_thresholds_nok' => [150000, 300000],
            ],
            'notes' => fake()->sentence(),
        ];
    }
}
