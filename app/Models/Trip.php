<?php

namespace App\Models;

use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'summary',
        'starts_on',
        'ends_on',
        'currency_primary',
        'currency_secondary',
        'arrival_preference',
        'is_public',
        'published_at',
        'visibility',
        'frontend_access',
        'metadata',
    ];

    protected $attributes = [
        'visibility' => 'private',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(TripVariant::class)->orderBy('sort_order');
    }

    public function dayNodes(): HasMany
    {
        return $this->hasMany(DayNode::class)->orderBy('day_number');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class)
            ->orderByDesc('happened_at')
            ->orderByDesc('created_at');
    }

    public function defaultVariant(): ?TripVariant
    {
        return $this->variants()->where('is_default', true)->first()
            ?? $this->variants()->orderBy('sort_order')->first();
    }

    public function publishedVariants(): HasMany
    {
        return $this->hasMany(TripVariant::class)
            ->where(fn ($query) => $query->where('is_public', true)->orWhere('visibility', 'public')->orWhere('frontend_access', 'public'))
            ->orderBy('sort_order');
    }

    public function visibleVariantsFor(?User $user): HasMany
    {
        return $this->hasMany(TripVariant::class)
            ->where(function ($query) use ($user): void {
                $query->where('is_public', true)
                    ->orWhere('visibility', 'public')
                    ->orWhere('frontend_access', 'public');

                if ($user) {
                    $query->orWhere('visibility', 'family')
                        ->orWhere('frontend_access', 'authenticated');
                }
            })
            ->orderBy('sort_order');
    }

    public function loyaltyProgramSnapshots(): HasMany
    {
        return $this->hasMany(LoyaltyProgramSnapshot::class)->latest();
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
