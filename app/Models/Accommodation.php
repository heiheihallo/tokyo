<?php

namespace App\Models;

use Database\Factories\AccommodationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Accommodation extends Model
{
    /** @use HasFactory<AccommodationFactory> */
    use HasFactory;

    protected $fillable = [
        'stable_key',
        'name',
        'neighborhood',
        'city',
        'country',
        'breakfast_note',
        'dinner_note',
        'check_in_time',
        'check_out_time',
        'reservation_url',
        'latitude',
        'longitude',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function dayNodes(): BelongsToMany
    {
        return $this->belongsToMany(DayNode::class, 'day_node_accommodation')
            ->withPivot(['role', 'confirmation_status', 'reservation_url', 'notes'])
            ->withTimestamps();
    }
}
