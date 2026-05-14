<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'stable_key',
        'name',
        'area',
        'city',
        'country',
        'rain_fit',
        'age_fit',
        'prebooking_status',
        'reservation_url',
        'latitude',
        'longitude',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function dayNodes(): BelongsToMany
    {
        return $this->belongsToMany(DayNode::class)
            ->withPivot(['sequence', 'time_block', 'booking_status', 'reservation_url', 'notes'])
            ->withTimestamps();
    }
}
