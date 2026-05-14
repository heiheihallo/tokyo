<?php

namespace App\Models;

use Database\Factories\TripVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripVariant extends Model
{
    /** @use HasFactory<TripVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'slug',
        'name',
        'budget_scenario',
        'stopover_type',
        'flight_strategy',
        'description',
        'is_default',
        'is_public',
        'published_at',
        'sort_order',
        'overrides',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'overrides' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function dayNodes(): HasMany
    {
        return $this->hasMany(DayNode::class)->orderBy('day_number');
    }

    public function routePoints(): HasMany
    {
        return $this->hasMany(RoutePoint::class)->orderBy('sequence');
    }

    public function publish(): void
    {
        $this->forceFill([
            'is_public' => true,
            'published_at' => $this->published_at ?? now(),
        ])->save();
    }

    public function unpublish(): void
    {
        $this->forceFill([
            'is_public' => false,
            'published_at' => null,
        ])->save();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
