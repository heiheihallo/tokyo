<?php

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\DayTask;
use App\Models\FoodSpot;
use App\Models\JournalEntry;
use App\Models\Source;
use App\Models\TransportLeg;
use App\Models\Trip;
use App\Models\TripVariant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

test('guests cannot access planner and trip management screens', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('trips.manage'))->assertRedirect(route('login'));
});

test('authenticated users can view the planner shell', function () {
    Artisan::call('trip:import-japan-reference');

    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Advanced filters');
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

test('planner compares multiple timelines and can open one from comparison', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::planner.dashboard')
        ->set('tripSlug', $trip->slug)
        ->set('view', 'compare')
        ->assertSee('Timeline comparison')
        ->assertSee('Value with Copenhagen stopover')
        ->assertSee('Premium with Seoul stopover')
        ->assertSee('Hotel changes')
        ->call('openComparisonVariant', 'premium-seoul-stopover')
        ->assertSet('variantSlug', 'premium-seoul-stopover')
        ->assertSet('view', 'timeline');
});

test('planner removes the map pane when switching from map to comparison', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::planner.dashboard')
        ->set('view', 'map')
        ->assertSeeHtml('planner-view-map')
        ->set('view', 'compare')
        ->assertSeeHtml('planner-view-compare')
        ->assertDontSeeHtml('planner-view-map')
        ->assertSee('Timeline comparison');
});

test('admin timeline does not preselect a day', function () {
    Artisan::call('trip:import-japan-reference');

    Livewire::actingAs(User::factory()->create())
        ->test('pages::planner.dashboard')
        ->assertSet('selectedDayId', null)
        ->assertSet('showDayTimeline', false)
        ->assertDontSee('Day timeline');
});

test('admin timeline hides expanded day slots until toggled', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'private-admin-slot',
        'item_type' => 'note',
        'title' => 'Private admin transfer note',
        'is_public' => false,
        'sort_order' => 900,
        'details' => [],
    ]);

    Livewire::actingAs($user)
        ->test('pages::planner.dashboard')
        ->set('tripSlug', $trip->slug)
        ->set('variantSlug', $variant->slug)
        ->call('selectDay', $day->id)
        ->assertSet('showDayTimeline', false)
        ->assertSee('Show day timeline')
        ->assertSee('METS_AKIHABARA')
        ->assertDontSee('Private admin transfer note')
        ->call('toggleDayTimeline')
        ->assertSet('showDayTimeline', true)
        ->assertSee('Hide day timeline')
        ->assertSee('Tokyo Station First Avenue')
        ->assertSee('Private admin transfer note')
        ->assertSee('Private');
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

test('trip management exposes modal triggers for editing workflows', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $hotel = Accommodation::query()->where('stable_key', 'mets-akihabara')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->call('selectDay', $day->id)
        ->assertSee('Manage trips')
        ->assertSee('Timeline')
        ->assertSee('Assets')
        ->assertSee('Journal')
        ->assertSee('Planning')
        ->assertSee('Publishing')
        ->assertSee('Edit day')
        ->assertSee('Slot')
        ->assertSee('Task')
        ->assertDontSee('Shared assets');

    Livewire::actingAs($user)
        ->test('pages::trips.manage.assets-panel', [
            'selectedDayId' => $day->id,
            'selectedAssetId' => $hotel->id,
            'assetTab' => 'accommodations',
        ])
        ->assertSee('Shared assets')
        ->assertSee('Asset')
        ->assertSee('Attach');
});

test('trip management trip index component manages trip and timeline selection', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();

    Livewire::test('pages::trips.manage.trip-index', [
        'selectedTripId' => $trip->id,
        'selectedVariantId' => $variant->id,
    ])
        ->assertSee('Trips')
        ->assertSee($trip->name)
        ->assertSee('Timelines')
        ->assertSee($variant->name)
        ->set('tripForm.name', 'Tokyo Side Quest')
        ->set('tripForm.summary', 'A separate planning scratchpad.')
        ->call('createTrip')
        ->assertHasNoErrors()
        ->assertDispatched('trip-management-selection-changed')
        ->set('variantForm.name', 'Fast rail loop')
        ->set('variantForm.budget_scenario', 'value')
        ->call('createVariant')
        ->assertHasNoErrors();

    expect(Trip::query()->where('name', 'Tokyo Side Quest')->exists())->toBeTrue();
});

test('trip management publishing panel updates frontend access', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();

    Livewire::test('pages::trips.manage.publishing-panel', [
        'selectedTripId' => $trip->id,
        'selectedVariantId' => $variant->id,
    ])
        ->assertSee('Publishing')
        ->call('setTripFrontendAccess', 'authenticated')
        ->call('setVariantFrontendAccess', $variant->id, 'authenticated')
        ->assertHasNoErrors();

    expect($trip->fresh()->travelerAccessMode())->toBe('authenticated')
        ->and($variant->fresh()->travelerAccessMode())->toBe('authenticated');
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

test('family visible trips require authentication and show only family visible timelines', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();

    $familyVariant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $hiddenVariant = $trip->variants()->where('slug', 'premium-seoul-stopover')->firstOrFail();
    $day = $familyVariant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $trip->setVisibility('family');
    $familyVariant->setVisibility('family');

    $this->get(route('trips.public', $trip))->assertNotFound();
    $this->get(route('trips.public.days.show', [$trip, $familyVariant, $day]))->assertNotFound();

    $this->actingAs($user)
        ->get(route('trips.public', $trip))
        ->assertOk()
        ->assertSee($familyVariant->name)
        ->assertDontSee($hiddenVariant->name)
        ->assertDontSee('Modeled cost');

    $this->actingAs($user)
        ->get(route('trips.public.days.show', [$trip, $familyVariant, $day]))
        ->assertOk()
        ->assertSee('Tokyo Station first easy day');
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
        ->visibility->toBe('public')
        ->published_at->not->toBeNull();

    expect($variant->fresh())
        ->is_public->toBeTrue()
        ->visibility->toBe('public')
        ->published_at->not->toBeNull();
});

test('trip management can set family and private visibility modes', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $trip->publish();
    $variant->publish();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->set('selectedVariantId', $variant->id)
        ->call('setTripVisibility', 'family')
        ->call('setVariantVisibility', $variant->id, 'family')
        ->assertHasNoErrors()
        ->call('setTripVisibility', 'private')
        ->call('setVariantVisibility', $variant->id, 'private')
        ->assertHasNoErrors();

    expect($trip->fresh())
        ->visibility->toBe('private')
        ->is_public->toBeFalse()
        ->published_at->toBeNull();

    expect($variant->fresh())
        ->visibility->toBe('private')
        ->is_public->toBeFalse()
        ->published_at->toBeNull();
});

test('trip management can set frontend access modes for planning and launch', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->set('selectedVariantId', $variant->id)
        ->call('setTripFrontendAccess', 'authenticated')
        ->call('setVariantFrontendAccess', $variant->id, 'authenticated')
        ->assertHasNoErrors()
        ->call('setTripFrontendAccess', 'public')
        ->call('setVariantFrontendAccess', $variant->id, 'public')
        ->assertHasNoErrors();

    expect($trip->fresh())
        ->frontend_access->toBe('public')
        ->visibility->toBe('public')
        ->is_public->toBeTrue();

    expect($variant->fresh())
        ->frontend_access->toBe('public')
        ->visibility->toBe('public')
        ->is_public->toBeTrue();

    $this->get(route('trips.public.days.show', [$trip, $variant, $day]))
        ->assertOk()
        ->assertSee('Tokyo Station first easy day');
});

test('public journal entries respect publication and visibility', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $slot = $day->itineraryItems()->where('is_public', true)->firstOrFail();

    $trip->publish();
    $variant->publish();

    JournalEntry::factory()->published()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'day_itinerary_item_id' => $slot->id,
        'title' => 'Public arrival update',
        'excerpt' => 'Haneda arrival went smoothly.',
        'body' => 'Traveler-safe public note.',
        'happened_at' => '2027-06-30 12:00:00',
        'location_label' => 'Tokyo Station',
    ]);

    JournalEntry::factory()->published('family')->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'title' => 'Family-only check-in',
        'excerpt' => 'Shared with logged-in family.',
    ]);

    JournalEntry::factory()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'title' => 'Draft travel note',
        'visibility' => 'public',
        'published_at' => null,
    ]);

    JournalEntry::factory()->published('private')->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'title' => 'Private admin note',
    ]);

    $this->get(route('trips.public', $trip))
        ->assertOk()
        ->assertSee('Public arrival update')
        ->assertSee('Haneda arrival went smoothly.')
        ->assertDontSee('Family-only check-in')
        ->assertDontSee('Draft travel note')
        ->assertDontSee('Private admin note');

    $this->actingAs($user)
        ->get(route('trips.public.days.show', [$trip, $variant, $day]))
        ->assertOk()
        ->assertSee('Public arrival update')
        ->assertSee('Family-only check-in')
        ->assertDontSee('Draft travel note')
        ->assertDontSee('Private admin note');
});

test('trip management can create update publish and unpublish journal entries', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $slot = $day->itineraryItems()->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->set('selectedVariantId', $variant->id)
        ->set('activeManageTab', 'journal')
        ->set('journalForm.title', 'First trip journal note')
        ->set('journalForm.excerpt', 'Short traveler update.')
        ->set('journalForm.body', 'Longer journal body for the day.')
        ->set('journalForm.location_label', 'Akihabara')
        ->set('journalForm.trip_variant_id', (string) $variant->id)
        ->set('journalForm.day_node_id', (string) $day->id)
        ->set('journalForm.day_itinerary_item_id', (string) $slot->id)
        ->set('journalForm.happened_at', '2027-06-30T18:30')
        ->set('journalForm.visibility', 'family')
        ->set('journalForm.tags', 'arrival, hotel')
        ->set('journalForm.metadata_weather', 'humid')
        ->set('journalForm.metadata_mood', 'excited')
        ->call('createJournalEntry')
        ->assertHasNoErrors()
        ->assertSee('First trip journal note')
        ->set('journalForm.title', 'Updated trip journal note')
        ->call('updateJournalEntry')
        ->call('publishJournalEntry')
        ->assertHasNoErrors();

    $entry = JournalEntry::query()->where('title', 'Updated trip journal note')->firstOrFail();

    expect($entry)
        ->trip_id->toBe($trip->id)
        ->trip_variant_id->toBe($variant->id)
        ->day_node_id->toBe($day->id)
        ->day_itinerary_item_id->toBe($slot->id)
        ->visibility->toBe('family')
        ->published_at->not->toBeNull()
        ->and($entry->tags)->toBe(['arrival', 'hotel'])
        ->and($entry->metadata)->toMatchArray(['weather' => 'humid', 'mood' => 'excited']);

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->set('activeManageTab', 'journal')
        ->call('selectJournalEntry', $entry->id)
        ->call('unpublishJournalEntry')
        ->assertHasNoErrors();

    expect($entry->fresh())
        ->visibility->toBe('private')
        ->published_at->toBeNull();
});

test('trip management can upload journal media with private default visibility', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $entry = JournalEntry::factory()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'title' => 'Media upload entry',
    ]);

    $upload = UploadedFile::fake()->image('journal.png', 2, 2);

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->set('activeManageTab', 'journal')
        ->call('selectJournalEntry', $entry->id)
        ->set('journalMediaUpload', $upload)
        ->set('journalMediaForm.caption', 'Hotel lobby arrival')
        ->set('journalMediaForm.alt', 'Lobby lights')
        ->call('addJournalMedia')
        ->assertHasNoErrors()
        ->assertSee('Hotel lobby arrival');

    $media = $entry->fresh()->getFirstMedia(JournalEntry::MEDIA_COLLECTION_IMAGES);

    expect($media)->not->toBeNull()
        ->and(data_get($media->custom_properties, 'visibility'))->toBe('private')
        ->and(data_get($media->custom_properties, 'caption'))->toBe('Hotel lobby arrival')
        ->and(data_get($media->custom_properties, 'alt'))->toBe('Lobby lights');
});

test('public journal media renders only when the entry and image are public', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $trip->publish();
    $variant->publish();

    $entry = JournalEntry::factory()->published()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'title' => 'Published image entry',
        'excerpt' => 'This update has one public image.',
    ]);

    $publicPath = tempnam(sys_get_temp_dir(), 'journal-public-img');
    $privatePath = tempnam(sys_get_temp_dir(), 'journal-private-img');
    writeTripPlannerJournalTinyPng($publicPath);
    writeTripPlannerJournalTinyPng($privatePath);

    $entry->addMedia($publicPath)
        ->withCustomProperties(['visibility' => 'public', 'caption' => 'Visible caption', 'alt' => 'Visible alt'])
        ->toMediaCollection(JournalEntry::MEDIA_COLLECTION_IMAGES);

    $entry->addMedia($privatePath)
        ->withCustomProperties(['visibility' => 'private', 'caption' => 'Private caption', 'alt' => 'Private alt'])
        ->toMediaCollection(JournalEntry::MEDIA_COLLECTION_IMAGES);

    $this->get(route('trips.public.days.show', [$trip, $variant, $day]))
        ->assertOk()
        ->assertSee('Published image entry')
        ->assertSee('Visible caption')
        ->assertSee('Visible alt')
        ->assertDontSee('Private caption')
        ->assertDontSee('Private alt');
});

test('public journal feed respects planning login access and filters updates', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $hiddenVariant = $trip->variants()->where('slug', 'premium-seoul-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $trip->setFrontendAccess('authenticated');
    $variant->setFrontendAccess('authenticated');
    $hiddenVariant->setFrontendAccess('private');

    JournalEntry::factory()->published('family')->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'title' => 'Family arrival story',
        'excerpt' => 'A planning-login update for family.',
        'tags' => ['arrival', 'hotel'],
    ]);

    JournalEntry::factory()->published('family')->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $hiddenVariant->id,
        'title' => 'Hidden timeline journal',
        'excerpt' => 'This belongs to a private timeline.',
        'tags' => ['hidden'],
    ]);

    $this->get(route('trips.public.journal', $trip))->assertNotFound();

    $this->actingAs($user)
        ->get(route('trips.public.journal', [
            'trip' => $trip,
            'timeline' => $variant->slug,
            'day' => $day->stable_key,
            'tag' => 'arrival',
        ]))
        ->assertOk()
        ->assertSee('Trip journal')
        ->assertSee('Family arrival story')
        ->assertSee('A planning-login update for family.')
        ->assertDontSee('Hidden timeline journal');
});

test('public journal show page renders one traveler safe story', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $trip->publish();
    $variant->publish();

    $entry = JournalEntry::factory()->published()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'title' => 'One shared story',
        'excerpt' => 'Traveler-safe summary.',
        'body' => 'Longer story without admin planning details.',
        'tags' => ['arrival'],
    ]);

    $publicPath = tempnam(sys_get_temp_dir(), 'journal-show-public');
    $privatePath = tempnam(sys_get_temp_dir(), 'journal-show-private');
    writeTripPlannerJournalTinyPng($publicPath);
    writeTripPlannerJournalTinyPng($privatePath);

    $entry->addMedia($publicPath)
        ->withCustomProperties(['visibility' => 'public', 'caption' => 'Story caption', 'alt' => 'Story image alt'])
        ->toMediaCollection(JournalEntry::MEDIA_COLLECTION_IMAGES);

    $entry->addMedia($privatePath)
        ->withCustomProperties(['visibility' => 'private', 'caption' => 'Hidden story caption', 'alt' => 'Hidden story alt'])
        ->toMediaCollection(JournalEntry::MEDIA_COLLECTION_IMAGES);

    $this->get(route('trips.public.journal.show', [$trip, $entry]))
        ->assertOk()
        ->assertSee('One shared story')
        ->assertSee('Longer story without admin planning details.')
        ->assertSee('Story caption')
        ->assertSee('Story image alt')
        ->assertDontSee('Hidden story caption')
        ->assertDontSee('Hidden story alt');
});

test('trip management connects selected day workspace and journal quality checks', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $trip->publish();
    $variant->publish();

    $entry = JournalEntry::factory()->published()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'title' => 'Published but empty journal entry',
        'excerpt' => null,
        'body' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::trips.manage', ['selectedTripId' => $trip->id])
        ->call('selectTripContext', $trip->id, $variant->id)
        ->call('selectDay', $day->id)
        ->assertSee('Day workspace')
        ->assertSee('Preview day')
        ->assertSee('Journal coverage')
        ->call('startJournalForSelectedDay')
        ->assertSet('journalForm.title', 'Day 4 update')
        ->assertSet('journalForm.day_node_id', (string) $day->id)
        ->call('openPlanningIssueTarget', [
            'target_type' => 'journal',
            'journal_entry_id' => $entry->id,
        ])
        ->assertSet('selectedJournalEntryId', $entry->id);

    Livewire::actingAs($user)
        ->test('pages::trips.manage.planning-panel', [
            'selectedTripId' => $trip->id,
            'selectedVariantId' => $variant->id,
        ])
        ->set('planningCategoryFilter', 'journal')
        ->assertSee('Published journal entry has no traveler detail')
        ->call('openPlanningIssue', 'journal-empty-'.$entry->id)
        ->assertDispatched('trip-management-open-planning-issue');
});

test('trip management day workspace creates quick slots and slot journal drafts', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->set('selectedVariantId', $variant->id)
        ->call('selectDay', $day->id)
        ->assertSee('Day board')
        ->call('createQuickDaySlot', 'buffer')
        ->assertHasNoErrors()
        ->assertSee('Flexible buffer');

    $slot = $day->itineraryItems()->where('title', 'Flexible buffer')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->set('selectedVariantId', $variant->id)
        ->call('selectDay', $day->id)
        ->call('startJournalForSelectedSlot', $slot->id)
        ->assertSet('journalForm.title', 'Flexible buffer update')
        ->assertSet('journalForm.day_itinerary_item_id', (string) $slot->id)
        ->set('journalForm.body', 'Family-safe slot update.')
        ->call('createJournalEntry')
        ->call('publishJournalEntryAs', 'family')
        ->assertHasNoErrors();

    $entry = JournalEntry::query()->where('title', 'Flexible buffer update')->firstOrFail();

    expect($slot->fresh())
        ->item_type->toBe('buffer')
        ->time_label->toBe('flex')
        ->is_public->toBeTrue();

    expect($entry)
        ->day_itinerary_item_id->toBe($slot->id)
        ->visibility->toBe('family')
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

test('day planning backfill fills only missing slot helpers and creates specific tasks', function () {
    Artisan::call('trip:import-japan-reference');

    $day = DayNode::query()
        ->where('stable_key', 'day-6')
        ->whereHas('variant', fn ($query) => $query->where('slug', 'value-copenhagen-stopover'))
        ->firstOrFail();

    $moveSlot = $day->itineraryItems()->create([
        'trip_id' => $day->trip_id,
        'trip_variant_id' => $day->trip_variant_id,
        'stable_key' => 'missing-move-helper-data',
        'item_type' => 'move',
        'title' => 'Move to Toyosu',
        'location_label' => 'Toyosu',
        'is_public' => true,
        'sort_order' => 600,
        'details' => [],
    ]);

    $editedSlot = $day->itineraryItems()->where('item_type', 'activity')->firstOrFail();
    $editedSlot->update([
        'time_label' => 'edited custom label',
        'latitude' => 1.2345678,
        'longitude' => 2.3456789,
    ]);

    Artisan::call('trip:backfill-day-planning');

    expect($moveSlot->fresh())
        ->time_label->toBe('move first')
        ->latitude->not->toBeNull()
        ->longitude->not->toBeNull();

    expect($editedSlot->fresh())
        ->time_label->toBe('edited custom label')
        ->latitude->toBe('1.2345678')
        ->longitude->toBe('2.3456789');

    expect($day->tasks()->where('stable_key', 'confirm-movement-anchor')->exists())->toBeTrue();
    expect($day->tasks()->where('stable_key', 'check-activity-booking-needs')->exists())->toBeTrue();
});

test('reference sync backfills source urls and concrete flexible anchors', function () {
    Artisan::call('trip:import-japan-reference');

    $source = Source::query()->where('source_key', 'VISCHIO_KYOTO')->firstOrFail();
    $source->update(['url' => null]);

    $kidChoice = Activity::query()->where('stable_key', 'kyoto-kid-choice')->firstOrFail();
    $kidChoice->update(['latitude' => null, 'longitude' => null, 'reservation_url' => null]);

    Artisan::call('trip:import-japan-reference', ['--sync-reference' => true]);

    expect($source->fresh()->url)->toBe('https://www.hotelvischio-kyoto.com/en/');

    expect(Source::query()->where('source_key', 'NISHIKI')->value('url'))->toBe('https://kyoto.travel/en/destinations/kyoto-nishiki-food-market/');
    expect(Source::query()->where('source_key', 'NARA_DEER')->value('url'))->toBe('https://narashikanko.or.jp/en/feature/deer');
    expect(Source::query()->where('source_key', 'DOTONBORI')->value('url'))->toBe('https://osaka-info.jp/en/spot/tombori-river-cruise/');
    expect(Source::query()->where('source_key', 'SMARTEX_HAYATOKU')->value('url'))->toBe('https://smart-ex.jp/en/product/');

    expect($kidChoice->fresh())
        ->area->toBe('Umekoji / Kyoto Railway Museum')
        ->reservation_url->toBe('https://www.kyotorailwaymuseum.jp/en/')
        ->latitude->toBe('34.9875000')
        ->longitude->toBe('135.7433000');
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

test('trip management can edit toggle and reorder day slots', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $foodSpot = FoodSpot::query()->where('stable_key', 'tokyo-ramen-street')->firstOrFail();

    $firstSlot = $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'admin-edit-first-slot',
        'item_type' => 'note',
        'title' => 'First custom slot',
        'is_public' => true,
        'sort_order' => 1000,
        'details' => [],
    ]);

    $secondSlot = $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'admin-edit-second-slot',
        'item_type' => 'buffer',
        'title' => 'Second custom slot',
        'is_public' => true,
        'sort_order' => 1010,
        'details' => [],
    ]);

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->call('selectDay', $day->id)
        ->call('selectSlot', $firstSlot->id)
        ->set('slotEditForm.item_type', 'food')
        ->set('slotEditForm.time_label', 'lunch anchor')
        ->set('slotEditForm.title', 'Tokyo Station ramen lunch')
        ->set('slotEditForm.location_label', 'Tokyo Station')
        ->set('slotEditForm.subject_ref', $foodSpot::class.':'.$foodSpot->id)
        ->set('slotEditForm.latitude', '35.6812000')
        ->set('slotEditForm.longitude', '139.7671000')
        ->set('slotEditForm.summary', 'Use this as the day meal anchor.')
        ->set('slotEditForm.is_public', false)
        ->call('updateSlot')
        ->call('toggleSlotPublication', $firstSlot->id)
        ->call('moveSlot', $firstSlot->id, 'down')
        ->assertHasNoErrors()
        ->assertSee('Tokyo Station ramen lunch')
        ->assertSee('lunch anchor');

    expect($firstSlot->fresh())
        ->item_type->toBe('food')
        ->time_label->toBe('lunch anchor')
        ->title->toBe('Tokyo Station ramen lunch')
        ->subject_type->toBe($foodSpot::class)
        ->subject_id->toBe($foodSpot->id)
        ->latitude->toBe('35.6812000')
        ->longitude->toBe('139.7671000')
        ->is_public->toBeTrue()
        ->sort_order->toBe(1010);

    expect($secondSlot->fresh()->sort_order)->toBe(1000);
});

test('trip management can search and edit shared assets', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $hotel = Accommodation::query()->where('stable_key', 'mets-akihabara')->firstOrFail();
    $transport = TransportLeg::query()->create([
        'stable_key' => 'test-asset-edit-transport',
        'mode' => 'rail',
        'route_label' => 'Akihabara to Toyosu test hop',
        'origin' => 'Akihabara',
        'destination' => 'Toyosu',
        'notes' => null,
    ]);

    $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'admin-asset-usage-anchor',
        'item_type' => 'stay',
        'time_label' => 'arrival night',
        'title' => 'Admin asset usage anchor',
        'subject_type' => $hotel::class,
        'subject_id' => $hotel->id,
        'is_public' => true,
        'sort_order' => 1500,
        'details' => [],
    ]);

    Livewire::actingAs($user)
        ->test('pages::trips.manage.assets-panel', [
            'selectedDayId' => $day->id,
            'assetTab' => 'accommodations',
        ])
        ->set('assetSearch', 'Mets')
        ->assertSee('JR East Hotel Mets Premier Akihabara')
        ->call('selectAsset', $hotel->id)
        ->assertSee('Admin asset usage anchor')
        ->assertSee('arrival night')
        ->set('assetEditForm.neighborhood', 'Akihabara Station east')
        ->set('assetEditForm.reservation_url', 'https://hotel.example.test/reservation')
        ->set('assetEditForm.latitude', '35.6984000')
        ->set('assetEditForm.longitude', '139.7730000')
        ->set('assetEditForm.price_min_nok', 1200)
        ->set('assetEditForm.price_max_nok', 1900)
        ->set('assetEditForm.price_basis', 'per night')
        ->set('assetEditForm.price_notes', 'Watch weekend rates.')
        ->set('assetEditForm.notes', 'Use as the low-friction arrival base.')
        ->call('updateAsset')
        ->set('assetQualityFilter', 'priced')
        ->assertSee('JR East Hotel Mets Premier Akihabara')
        ->set('assetUsageFilter', 'used')
        ->assertSee('Admin asset usage anchor')
        ->set('assetTab', 'transport')
        ->set('assetQualityFilter', 'all')
        ->set('assetUsageFilter', 'all')
        ->set('assetSearch', 'Toyosu test')
        ->assertSee('Akihabara to Toyosu test hop')
        ->call('selectAsset', $transport->id)
        ->set('assetEditForm.route_label', 'Akihabara to Toyosu light transfer')
        ->set('assetEditForm.duration_label', '25-35 min')
        ->set('assetEditForm.operator', 'Tokyo Metro')
        ->set('assetEditForm.reservation_url', 'https://rail.example.test/route')
        ->set('assetEditForm.notes', 'Keep luggage complexity low.')
        ->call('updateAsset')
        ->assertHasNoErrors();

    expect($hotel->fresh())
        ->neighborhood->toBe('Akihabara Station east')
        ->reservation_url->toBe('https://hotel.example.test/reservation')
        ->latitude->toBe('35.6984000')
        ->longitude->toBe('139.7730000')
        ->price_min_nok->toBe(1200)
        ->price_max_nok->toBe(1900)
        ->price_basis->toBe('per night')
        ->price_notes->toBe('Watch weekend rates.')
        ->notes->toBe('Use as the low-friction arrival base.');

    expect($transport->fresh())
        ->route_label->toBe('Akihabara to Toyosu light transfer')
        ->duration_label->toBe('25-35 min')
        ->operator->toBe('Tokyo Metro')
        ->reservation_url->toBe('https://rail.example.test/route')
        ->notes->toBe('Keep luggage complexity low.');
});

test('trip management can attach and detach shared assets from the selected day', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-6')->firstOrFail();
    $foodSpot = FoodSpot::query()->where('stable_key', 'tokyo-ramen-street')->firstOrFail();

    $component = Livewire::actingAs($user)
        ->test('pages::trips.manage.assets-panel', [
            'selectedDayId' => $day->id,
            'assetTab' => 'food',
        ])
        ->call('selectAsset', $foodSpot->id)
        ->set('assetAttachForm.time_label', 'lunch')
        ->set('assetAttachForm.title', 'Tokyo Station ramen day anchor')
        ->set('assetAttachForm.summary', 'Use this if Toyosu timing runs long.')
        ->set('assetAttachForm.is_public', true)
        ->call('attachAssetToSelectedDay')
        ->assertHasNoErrors()
        ->assertSee('Tokyo Station ramen day anchor');

    $slot = DayItineraryItem::query()
        ->where('day_node_id', $day->id)
        ->where('subject_type', $foodSpot::class)
        ->where('subject_id', $foodSpot->id)
        ->where('title', 'Tokyo Station ramen day anchor')
        ->firstOrFail();

    expect($slot)
        ->item_type->toBe('food')
        ->time_label->toBe('lunch')
        ->summary->toBe('Use this if Toyosu timing runs long.')
        ->is_public->toBeTrue();

    $component
        ->call('detachAssetFromSelectedDay', $slot->id)
        ->assertHasNoErrors();

    expect(DayItineraryItem::query()->whereKey($slot->id)->exists())->toBeFalse();
});

test('trip management planning health detects gaps and opens targets', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-6')->firstOrFail();
    $publicGapDay = $variant->dayNodes()->where('stable_key', 'day-5')->firstOrFail();
    $day->update(['booking_priority' => 'high', 'booking_status' => 'unbooked']);
    $trip->publish();
    $variant->publish();
    $publicGapDay->itineraryItems()->update(['is_public' => false]);

    $slot = $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'planning-health-gap-slot',
        'item_type' => 'activity',
        'title' => 'Planning health gap slot',
        'is_public' => true,
        'sort_order' => 1700,
        'details' => [],
    ]);

    $asset = Accommodation::query()->create([
        'stable_key' => 'aaa-planning-health-hotel',
        'name' => 'AAA Planning Health Hotel',
        'city' => 'Tokyo',
        'country' => 'Japan',
    ]);

    Livewire::actingAs($user)
        ->test('pages::trips.manage.planning-panel', [
            'selectedTripId' => $trip->id,
            'selectedVariantId' => $variant->id,
        ])
        ->assertSee('Planning health')
        ->assertSee('High-priority day is not booked')
        ->assertSee('Public slot is missing map coordinates')
        ->assertSee('Published day has no public slots')
        ->assertSee('AAA Planning Health Hotel')
        ->assertSee('Mark planned')
        ->assertSee('Make private')
        ->set('planningSeverityFilter', 'high')
        ->assertSee('High-priority day is not booked')
        ->assertDontSee('Public slot is missing map coordinates')
        ->set('planningSeverityFilter', 'all')
        ->set('planningCategoryFilter', 'assets')
        ->assertSee('AAA Planning Health Hotel')
        ->assertDontSee('High-priority day is not booked')
        ->set('planningCategoryFilter', 'public')
        ->assertSee('Published day has no public slots')
        ->set('planningCategoryFilter', 'all')
        ->call('openPlanningIssue', 'slot-gap-'.$slot->id)
        ->assertDispatched('trip-management-open-planning-issue');

    Livewire::actingAs($user)
        ->test('pages::trips.manage', ['selectedTripId' => $trip->id])
        ->call('selectTripContext', $trip->id, $variant->id)
        ->call('openPlanningIssueTarget', [
            'target_type' => 'slot',
            'day_id' => $day->id,
            'slot_id' => $slot->id,
        ])
        ->assertSet('selectedDayId', $day->id)
        ->assertSet('selectedSlotId', $slot->id)
        ->assertSee('Planning health gap slot')
        ->call('openPlanningIssueTarget', [
            'target_type' => 'asset',
            'asset_tab' => 'accommodations',
            'asset_id' => $asset->id,
        ])
        ->assertSet('assetTab', 'accommodations')
        ->assertSet('selectedAssetId', $asset->id)
        ->assertSee('Edit shared asset');
});

test('trip management planning health quick fixes safe issues', function () {
    $user = User::factory()->create();
    $trip = Trip::query()->create([
        'slug' => 'quick-fix-trip',
        'name' => 'Quick Fix Trip',
        'currency_primary' => 'NOK',
        'currency_secondary' => 'JPY',
        'metadata' => [],
    ]);
    $variant = TripVariant::query()->create([
        'trip_id' => $trip->id,
        'slug' => 'quick-fix-timeline',
        'name' => 'Quick Fix Timeline',
        'budget_scenario' => 'value',
        'is_default' => true,
        'sort_order' => 10,
        'overrides' => [],
    ]);
    $day = DayNode::query()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'quick-fix-day',
        'day_number' => 1,
        'location' => 'Tokyo',
        'title' => 'Quick fix day',
        'node_types' => ['stay'],
        'booking_priority' => 'high',
        'booking_status' => 'unbooked',
        'details' => [],
    ]);
    $asset = Accommodation::query()->create([
        'stable_key' => 'quick-fix-hotel',
        'name' => 'Quick Fix Hotel',
        'city' => 'Tokyo',
        'country' => 'Japan',
        'latitude' => '35.6812000',
        'longitude' => '139.7671000',
    ]);
    $noCoordinateAsset = Accommodation::query()->create([
        'stable_key' => 'quick-fix-no-coordinate-hotel',
        'name' => 'Quick Fix No Coordinate Hotel',
        'city' => 'Tokyo',
        'country' => 'Japan',
    ]);

    $coordinateSlot = DayItineraryItem::query()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'stable_key' => 'quick-fix-coordinate-slot',
        'item_type' => 'stay',
        'time_label' => 'night',
        'title' => 'Needs copied coordinates',
        'subject_type' => $asset::class,
        'subject_id' => $asset->id,
        'is_public' => true,
        'sort_order' => 10,
        'details' => [],
    ]);
    $privateSlot = DayItineraryItem::query()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'stable_key' => 'quick-fix-private-slot',
        'item_type' => 'stay',
        'time_label' => 'later',
        'title' => 'Needs privacy fallback',
        'subject_type' => $noCoordinateAsset::class,
        'subject_id' => $noCoordinateAsset->id,
        'is_public' => true,
        'sort_order' => 20,
        'details' => [],
    ]);
    $timeSlot = DayItineraryItem::query()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'day_node_id' => $day->id,
        'stable_key' => 'quick-fix-time-slot',
        'item_type' => 'stay',
        'title' => 'Needs time placeholder',
        'subject_type' => $asset::class,
        'subject_id' => $asset->id,
        'latitude' => '35.6812000',
        'longitude' => '139.7671000',
        'is_public' => false,
        'sort_order' => 30,
        'details' => [],
    ]);

    Livewire::actingAs($user)
        ->test('pages::trips.manage.planning-panel', [
            'selectedTripId' => $trip->id,
            'selectedVariantId' => $variant->id,
        ])
        ->assertSee('Planning health')
        ->call('quickFixPlanningIssue', 'day-high-unbooked-'.$day->id)
        ->call('quickFixPlanningIssue', 'slot-gap-'.$coordinateSlot->id)
        ->call('quickFixPlanningIssue', 'slot-gap-'.$privateSlot->id)
        ->call('quickFixPlanningIssue', 'slot-gap-'.$timeSlot->id)
        ->assertHasNoErrors();

    expect($day->fresh()->booking_status)->toBe('planned');
    expect($coordinateSlot->fresh())
        ->latitude->toBe('35.6812000')
        ->longitude->toBe('139.7671000');
    expect($privateSlot->fresh()->is_public)->toBeFalse();
    expect($timeSlot->fresh()->time_label)->toBe('needs timing');
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

test('authenticated admins can preview unpublished public pages without exposing private slots', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();

    $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'preview-private-slot',
        'item_type' => 'note',
        'title' => 'Preview private slot',
        'is_public' => false,
        'sort_order' => 2000,
        'details' => [],
    ]);

    $previewUrl = route('trips.public', [
        'trip' => $trip,
        'timeline' => $variant->slug,
        'day' => $day->stable_key,
        'preview' => 1,
    ]);

    $this->get($previewUrl)->assertNotFound();

    $this->actingAs($user)
        ->get($previewUrl)
        ->assertOk()
        ->assertSee('Preview mode')
        ->assertSee($variant->name)
        ->assertSee('Tokyo Station first easy day')
        ->assertDontSee('Preview private slot');
});

test('public timeline preserves selected timeline day and slot query state', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $slot = $day->publicItineraryItems()->firstOrFail();
    $trip->publish();
    $variant->publish();

    $this->get(route('trips.public', [
        'trip' => $trip,
        'timeline' => $variant->slug,
        'day' => $day->stable_key,
        'slot' => $slot->stable_key,
    ]))
        ->assertOk()
        ->assertSee('Share current view')
        ->assertSee($slot->title)
        ->assertSee($slot->summary)
        ->assertSee('slot='.$slot->stable_key, false);
});

test('public day page expands selected slot and links back to timeline state', function () {
    Artisan::call('trip:import-japan-reference');

    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();
    $day = $variant->dayNodes()->where('stable_key', 'day-4')->firstOrFail();
    $slot = $day->publicItineraryItems()->firstOrFail();
    $trip->publish();
    $variant->publish();

    $this->get(route('trips.public.days.show', [
        'trip' => $trip,
        'variant' => $variant,
        'dayNode' => $day,
        'slot' => $slot->stable_key,
    ]))
        ->assertOk()
        ->assertSee('Share day')
        ->assertSee($slot->summary)
        ->assertSee('day='.$day->stable_key, false)
        ->assertSee('slot='.$slot->stable_key, false);
});

test('trip management exposes authenticated public preview links', function () {
    Artisan::call('trip:import-japan-reference');

    $user = User::factory()->create();
    $trip = Trip::query()->where('slug', 'japan-summer-2027')->firstOrFail();
    $trip->unpublish();
    $trip->variants()->get()->each->unpublish();
    $variant = $trip->variants()->where('slug', 'value-copenhagen-stopover')->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::trips.manage')
        ->set('selectedTripId', $trip->id)
        ->set('selectedVariantId', $variant->id)
        ->assertSee('Preview timeline')
        ->assertSee('preview=1', false)
        ->assertSee('timeline='.$variant->slug, false);
});

test('public day map shows missing coordinate fallbacks for visible slots', function () {
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
        'stable_key' => 'public-missing-coordinate-slot',
        'item_type' => 'activity',
        'title' => 'Public missing coordinate stop',
        'is_public' => true,
        'sort_order' => 2200,
        'details' => [],
    ]);

    $day->itineraryItems()->create([
        'trip_id' => $trip->id,
        'trip_variant_id' => $variant->id,
        'stable_key' => 'private-missing-coordinate-slot',
        'item_type' => 'activity',
        'title' => 'Private missing coordinate stop',
        'is_public' => false,
        'sort_order' => 2210,
        'details' => [],
    ]);

    $this->get(route('trips.public.days.show', [$trip, $variant, $day]))
        ->assertOk()
        ->assertSee('Needs map pin')
        ->assertSee('Public missing coordinate stop')
        ->assertDontSee('Private missing coordinate stop');
});

function writeTripPlannerJournalTinyPng(string $path): void
{
    $image = imagecreatetruecolor(2, 2);
    imagepng($image, $path);
    imagedestroy($image);
}
