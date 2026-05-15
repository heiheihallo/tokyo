<?php

namespace Database\Factories;

use App\Models\AwardAvailabilityCheck;
use App\Models\TransportFareOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AwardAvailabilityCheck>
 */
class AwardAvailabilityCheckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transport_fare_option_id' => TransportFareOption::factory(),
            'checked_on' => now()->toDateString(),
            'route_label' => 'OSL-CPH-HND',
            'travel_dates' => 'June 2027',
            'cabin' => 'business',
            'seats_seen' => 2,
            'availability_status' => 'available',
            'source_url' => 'https://example.test/awards',
            'notes' => fake()->sentence(),
        ];
    }
}
