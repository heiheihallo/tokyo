<?php

namespace App\Models;

use App\Models\Concerns\HasTripAssetMedia;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class JournalEntry extends Model implements HasMedia
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    use HasTripAssetMedia;

    protected $fillable = [
        'trip_id',
        'trip_variant_id',
        'day_node_id',
        'day_itinerary_item_id',
        'title',
        'excerpt',
        'body',
        'location_label',
        'happened_at',
        'published_at',
        'visibility',
        'tags',
        'metadata',
    ];

    protected $attributes = [
        'visibility' => 'private',
    ];

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
            'published_at' => 'datetime',
            'tags' => 'array',
            'metadata' => 'array',
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

    public function dayItineraryItem(): BelongsTo
    {
        return $this->belongsTo(DayItineraryItem::class);
    }

    /**
     * @return Collection<int, Media>
     */
    public function publicMedia(): Collection
    {
        return $this->getMedia(self::MEDIA_COLLECTION_MAIN_IMAGE)
            ->merge($this->getMedia(self::MEDIA_COLLECTION_IMAGES))
            ->filter(fn (Media $media): bool => data_get($media->custom_properties, 'visibility', 'private') === 'public')
            ->values();
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where(function (Builder $query) use ($user): void {
                $query->where('visibility', 'public');

                if ($user) {
                    $query->orWhere('visibility', 'family');
                }
            });
    }

    public function setVisibility(string $visibility): void
    {
        if (! in_array($visibility, ['private', 'family', 'public'], true)) {
            return;
        }

        $this->forceFill(['visibility' => $visibility])->save();
    }

    public function publish(?string $visibility = null): void
    {
        if ($visibility !== null) {
            $this->setVisibility($visibility);
        }

        $this->forceFill([
            'visibility' => $this->visibility === 'private' ? 'public' : $this->visibility,
            'published_at' => $this->published_at ?? now(),
        ])->save();
    }

    public function unpublish(): void
    {
        $this->forceFill([
            'visibility' => 'private',
            'published_at' => null,
        ])->save();
    }

    public function isVisibleTo(?User $user): bool
    {
        if (! $this->published_at) {
            return false;
        }

        if ($this->visibility === 'public') {
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
}
