<?php

use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\Trip;
use App\Models\TripVariant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage trips')] class extends Component {
    public ?int $selectedTripId = null;
    public ?int $selectedVariantId = null;
    public ?int $selectedDayId = null;
    public ?int $selectedSlotId = null;
    public ?int $selectedAssetId = null;
    public string $activeManageTab = 'timeline';
    public string $assetTab = 'accommodations';

    public array $tripForm = ['name' => '', 'summary' => '', 'starts_on' => '', 'ends_on' => '', 'arrival_preference' => 'HND'];
    public array $variantForm = ['name' => '', 'budget_scenario' => 'value', 'stopover_type' => '', 'flight_strategy' => ''];
    public ?int $selectedJournalEntryId = null;

    public function mount(): void
    {
        $trip = Trip::query()->orderBy('starts_on')->first();

        $this->selectedTripId = $trip?->id;
        $this->selectedVariantId = $trip?->defaultVariant()?->id;
        $this->selectedDayId = $trip?->defaultVariant()?->dayNodes()->orderBy('day_number')->value('id');
    }

    #[On('trip-management-selection-changed')]
    public function selectTripContext(?int $tripId = null, ?int $variantId = null): void
    {
        $this->selectedTripId = $tripId;
        $this->selectedVariantId = $variantId ?: $this->selectedTrip?->defaultVariant()?->id;
        $this->selectedDayId = $this->selectedVariant?->dayNodes()->orderBy('day_number')->value('id');
        $this->selectedSlotId = null;
        $this->selectedAssetId = null;
    }

    #[On('trip-management-open-planning-issue')]
    public function openPlanningIssueTarget(array $issue): void
    {
        if (($issue['target_type'] ?? null) === 'slot') {
            $this->selectDay((int) $issue['day_id']);
            $this->selectSlot((int) $issue['slot_id']);
            $this->activeManageTab = 'timeline';

            return;
        }

        if (($issue['target_type'] ?? null) === 'day') {
            $this->selectDay((int) $issue['day_id']);
            $this->activeManageTab = 'timeline';

            return;
        }

        if (($issue['target_type'] ?? null) === 'asset') {
            $this->assetTab = (string) $issue['asset_tab'];
            $this->selectedAssetId = (int) $issue['asset_id'];
            $this->activeManageTab = 'assets';

            return;
        }

        if (($issue['target_type'] ?? null) === 'journal') {
            $this->selectedJournalEntryId = (int) $issue['journal_entry_id'];
            $this->activeManageTab = 'journal';
        }
    }

    #[On('trip-management-day-selection-changed')]
    public function syncSelectedDayFromTimeline(int $dayId): void
    {
        $this->selectedDayId = $dayId;
        $this->selectedSlotId = null;
    }

    #[On('trip-management-start-day-journal')]
    public function startDayJournalFromTimeline(int $dayId): void
    {
        $this->selectDay($dayId);
        $this->activeManageTab = 'journal';
    }

    #[On('trip-management-start-slot-journal')]
    public function startSlotJournalFromTimeline(int $dayId, int $slotId): void
    {
        $this->selectDay($dayId);
        $this->selectSlot($slotId);
        $this->activeManageTab = 'journal';
    }

    public function createTrip(): void
    {
        $validated = $this->validate([
            'tripForm.name' => ['required', 'string', 'max:255'],
            'tripForm.summary' => ['nullable', 'string', 'max:2000'],
            'tripForm.starts_on' => ['nullable', 'date'],
            'tripForm.ends_on' => ['nullable', 'date'],
            'tripForm.arrival_preference' => ['nullable', 'string', 'max:50'],
        ]);

        $trip = Trip::create([
            'slug' => Str::slug($validated['tripForm']['name']).'-'.Str::lower(Str::random(6)),
            'name' => $validated['tripForm']['name'],
            'summary' => $validated['tripForm']['summary'],
            'starts_on' => $validated['tripForm']['starts_on'] ?: null,
            'ends_on' => $validated['tripForm']['ends_on'] ?: null,
            'currency_primary' => 'NOK',
            'currency_secondary' => 'JPY',
            'arrival_preference' => $validated['tripForm']['arrival_preference'],
            'metadata' => [],
        ]);

        $this->selectedTripId = $trip->id;
        $this->selectedVariantId = null;
        $this->selectedDayId = null;
        $this->tripForm = ['name' => '', 'summary' => '', 'starts_on' => '', 'ends_on' => '', 'arrival_preference' => 'HND'];

        Flux::toast(variant: 'success', text: __('Trip created.'));
    }

    public function createVariant(): void
    {
        if (! $this->selectedTrip) {
            return;
        }

        $validated = $this->validate([
            'variantForm.name' => ['required', 'string', 'max:255'],
            'variantForm.budget_scenario' => ['required', 'string', 'max:50'],
            'variantForm.stopover_type' => ['nullable', 'string', 'max:100'],
            'variantForm.flight_strategy' => ['nullable', 'string', 'max:500'],
        ]);

        $variant = $this->selectedTrip->variants()->create([
            'slug' => Str::slug($validated['variantForm']['name']).'-'.Str::lower(Str::random(6)),
            'name' => $validated['variantForm']['name'],
            'budget_scenario' => $validated['variantForm']['budget_scenario'],
            'stopover_type' => $validated['variantForm']['stopover_type'],
            'flight_strategy' => $validated['variantForm']['flight_strategy'],
            'description' => '',
            'is_default' => $this->selectedTrip->variants()->doesntExist(),
            'sort_order' => ($this->selectedTrip->variants()->max('sort_order') ?? 0) + 10,
            'overrides' => [],
        ]);

        $this->selectedVariantId = $variant->id;
        $this->variantForm = ['name' => '', 'budget_scenario' => 'value', 'stopover_type' => '', 'flight_strategy' => ''];

        Flux::toast(variant: 'success', text: __('Timeline created.'));
    }

    public function toggleTripPublication(): void
    {
        if (! $this->selectedTrip) {
            return;
        }

        if ($this->selectedTrip->is_public || $this->selectedTrip->visibility === 'public') {
            $this->selectedTrip->unpublish();

            Flux::toast(text: __('Trip unpublished.'));

            return;
        }

        $this->selectedTrip->publish();

        Flux::toast(variant: 'success', text: __('Trip published.'));
    }

    public function setTripVisibility(string $visibility): void
    {
        if (! $this->selectedTrip || ! in_array($visibility, ['private', 'family', 'public'], true)) {
            return;
        }

        $this->selectedTrip->setVisibility($visibility);

        Flux::toast(variant: 'success', text: __('Trip visibility updated.'));
    }

    public function setTripFrontendAccess(string $access): void
    {
        if (! $this->selectedTrip || ! in_array($access, ['private', 'authenticated', 'public'], true)) {
            return;
        }

        $this->selectedTrip->setFrontendAccess($access);

        Flux::toast(variant: 'success', text: __('Traveler access updated.'));
    }

    public function toggleVariantPublication(int $variantId): void
    {
        $variant = $this->selectedTrip?->variants()->whereKey($variantId)->first();

        if (! $variant) {
            return;
        }

        if ($variant->is_public || $variant->visibility === 'public') {
            $variant->unpublish();

            Flux::toast(text: __('Timeline unpublished.'));

            return;
        }

        $variant->publish();

        Flux::toast(variant: 'success', text: __('Timeline published.'));
    }

    public function setVariantVisibility(int $variantId, string $visibility): void
    {
        $variant = $this->selectedTrip?->variants()->whereKey($variantId)->first();

        if (! $variant || ! in_array($visibility, ['private', 'family', 'public'], true)) {
            return;
        }

        $variant->setVisibility($visibility);

        Flux::toast(variant: 'success', text: __('Timeline visibility updated.'));
    }

    public function setVariantFrontendAccess(int $variantId, string $access): void
    {
        $variant = $this->selectedTrip?->variants()->whereKey($variantId)->first();

        if (! $variant || ! in_array($access, ['private', 'authenticated', 'public'], true)) {
            return;
        }

        $variant->setFrontendAccess($access);

        Flux::toast(variant: 'success', text: __('Timeline traveler access updated.'));
    }

    public function selectDay(int $dayId): void
    {
        $this->selectedDayId = $dayId;
        $this->selectedSlotId = null;
    }








    public function selectSlot(int $slotId): void
    {
        $slot = $this->selectedDay?->itineraryItems()->whereKey($slotId)->first();

        if (! $slot) {
            return;
        }

        $this->selectedSlotId = $slot->id;
    }
























    #[Computed]
    public function trips(): EloquentCollection
    {
        return Trip::query()->withCount('variants')->orderBy('starts_on')->orderBy('name')->get();
    }

    #[Computed]
    public function selectedTrip(): ?Trip
    {
        return $this->selectedTripId ? Trip::find($this->selectedTripId) : null;
    }


    #[Computed]
    public function variants(): EloquentCollection
    {
        return $this->selectedTrip?->variants()->get() ?? new EloquentCollection();
    }

    #[Computed]
    public function selectedVariant(): ?TripVariant
    {
        return $this->selectedVariantId ? TripVariant::find($this->selectedVariantId) : null;
    }

    #[Computed]
    public function days(): EloquentCollection
    {
        return $this->selectedVariant?->dayNodes()->orderBy('day_number')->get() ?? new EloquentCollection();
    }

    #[Computed]
    public function selectedDay(): ?DayNode
    {
        return $this->selectedDayId
            ? DayNode::query()->with(['itineraryItems.subject', 'tasks'])->find($this->selectedDayId)
            : null;
    }

    #[Computed]
    public function selectedSlot(): ?DayItineraryItem
    {
        return $this->selectedSlotId && $this->selectedDay
            ? $this->selectedDay->itineraryItems->firstWhere('id', $this->selectedSlotId)
            : null;
    }














    public function publicTripUrl(): ?string
    {
        if (! $this->selectedTrip) {
            return null;
        }

        return route('trips.public', $this->selectedTrip);
    }

    public function publicPreviewUrl(?TripVariant $variant = null): ?string
    {
        if (! $this->selectedTrip) {
            return null;
        }

        return route('trips.public', array_filter([
            'trip' => $this->selectedTrip,
            'timeline' => $variant?->slug ?? $this->selectedVariant?->slug,
            'day' => $this->selectedDay?->stable_key,
            'slot' => $this->selectedSlot?->stable_key,
            'preview' => 1,
        ], fn ($value) => $value !== null));
    }





    public function updatedSelectedTripId(): void
    {
        $this->selectedVariantId = $this->selectedTrip?->defaultVariant()?->id;
        $this->selectedDayId = $this->selectedVariant?->dayNodes()->orderBy('day_number')->value('id');
        $this->selectedSlotId = null;
    }

    public function updatedSelectedVariantId(): void
    {
        $this->selectedDayId = $this->selectedVariant?->dayNodes()->orderBy('day_number')->value('id');
        $this->selectedSlotId = null;
    }






























}; ?>

<section class="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6">
        <div class="max-w-3xl">
            <div class="text-sm font-medium text-zinc-500">{{ __('Planning workspace') }}</div>
            <flux:heading size="xl">{{ __('Manage trips') }}</flux:heading>
            <flux:text>{{ __('Set the route, then work through its timeline, shared assets, journal, planning health, and traveler access.') }}</flux:text>
        </div>

        <div class="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
            <aside class="space-y-6 xl:sticky xl:top-4 xl:self-start">
                <livewire:pages::trips.manage.trip-index
                    :selected-trip-id="$selectedTripId"
                    :selected-variant-id="$selectedVariantId"
                />

                <livewire:pages::trips.manage.loyalty-panel
                    :selected-trip-id="$selectedTripId"
                    :key="'loyalty-'.$selectedTripId"
                />

            </aside>

            <div class="space-y-6">
                <div class="rounded-lg border border-zinc-200 bg-white/95 p-4 shadow-sm backdrop-blur-xl dark:border-zinc-700 dark:bg-zinc-900/95 motion-reduce:backdrop-blur-none">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-zinc-500">{{ __('Current context') }}</div>
                            <flux:heading>{{ $this->selectedTrip?->name ?? __('No trip selected') }}</flux:heading>
                            <flux:text>
                                {{ $this->selectedVariant ? __('Timeline: :name', ['name' => $this->selectedVariant->name]) : __('Choose a trip and timeline from the index.') }}
                            </flux:text>
                        </div>

                        @if ($this->selectedTrip)
                            <div class="flex flex-wrap gap-2">
                                @if ($this->publicTripUrl())
                                    <flux:button size="sm" icon="arrow-top-right-on-square" :href="$this->publicTripUrl()" target="_blank">
                                        {{ __('Open frontend') }}
                                    </flux:button>
                                @endif
                                @if ($this->publicPreviewUrl())
                                    <flux:button size="sm" icon="eye" :href="$this->publicPreviewUrl()" target="_blank">
                                        {{ __('Preview timeline') }}
                                    </flux:button>
                                @endif
                            </div>
                        @endif
                    </div>

                    <flux:tabs class="mt-5" wire:model.live="activeManageTab">
                        <flux:tab name="timeline" icon="calendar-days">{{ __('Timeline') }}</flux:tab>
                        <flux:tab name="assets" icon="squares-2x2">{{ __('Assets') }}</flux:tab>
                        <flux:tab name="journal" icon="book-open">{{ __('Journal') }}</flux:tab>
                        <flux:tab name="planning" icon="clipboard-document-check">{{ __('Planning') }}</flux:tab>
                        <flux:tab name="publishing" icon="globe-alt">{{ __('Publishing') }}</flux:tab>
                    </flux:tabs>
                </div>

                @if ($activeManageTab === 'publishing')
                    <livewire:pages::trips.manage.publishing-panel
                        :selected-trip-id="$selectedTripId"
                        :selected-variant-id="$selectedVariantId"
                        :key="'publishing-'.$selectedTripId.'-'.$selectedVariantId"
                    />
                @endif

                @if ($activeManageTab === 'planning')
                    <livewire:pages::trips.manage.planning-panel
                        :selected-trip-id="$selectedTripId"
                        :selected-variant-id="$selectedVariantId"
                        :key="'planning-'.$selectedTripId.'-'.$selectedVariantId"
                    />
                @endif

                @if ($activeManageTab === 'journal')
                    <livewire:pages::trips.manage.journal-panel
                        :selected-trip-id="$selectedTripId"
                        :selected-variant-id="$selectedVariantId"
                        :selected-day-id="$selectedDayId"
                        :selected-slot-id="$selectedSlotId"
                        :selected-journal-entry-id="$selectedJournalEntryId"
                        :key="'journal-'.$selectedTripId.'-'.$selectedVariantId.'-'.$selectedDayId.'-'.$selectedSlotId.'-'.$selectedJournalEntryId"
                    />
                @endif

                @if ($activeManageTab === 'timeline')
                    <livewire:pages::trips.manage.timeline-panel
                        :selected-trip-id="$selectedTripId"
                        :selected-variant-id="$selectedVariantId"
                        :selected-day-id="$selectedDayId"
                        :selected-slot-id="$selectedSlotId"
                        :key="'timeline-'.$selectedTripId.'-'.$selectedVariantId.'-'.$selectedDayId.'-'.$selectedSlotId"
                    />
                @endif

                @if ($activeManageTab === 'assets')
                    <livewire:pages::trips.manage.assets-panel
                        :selected-day-id="$selectedDayId"
                        :selected-asset-id="$selectedAssetId"
                        :asset-tab="$assetTab"
                        :key="'assets-'.$selectedDayId.'-'.$selectedAssetId.'-'.$assetTab"
                    />
                @endif
            </div>
        </div>
</section>
