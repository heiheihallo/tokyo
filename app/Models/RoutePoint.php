<?php

namespace App\Models;

use Database\Factories\RoutePointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutePoint extends Model
{
    /** @use HasFactory<RoutePointFactory> */
    use HasFactory;

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
