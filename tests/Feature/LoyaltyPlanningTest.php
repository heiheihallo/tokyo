<?php

use App\Mcp\Servers\TripPlannerServer;
use App\Mcp\Support\TripCostEstimator;
use App\Mcp\Tools\EstimateTripCostTool;
use App\Mcp\Tools\GetLoyaltyPlanTool;
use App\Mcp\Tools\RecordAwardAvailabilityCheckTool;
use App\Mcp\Tools\RecordBonusGrabTripTool;
use App\Mcp\Tools\RecordFlightFareOptionTool;
use App\Mcp\Tools\UpdateLoyaltyPlanTool;
use App\Models\AwardAvailabilityCheck;
use App\Models\BonusGrabTrip;
use App\Models\DayNode;
use App\Models\LoyaltyProgramSnapshot;
use App\Models\LoyaltyVoucher;
use App\Models\TransportFareOption;
use App\Models\TransportLeg;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

test('loyalty planning models store eurobonus assumptions and bonus grab trips', function () {
    $snapshot = LoyaltyProgramSnapshot::factory()->create([
        'current_points' => 8899,
        'current_level_points' => 200,
        'projected_card_points' => 60000,
        'projected_card_level_points' => 18000,
    ]);
    $voucher = LoyaltyVoucher::factory()->for($snapshot)->create([
        'quantity' => 2,
        'earned_threshold_nok' => 300000,
    ]);
    $bonusGrabTrip = BonusGrabTrip::factory()->for($snapshot)->create([
        'title' => 'OSL-CPH-Tokyo point grabber',
        'expected_level_points' => 13500,
    ]);
    $fareOption = TransportFareOption::factory()->create([
        'fare_type' => 'award',
        'points_min' => 90000,
        'voucher_count' => 1,
    ]);
    $availabilityCheck = AwardAvailabilityCheck::factory()->for($fareOption)->create([
        'availability_status' => 'available',
        'seats_seen' => 2,
    ]);

    expect($snapshot->fresh())
        ->current_points->toBe(8899)
        ->projected_card_points->toBe(60000)
        ->and($voucher->fresh()->quantity)->toBe(2)
        ->and($bonusGrabTrip->fresh()->expected_level_points)->toBe(13500)
        ->and($availabilityCheck->fresh()->availability_status)->toBe('available');
});

test('trip cost estimator includes loyalty gaps fare warnings and bonus grab projections', function () {
    $day = DayNode::factory()->create();
    $leg = TransportLeg::factory()->create([
        'mode' => 'flight',
        'route_label' => 'OSL-CPH-HND',
    ]);
    $day->transportLegs()->attach($leg->id);

    $snapshot = LoyaltyProgramSnapshot::factory()->for($day->variant->trip)->create([
        'current_points' => 8899,
        'current_level_points' => 4200,
        'signup_bonus_points' => 15000,
        'projected_card_points' => 60000,
        'projected_card_level_points' => 18000,
        'expected_trip_level_points' => 0,
        'target_level_points' => 45000,
    ]);
    LoyaltyVoucher::factory()->for($snapshot)->create(['quantity' => 2]);
    BonusGrabTrip::factory()->for($snapshot)->create([
        'title' => 'Tokyo weekend status run',
        'expected_bonus_points' => 4000,
        'expected_level_points' => 13500,
        'cash_cost_max_nok' => 16000,
    ]);
    TransportFareOption::factory()->for($leg)->create([
        'label' => '2-for-1 Business award',
        'fare_type' => 'award',
        'cabin' => 'business',
        'cash_min_nok' => null,
        'cash_max_nok' => null,
        'points_min' => 90000,
        'points_max' => 120000,
        'voucher_count' => 1,
        'fresh_until' => now()->addWeek(),
    ]);

    $estimate = app(TripCostEstimator::class)->estimate($day->variant);

    expect($estimate['loyalty']['snapshot']['projected_points_before_fare_options'])->toBe(83899)
        ->and($estimate['loyalty']['snapshot']['remaining_level_points_to_target'])->toBe(22800)
        ->and($estimate['loyalty']['snapshot']['available_vouchers'])->toBe(2);

    expect($estimate['loyalty']['fare_options'][0]['warnings'])
        ->toContain('points_shortfall')
        ->toContain('award_availability_missing');

    expect($estimate['loyalty']['bonus_grab_trips'][0])
        ->remaining_level_points_after->toBe(9300)
        ->nok_per_level_point->toBe(1.19);
});

test('mcp tools manage loyalty plan fare options award checks and bonus grab candidates', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $leg = TransportLeg::query()->where('stable_key', 'cph-hnd')->firstOrFail();

    TripPlannerServer::tool(UpdateLoyaltyPlanTool::class, [
        'trip_slug' => $trip->slug,
        'current_points' => 8899,
        'current_level_points' => 200,
        'qualification_starts_on' => '2026-04-01',
        'qualification_ends_on' => '2027-03-31',
        'expected_trip_level_points' => 4000,
        'signup_bonus_points' => 15000,
        'card_spend_target_nok' => 300000,
        'card_points_per_100_nok' => 20,
        'card_level_points_per_100_nok' => 6,
        'projected_card_points' => 60000,
        'projected_card_level_points' => 18000,
        'expected_voucher_quantity' => 2,
    ])
        ->assertOk()
        ->assertSee('EuroBonus');

    TripPlannerServer::tool(RecordFlightFareOptionTool::class, [
        'transport_leg_id' => $leg->id,
        'label' => '2-for-1 Business award',
        'fare_type' => 'award',
        'cabin' => 'business',
        'carrier' => 'SAS',
        'points_min' => 90000,
        'points_max' => 120000,
        'taxes_fees_min_nok' => 1800,
        'taxes_fees_max_nok' => 2600,
        'voucher_count' => 1,
        'travel_dates' => 'June 2027',
        'observed_at' => now()->toDateString(),
        'fresh_until' => now()->addDays(14)->toDateString(),
        'source_priority' => 'official',
        'source_url' => 'https://example.test/awards',
    ])->assertOk();

    $fareOption = TransportFareOption::query()->where('label', '2-for-1 Business award')->firstOrFail();

    TripPlannerServer::tool(RecordAwardAvailabilityCheckTool::class, [
        'transport_fare_option_id' => $fareOption->id,
        'availability_status' => 'available',
        'seats_seen' => 2,
        'source_url' => 'https://example.test/awards',
    ])->assertOk();

    TripPlannerServer::tool(RecordBonusGrabTripTool::class, [
        'trip_slug' => $trip->slug,
        'title' => 'SAS Premium Tokyo point grabber',
        'route_label' => 'OSL-CPH-TYO-CPH-OSL',
        'starts_on' => '2027-02-10',
        'ends_on' => '2027-02-12',
        'cash_cost_min_nok' => 12000,
        'cash_cost_max_nok' => 16000,
        'expected_bonus_points' => 4000,
        'expected_level_points' => 13500,
        'nights_away' => 2,
        'feasibility_score' => 75,
        'legs' => [
            ['sequence' => 1, 'origin' => 'OSL', 'destination' => 'CPH', 'carrier' => 'SAS', 'expected_level_points' => 1000],
            ['sequence' => 2, 'origin' => 'CPH', 'destination' => 'HND', 'carrier' => 'SAS', 'expected_level_points' => 5750],
        ],
    ])->assertOk();

    TripPlannerServer::tool(GetLoyaltyPlanTool::class, [
        'trip_slug' => $trip->slug,
        'variant_slug' => 'value-copenhagen-stopover',
    ])
        ->assertOk()
        ->assertSee('SAS Premium Tokyo point grabber')
        ->assertSee('available');

    TripPlannerServer::tool(EstimateTripCostTool::class, [
        'trip_slug' => $trip->slug,
        'variant_slug' => 'value-copenhagen-stopover',
    ])
        ->assertOk()
        ->assertSee('best_bonus_grab_trip_id');
});

test('loyalty planning stays private to management screens and is hidden publicly', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $trip->publish();
    $variant->publish();

    $snapshot = LoyaltyProgramSnapshot::factory()->for($trip)->create();
    BonusGrabTrip::factory()->for($snapshot)->create([
        'title' => 'Private Tokyo point grabber',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->assertSee('EuroBonus plan')
        ->assertSee('Private Tokyo point grabber');

    $this->get(route('trips.public', $trip))
        ->assertOk()
        ->assertDontSee('EuroBonus plan')
        ->assertDontSee('Private Tokyo point grabber');
});
