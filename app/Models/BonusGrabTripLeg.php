<?php

namespace App\Models;

use Database\Factories\BonusGrabTripLegFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusGrabTripLeg extends Model
{
    /** @use HasFactory<BonusGrabTripLegFactory> */
    use HasFactory;

    protected $fillable = [
        'bonus_grab_trip_id',
        'sequence',
        'origin',
        'destination',
        'carrier',
        'flight_number',
        'cabin',
        'departs_at',
        'arrives_at',
        'expected_bonus_points',
        'expected_level_points',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'departs_at' => 'datetime',
            'arrives_at' => 'datetime',
            'expected_bonus_points' => 'integer',
            'expected_level_points' => 'integer',
        ];
    }

    public function bonusGrabTrip(): BelongsTo
    {
        return $this->belongsTo(BonusGrabTrip::class);
    }
}
