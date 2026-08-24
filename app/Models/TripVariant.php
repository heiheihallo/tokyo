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
        'visibility',
        'sort_order',
        'overrides',
    ];

    protected $attributes = [
        'visibility' => 'private',
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

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class)
            ->orderByDesc('happened_at')
            ->orderByDesc('created_at');
    }

    public function publish(): void
    {
        $this->forceFill([
            'is_public' => true,
            'published_at' => $this->published_at ?? now(),
            'visibility' => 'public',
        ])->save();
    }

    public function unpublish(): void
    {
        $this->forceFill([
            'is_public' => false,
            'published_at' => null,
            'visibility' => 'private',
        ])->save();
    }

    public function setVisibility(string $visibility): void
    {
        if (! in_array($visibility, ['private', 'family', 'public'], true)) {
            return;
        }

        $this->forceFill([
            'visibility' => $visibility,
            'is_public' => $visibility === 'public',
            'published_at' => $visibility === 'public' ? ($this->published_at ?? now()) : null,
        ])->save();
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->is_public || $this->visibility === 'public') {
            return true;
        }

        return $this->visibility === 'family' && $user !== null;
    }

    public function visibilityLabel(): string
    {
        return match ($this->visibility) {
            'public' => 'Public',
            'family' => 'Family',
            default => 'Private',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
