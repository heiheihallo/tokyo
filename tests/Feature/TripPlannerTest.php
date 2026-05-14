<?php

use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\DayTask;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

test('guests cannot access planner and trip management screens', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('trips.manage'))->assertRedirect(route('login'));
});

test('authenticated users can view the planner shell', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('japan reference import creates multiple timelines and preserves edited records', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();

    expect($trip->variants)->toHaveCount(4);

    $defaultVariant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();

    expect($defaultVariant->dayNodes)->toHaveCount(24);

    $day = $defaultVariant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $day->update(['title' => 'Edited Tokyo arrival rhythm']);

    Artisan::call('trip:import-japan-reference');

    expect($day->fresh()->title)->toBe('Edited Tokyo arrival rhythm');
});

test('planner filters timeline data by variant and priority', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::planner.dashboard')
        ->set('tripSlug', $trip->slug)
        ->set('variantSlug', 'value-copenhagen-stopover')
        ->set('priority', 'high')
        ->assertSet('priority', 'high')
        ->assertSee('Long haul to Tokyo Haneda')
        ->assertDontSee('Low effort Copenhagen day');
});

test('trip management can persist selected day edits', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $day = DayNode::query()->where('stable_key', 'day-6')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->call('selectDay', $day->id)
        ->set('dayForm.title', 'teamLab with Toyosu lunch buffer')
        ->set('dayForm.location', 'Tokyo / Toyosu')
        ->set('dayForm.booking_priority', 'medium')
        ->set('dayForm.booking_status', 'planned')
        ->set('dayForm.cost_value_min_nok', 1900)
        ->set('dayForm.cost_value_max_nok', 3000)
        ->set('dayForm.cost_premium_min_nok', 3700)
        ->set('dayForm.cost_premium_max_nok', 5900)
        ->set('dayForm.rain_backup', 'Stay indoors around Toyosu.')
        ->call('updateDay')
        ->assertHasNoErrors();

    expect($day->fresh())
        ->title->toBe('teamLab with Toyosu lunch buffer')
        ->booking_status->toBe('planned');
});

test('unpublished trips are not visible publicly', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();

    $this->get(route('trips.public', $trip))->assertNotFound();
});

test('published trip is visible publicly with only published timelines', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();
    $trip->publish();

    $publishedVariant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $hiddenVariant = $trip->variants()->where('slug', 'premium-seoul-stopover')->firstOrFail();

    $publishedVariant->publish();

    $this->get(route('trips.public', $trip))
        ->assertOk()
        ->assertSee($trip->name)
        ->assertSee($publishedVariant->name)
        ->assertDontSee($hiddenVariant->name);
});

test('public trip page hides admin only planning data', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();

    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $trip->publish();
    $variant->publish();
    $day->update([
        'booking_status' => 'admin-booked-private',
        'booking_priority' => 'admin-priority-private',
        'reservation_url' => 'https://private.example.test/reservation',
        'cancellation_window_at' => now()->addMonth(),
    ]);

    $this->get(route('trips.public', $trip))
        ->assertOk()
        ->assertSee('Tokyo Station first easy day')
        ->assertDontSee('admin-booked-private')
        ->assertDontSee('admin-priority-private')
        ->assertDontSee('METS_AKIHABARA')
        ->assertDontSee('private.example.test')
        ->assertDontSee('Modeled cost');
});

test('trip management can toggle trip and variant publication', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->call('toggleTripPublication')
        ->call('toggleVariantPublication', $variant->id)
        ->assertHasNoErrors();

    expect($trip->fresh())
        ->is_public->toBeTrue()
        ->published_at->not->toBeNull();

    expect($variant->fresh())
        ->is_public->toBeTrue()
        ->published_at->not->toBeNull();
});

test('public day show page requires a published trip and timeline', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $this->get(route('trips.public.days.show', [$trip, $variant, $day]))->assertNotFound();

    $trip->publish();

    $this->get(route('trips.public.days.show', [$trip, $variant, $day]))->assertNotFound();
});

test('published day show page renders richer traveler details', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $trip->publish();
    $variant->publish();

    $this->get(route('trips.public.days.show', [$trip, $variant, $day]))
        ->assertOk()
        ->assertSee('Tokyo Station first easy day')
        ->assertSee('Tokyo Station First Avenue')
        ->assertSee('Tokyo Ramen Street')
        ->assertSee('JR East Hotel Mets Premier Akihabara')
        ->assertSee('Rain backup');
});

test('reference import creates typed day slots and preserves edited slots', function () {
    Artisan::call('trip:import-japan-reference');

    $day = DayNode::query()
        ->where('stable_key', 'day-4')
        ->whereHas('variant', fn ($query) => $query->where('slug', 'value-copenhagen-stopover'))
        ->firstOrFail();

    expect($day->itineraryItems()->whereIn('item_type', ['stay', 'activity', 'food'])->count())->toBeGreaterThan(0);
    expect($day->tasks()->where('task_type', 'fix')->exists())->toBeTrue();

    $slot = $day->itineraryItems()->where('item_type', 'activity')->firstOrFail();
    $slot->update(['title' => 'Edited slot title']);

    Artisan::call('trip:import-japan-reference');

    expect($slot->fresh()->title)->toBe('Edited slot title');
});

test('public day show page renders public slots and hides private planning tasks', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();

    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $trip->publish();
    $variant->publish();

    $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'visible-slot',
        'item_type' => 'buffer',
        'time_label' => 'after lunch',
        'title' => 'Visible traveler buffer',
        'summary' => 'Public slot summary',
        'is_public' => true,
        'sort_order' => 500,
        'details' => [],
    ]);

    $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'private-slot',
        'item_type' => 'note',
        'title' => 'Private internal slot',
        'is_public' => false,
        'sort_order' => 510,
        'details' => [],
    ]);

    $day->tasks()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'private-fix-task',
        'task_type' => 'fix',
        'title' => 'Private timing cleanup',
        'status' => 'open',
        'priority' => 'high',
        'details' => [],
    ]);

    $this->get(route('trips.public.days.show', [$trip, $variant, $day]))
        ->assertOk()
        ->assertSee('Day timeline')
        ->assertSee('Visible traveler buffer')
        ->assertSee('after lunch')
        ->assertDontSee('Private internal slot')
        ->assertDontSee('Private timing cleanup');
});

test('trip management can create typed day slots and tasks', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $day = DayNode::query()
        ->where('stable_key', 'day-6')
        ->whereHas('variant', fn ($query) => $query->where('slug', 'value-copenhagen-stopover'))
        ->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->call('selectDay', $day->id)
        ->set('slotForm.item_type', 'move')
        ->set('slotForm.time_label', '10:15')
        ->set('slotForm.title', 'Leave for Toyosu')
        ->set('slotForm.location_label', 'Akihabara station')
        ->set('slotForm.summary', 'Move before lunch so the afternoon stays light.')
        ->set('slotForm.is_public', true)
        ->call('createSlot')
        ->set('taskForm.task_type', 'fix')
        ->set('taskForm.title', 'Confirm Toyosu transfer timing')
        ->set('taskForm.priority', 'high')
        ->set('taskForm.notes', 'Check exact train once hotel is final.')
        ->call('createTask')
        ->assertHasNoErrors();

    expect(DayItineraryItem::query()->where('day_node_id', $day->id)->where('title', 'Leave for Toyosu')->exists())->toBeTrue();
    expect(DayTask::query()->where('day_node_id', $day->id)->where('title', 'Confirm Toyosu transfer timing')->exists())->toBeTrue();
});

test('public day show page hides admin only planning data', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();

    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $trip->publish();
    $variant->publish();
    $day->update([
        'booking_status' => 'admin-booked-private',
        'booking_priority' => 'admin-priority-private',
        'reservation_url' => 'https://private.example.test/reservation',
        'cancellation_window_at' => now()->addMonth(),
    ]);

    $this->get(route('trips.public.days.show', [$trip, $variant, $day]))
        ->assertOk()
        ->assertDontSee('admin-booked-private')
        ->assertDontSee('admin-priority-private')
        ->assertDontSee('METS_AKIHABARA')
        ->assertDontSee('private.example.test')
        ->assertDontSee('Modeled cost');
});
