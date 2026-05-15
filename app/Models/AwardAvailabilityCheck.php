<?php

namespace App\Models;

use Database\Factories\AwardAvailabilityCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardAvailabilityCheck extends Model
{
    /** @use HasFactory<AwardAvailabilityCheckFactory> */
    use HasFactory;

    protected $fillable = [
        'transport_fare_option_id',
        'checked_on',
        'route_label',
        'travel_dates',
        'cabin',
        'seats_seen',
        'availability_status',
        'source_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
            'seats_seen' => 'integer',
        ];
    }

    public function transportFareOption(): BelongsTo
    {
        return $this->belongsTo(TransportFareOption::class);
    }
}
