<?php

namespace Database\Factories;

use App\Models\LoyaltyProgramSnapshot;
use App\Models\LoyaltyVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyVoucher>
 */
class LoyaltyVoucherFactory extends Factory
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
            'voucher_type' => '2-for-1',
            'status' => 'expected',
            'quantity' => 1,
            'earned_threshold_nok' => 150000,
            'valid_from' => null,
            'valid_until' => null,
            'notes' => fake()->sentence(),
        ];
    }
}
