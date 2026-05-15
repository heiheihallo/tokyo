<?php

namespace App\Models;

use Database\Factories\LoyaltyProgramSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgramSnapshot extends Model
{
    /** @use HasFactory<LoyaltyProgramSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'program_name',
        'current_points',
        'current_level_points',
        'qualification_starts_on',
        'qualification_ends_on',
        'target_tier',
        'target_level_points',
        'target_qualifying_flights',
        'expected_trip_level_points',
        'signup_bonus_points',
        'card_spend_target_nok',
        'card_points_per_100_nok',
        'card_level_points_per_100_nok',
        'projected_card_points',
        'projected_card_level_points',
        'assumptions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_points' => 'integer',
            'current_level_points' => 'integer',
            'qualification_starts_on' => 'date',
            'qualification_ends_on' => 'date',
            'target_level_points' => 'integer',
            'target_qualifying_flights' => 'integer',
            'expected_trip_level_points' => 'integer',
            'signup_bonus_points' => 'integer',
            'card_spend_target_nok' => 'integer',
            'card_points_per_100_nok' => 'integer',
            'card_level_points_per_100_nok' => 'integer',
            'projected_card_points' => 'integer',
            'projected_card_level_points' => 'integer',
            'assumptions' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(LoyaltyVoucher::class);
    }

    public function bonusGrabTrips(): HasMany
    {
        return $this->hasMany(BonusGrabTrip::class);
    }
}
