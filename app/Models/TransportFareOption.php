<?php

namespace App\Models;

use Database\Factories\TransportFareOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportFareOption extends Model
{
    /** @use HasFactory<TransportFareOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'transport_leg_id',
        'label',
        'fare_type',
        'cabin',
        'carrier',
        'passengers',
        'cash_min_nok',
        'cash_max_nok',
        'cash_min_jpy',
        'cash_max_jpy',
        'points_min',
        'points_max',
        'taxes_fees_min_nok',
        'taxes_fees_max_nok',
        'voucher_count',
        'expected_level_points',
        'expected_bonus_points',
        'travel_dates',
        'observed_at',
        'fresh_until',
        'source_priority',
        'source_url',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'passengers' => 'integer',
            'cash_min_nok' => 'integer',
            'cash_max_nok' => 'integer',
            'cash_min_jpy' => 'integer',
            'cash_max_jpy' => 'integer',
            'points_min' => 'integer',
            'points_max' => 'integer',
            'taxes_fees_min_nok' => 'integer',
            'taxes_fees_max_nok' => 'integer',
            'voucher_count' => 'integer',
            'expected_level_points' => 'integer',
            'expected_bonus_points' => 'integer',
            'observed_at' => 'date',
            'fresh_until' => 'date',
        ];
    }

    public function transportLeg(): BelongsTo
    {
        return $this->belongsTo(TransportLeg::class);
    }

    public function awardAvailabilityChecks(): HasMany
    {
        return $this->hasMany(AwardAvailabilityCheck::class);
    }
}
