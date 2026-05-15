<?php

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\FoodSpot;
use App\Models\RoutePoint;
use App\Models\TransportLeg;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

uses(TestCase::class);

test('trip asset models register shared image collections and webp conversions', function (string $modelClass) {
    /** @var Model&HasMedia $model */
    $model = new $modelClass;

    expect($model)->toBeInstanceOf(HasMedia::class);

    $collections = $model->getRegisteredMediaCollections()->keyBy('name');

    expect($collections->keys()->all())->toContain(
        $modelClass::MEDIA_COLLECTION_MAIN_IMAGE,
        $modelClass::MEDIA_COLLECTION_IMAGES,
    );

    expect($collections[$modelClass::MEDIA_COLLECTION_MAIN_IMAGE])
        ->singleFile->toBeTrue()
        ->acceptsMimeTypes->toBe(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);

    expect($collections[$modelClass::MEDIA_COLLECTION_IMAGES])
        ->singleFile->toBeFalse()
        ->acceptsMimeTypes->toBe(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);

    $model->registerAllMediaConversions();

    $conversions = collect($model->mediaConversions)->keyBy->getName();

    expect($conversions->keys()->all())->toBe(['thumb', 'card', 'hero']);

    $conversions->each(function ($conversion) use ($modelClass) {
        expect($conversion->getPerformOnCollections())->toBe([
            $modelClass::MEDIA_COLLECTION_MAIN_IMAGE,
            $modelClass::MEDIA_COLLECTION_IMAGES,
        ]);

        expect($conversion->getResultExtension('jpg'))->toBe('webp');
    });
})->with([
    Accommodation::class,
    Activity::class,
    FoodSpot::class,
    RoutePoint::class,
    TransportLeg::class,
]);
