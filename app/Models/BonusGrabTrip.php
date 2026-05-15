<?php

namespace App\Models;

use Database\Factories\BonusGrabTripFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonusGrabTrip extends Model
{
    /** @use HasFactory<BonusGrabTripFactory> */
    use HasFactory;

    protected $fillable = [
        'loyalty_program_snapshot_id',
        'title',
        'route_label',
        'starts_on',
        'ends_on',
        'cash_cost_min_nok',
        'cash_cost_max_nok',
        'expected_bonus_points',
        'expected_level_points',
        'nights_away',
        'cabin',
        'feasibility_score',
        'status',
        'source_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cash_cost_min_nok' => 'integer',
            'cash_cost_max_nok' => 'integer',
            'expected_bonus_points' => 'integer',
            'expected_level_points' => 'integer',
            'nights_away' => 'integer',
            'feasibility_score' => 'integer',
        ];
    }

    public function loyaltyProgramSnapshot(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgramSnapshot::class);
    }

    public function legs(): HasMany
    {
        return $this->hasMany(BonusGrabTripLeg::class)->orderBy('sequence');
    }
}
