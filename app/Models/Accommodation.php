<?php

namespace App\Models;

use App\Models\Concerns\HasTripAssetMedia;
use Database\Factories\AccommodationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;

class Accommodation extends Model implements HasMedia
{
    /** @use HasFactory<AccommodationFactory> */
    use HasFactory;

    use HasTripAssetMedia;

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
        'price_min_nok',
        'price_max_nok',
        'price_min_jpy',
        'price_max_jpy',
        'price_basis',
        'price_notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_min_nok' => 'integer',
            'price_max_nok' => 'integer',
            'price_min_jpy' => 'integer',
            'price_max_jpy' => 'integer',
        ];
    }

    public function dayNodes(): BelongsToMany
    {
        return $this->belongsToMany(DayNode::class, 'day_node_accommodation')
            ->withPivot(['role', 'confirmation_status', 'reservation_url', 'notes'])
            ->withTimestamps();
    }
}
