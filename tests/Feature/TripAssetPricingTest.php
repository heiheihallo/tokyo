<?php

use App\Mcp\Support\TripCostEstimator;
use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\DayNode;
use App\Models\FoodSpot;
use App\Models\TransportLeg;

test('shared assets store nok and jpy price ranges with a typed basis', function (string $modelClass, string $basis) {
    $asset = $modelClass::factory()->create([
        'price_min_nok' => 123,
        'price_max_nok' => 456,
        'price_min_jpy' => 1700,
        'price_max_jpy' => 6400,
        'price_basis' => $basis,
        'price_notes' => 'Observed range.',
    ]);

    expect($asset->fresh())
        ->price_min_nok->toBe(123)
        ->price_max_nok->toBe(456)
        ->price_min_jpy->toBe(1700)
        ->price_max_jpy->toBe(6400)
        ->price_basis->toBe($basis)
        ->price_notes->toBe('Observed range.');
})->with([
    [Accommodation::class, 'per_night'],
    [Activity::class, 'per_ticket'],
    [FoodSpot::class, 'per_meal'],
    [TransportLeg::class, 'per_person'],
]);

test('trip cost estimator groups totals by price basis and reports missing prices', function () {
    $day = DayNode::factory()->create();
    $hotel = Accommodation::factory()->create([
        'price_min_nok' => 1000,
        'price_max_nok' => 1500,
        'price_min_jpy' => 14000,
        'price_max_jpy' => 21000,
        'price_basis' => 'per_night',
    ]);
    $activity = Activity::factory()->create([
        'price_min_nok' => null,
        'price_max_nok' => null,
        'price_min_jpy' => null,
        'price_max_jpy' => null,
        'price_basis' => 'per_ticket',
    ]);

    $day->accommodations()->attach($hotel->id);
    $day->activities()->attach($activity->id);

    $estimate = app(TripCostEstimator::class)->estimate($day->variant);

    expect($estimate['totals_by_basis']['per_night'])
        ->min_nok->toBe(1000)
        ->max_nok->toBe(1500)
        ->min_jpy->toBe(14000)
        ->max_jpy->toBe(21000);

    expect($estimate['excluded_items'])
        ->toHaveCount(1)
        ->and($estimate['excluded_items'][0]['excluded_reasons'])->toContain('missing_price');
});
