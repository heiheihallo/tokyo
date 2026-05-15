<?php

namespace Database\Factories;

use App\Models\BonusGrabTrip;
use App\Models\LoyaltyProgramSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BonusGrabTrip>
 */
class BonusGrabTripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loyalty_program_snapshot_id' => LoyaltyProgramSnapshot::factory(),
            'title' => 'SAS Premium point grabber',
            'route_label' => 'OSL-CPH-TYO-CPH-OSL',
            'starts_on' => '2027-02-10',
            'ends_on' => '2027-02-12',
            'cash_cost_min_nok' => 12000,
            'cash_cost_max_nok' => 16000,
            'expected_bonus_points' => 4000,
            'expected_level_points' => 13500,
            'nights_away' => 2,
            'cabin' => 'premium_economy',
            'feasibility_score' => 75,
            'status' => 'candidate',
            'source_url' => 'https://example.test/status-run',
            'notes' => fake()->sentence(),
        ];
    }
}
