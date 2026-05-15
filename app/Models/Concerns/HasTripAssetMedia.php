<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasTripAssetMedia
{
    use InteractsWithMedia;

    public const MEDIA_COLLECTION_MAIN_IMAGE = 'main_image';

    public const MEDIA_COLLECTION_IMAGES = 'images';

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection(self::MEDIA_COLLECTION_MAIN_IMAGE)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])
            ->singleFile();

        $this
            ->addMediaCollection(self::MEDIA_COLLECTION_IMAGES)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('thumb')
            ->performOnCollections(self::MEDIA_COLLECTION_MAIN_IMAGE, self::MEDIA_COLLECTION_IMAGES)
            ->fit(Fit::Crop, 320, 240)
            ->format('webp');

        $this
            ->addMediaConversion('card')
            ->performOnCollections(self::MEDIA_COLLECTION_MAIN_IMAGE, self::MEDIA_COLLECTION_IMAGES)
            ->fit(Fit::Crop, 800, 600)
            ->format('webp');

        $this
            ->addMediaConversion('hero')
            ->performOnCollections(self::MEDIA_COLLECTION_MAIN_IMAGE, self::MEDIA_COLLECTION_IMAGES)
            ->fit(Fit::Crop, 1600, 900)
            ->format('webp');
    }
}
