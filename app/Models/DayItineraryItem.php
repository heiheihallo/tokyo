<?php

namespace App\Models;

use Database\Factories\DayItineraryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DayItineraryItem extends Model
{
    /** @use HasFactory<DayItineraryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'trip_variant_id',
        'day_node_id',
        'stable_key',
        'item_type',
        'starts_at',
        'ends_at',
        'time_label',
        'title',
        'summary',
        'location_label',
        'subject_type',
        'subject_id',
        'latitude',
        'longitude',
        'is_public',
        'sort_order',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime:H:i',
            'ends_at' => 'datetime:H:i',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_public' => 'boolean',
            'details' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(TripVariant::class, 'trip_variant_id');
    }

    public function dayNode(): BelongsTo
    {
        return $this->belongsTo(DayNode::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
