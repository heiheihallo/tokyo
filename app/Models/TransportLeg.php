<?php

namespace App\Models;

use App\Models\Concerns\HasTripAssetMedia;
use Database\Factories\TransportLegFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;

class TransportLeg extends Model implements HasMedia
{
    /** @use HasFactory<TransportLegFactory> */
    use HasFactory;

    use HasTripAssetMedia;

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
            'geo_path' => 'array',
            'price_min_nok' => 'integer',
            'price_max_nok' => 'integer',
            'price_min_jpy' => 'integer',
            'price_max_jpy' => 'integer',
        ];
    }

    public function dayNodes(): BelongsToMany
    {
        return $this->belongsToMany(DayNode::class)
            ->withPivot(['sequence', 'booking_status', 'reservation_url', 'notes'])
            ->withTimestamps();
    }
}
