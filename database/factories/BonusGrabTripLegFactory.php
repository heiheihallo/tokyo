<?php

namespace Database\Factories;

use App\Models\BonusGrabTrip;
use App\Models\BonusGrabTripLeg;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BonusGrabTripLeg>
 */
class BonusGrabTripLegFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bonus_grab_trip_id' => BonusGrabTrip::factory(),
            'sequence' => 1,
            'origin' => 'OSL',
            'destination' => 'CPH',
            'carrier' => 'SAS',
            'flight_number' => 'SK000',
            'cabin' => 'premium_economy',
            'departs_at' => null,
            'arrives_at' => null,
            'expected_bonus_points' => 500,
            'expected_level_points' => 1000,
        ];
    }
}
