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
        'metadata',
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

    public function defaultVariant(): ?TripVariant
    {
        return $this->variants()->where('is_default', true)->first()
            ?? $this->variants()->orderBy('sort_order')->first();
    }

    public function publishedVariants(): HasMany
    {
        return $this->hasMany(TripVariant::class)
            ->where('is_public', true)
            ->orderBy('sort_order');
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
