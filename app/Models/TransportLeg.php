<?php

namespace App\Models;

use Database\Factories\TransportLegFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TransportLeg extends Model
{
    /** @use HasFactory<TransportLegFactory> */
    use HasFactory;

    protected $fillable = [
        'stable_key',
        'mode',
        'route_label',
        'duration_label',
        'operator',
        'origin',
        'destination',
        'reservation_url',
        'geo_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'geo_path' => 'array',
        ];
    }

    public function dayNodes(): BelongsToMany
    {
        return $this->belongsToMany(DayNode::class)
            ->withPivot(['sequence', 'booking_status', 'reservation_url', 'notes'])
            ->withTimestamps();
    }
}
