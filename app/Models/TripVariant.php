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
        'frontend_access',
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
            'frontend_access' => 'public',
        ])->save();
    }

    public function unpublish(): void
    {
        $this->forceFill([
            'is_public' => false,
            'published_at' => null,
            'visibility' => 'private',
            'frontend_access' => 'private',
        ])->save();
    }

    public function setVisibility(string $visibility): void
    {
        if (! in_array($visibility, ['private', 'family', 'public'], true)) {
            return;
        }

        $this->forceFill([
            'visibility' => $visibility,
            'frontend_access' => $this->frontendAccessFromVisibility($visibility),
            'is_public' => $visibility === 'public',
            'published_at' => $visibility === 'public' ? ($this->published_at ?? now()) : null,
        ])->save();
    }

    public function setFrontendAccess(string $access): void
    {
        if (! in_array($access, ['private', 'authenticated', 'public'], true)) {
            return;
        }

        $this->forceFill([
            'frontend_access' => $access,
            'visibility' => $this->visibilityFromFrontendAccess($access),
            'is_public' => $access === 'public',
            'published_at' => $access === 'public' ? ($this->published_at ?? now()) : null,
        ])->save();
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->is_public || $this->visibility === 'public' || $this->travelerAccessMode() === 'public') {
            return true;
        }

        return $this->travelerAccessMode() === 'authenticated' && $user !== null;
    }

    public function visibilityLabel(): string
    {
        return match ($this->visibility) {
            'public' => 'Public',
            'family' => 'Family',
            default => 'Private',
        };
    }

    public function travelerAccessMode(): string
    {
        return $this->frontend_access ?? $this->frontendAccessFromVisibility($this->visibility);
    }

    public function travelerAccessLabel(): string
    {
        return match ($this->travelerAccessMode()) {
            'public' => 'Public launch',
            'authenticated' => 'Planning login',
            default => 'Private',
        };
    }

    private function frontendAccessFromVisibility(string $visibility): string
    {
        return match ($visibility) {
            'public' => 'public',
            'family' => 'authenticated',
            default => 'private',
        };
    }

    private function visibilityFromFrontendAccess(string $access): string
    {
        return match ($access) {
            'public' => 'public',
            'authenticated' => 'family',
            default => 'private',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
