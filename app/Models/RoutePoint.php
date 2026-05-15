<?php

namespace App\Models;

use App\Models\Concerns\HasTripAssetMedia;
use Database\Factories\RoutePointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

class RoutePoint extends Model implements HasMedia
{
    /** @use HasFactory<RoutePointFactory> */
    use HasFactory;

    use HasTripAssetMedia;

    protected $fillable = [
        'trip_variant_id',
        'day_node_id',
        'stable_key',
        'name',
        'category',
        'latitude',
        'longitude',
        'sequence',
        'route_group',
        'external_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(TripVariant::class, 'trip_variant_id');
    }

    public function dayNode(): BelongsTo
    {
        return $this->belongsTo(DayNode::class);
    }
}
