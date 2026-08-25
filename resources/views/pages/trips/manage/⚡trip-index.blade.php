<?php

use App\Models\Trip;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $selectedTripId = null;
    public ?int $selectedVariantId = null;

    public array $tripForm = ['name' => '', 'summary' => '', 'starts_on' => '', 'ends_on' => '', 'arrival_preference' => 'HND'];
    public array $variantForm = ['name' => '', 'budget_scenario' => 'value', 'stopover_type' => '', 'flight_strategy' => ''];

    public function mount(?int $selectedTripId = null, ?int $selectedVariantId = null): void
    {
        $this->selectedTripId = $selectedTripId;
        $this->selectedVariantId = $selectedVariantId;
    }

    public function updatedSelectedTripId(): void
    {
        $this->selectedVariantId = $this->selectedTrip?->defaultVariant()?->id;
        $this->dispatchSelectionChanged();
    }

    public function updatedSelectedVariantId(): void
    {
        $this->dispatchSelectionChanged();
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

        $trip = Trip::query()->create([
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
        $this->tripForm = ['name' => '', 'summary' => '', 'starts_on' => '', 'ends_on' => '', 'arrival_preference' => 'HND'];
        $this->dispatchSelectionChanged();

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
        $this->dispatchSelectionChanged();

        Flux::toast(variant: 'success', text: __('Timeline created.'));
    }

    #[Computed]
    public function trips(): EloquentCollection
    {
        return Trip::query()
            ->withCount(['variants', 'dayNodes'])
            ->orderBy('starts_on')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedTrip(): ?Trip
    {
        return $this->selectedTripId ? Trip::query()->find($this->selectedTripId) : null;
    }

    #[Computed]
    public function variants(): EloquentCollection
    {
        return $this->selectedTrip?->variants()->withCount('dayNodes')->orderBy('sort_order')->get() ?? new EloquentCollection();
    }

    private function dispatchSelectionChanged(): void
    {
        $this->dispatch('trip-management-selection-changed', tripId: $this->selectedTripId, variantId: $this->selectedVariantId);
    }
}; ?>

<flux:card>
    <div class="flex items-start justify-between gap-3">
        <div>
            <flux:heading>{{ __('Trips') }}</flux:heading>
            <flux:text>{{ __('Pick a trip, then work inside focused tabs.') }}</flux:text>
        </div>
        <flux:modal.trigger name="create-trip">
            <flux:button size="sm" icon="plus">{{ __('Trip') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($this->trips as $trip)
            <button
                type="button"
                wire:key="trip-index-{{ $trip->id }}"
                wire:click="$set('selectedTripId', {{ $trip->id }})"
                class="block w-full rounded-lg border p-3 text-left transition hover:border-teal-600 {{ $this->selectedTripId === $trip->id ? 'border-teal-700 bg-teal-50 dark:border-teal-300 dark:bg-teal-950/40' : 'border-zinc-200 dark:border-zinc-700' }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate font-medium text-zinc-950 dark:text-white">{{ $trip->name }}</div>
                        <div class="mt-1 text-sm text-zinc-500">
                            {{ collect([$trip->starts_on?->format('M j, Y'), $trip->ends_on?->format('M j, Y')])->filter()->join(' - ') ?: __('Dates TBD') }}
                        </div>
                    </div>
                    <flux:badge size="sm">{{ $trip->variants_count }} {{ __('timelines') }}</flux:badge>
                </div>
            </button>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                {{ __('No trips yet.') }}
            </div>
        @endforelse
    </div>

    @if ($this->selectedTrip)
        <flux:separator class="my-5" />

        <div class="flex items-center justify-between gap-3">
            <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Timelines') }}</div>
            <flux:modal.trigger name="create-timeline">
                <flux:button size="sm" icon="plus">{{ __('Timeline') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="mt-3 space-y-2">
            @forelse ($this->variants as $variant)
                <button
                    type="button"
                    wire:key="timeline-index-{{ $variant->id }}"
                    wire:click="$set('selectedVariantId', {{ $variant->id }})"
                    class="block w-full rounded-lg border px-3 py-2 text-left text-sm transition hover:border-teal-600 {{ $this->selectedVariantId === $variant->id ? 'border-teal-700 bg-teal-50 dark:border-teal-300 dark:bg-teal-950/40' : 'border-zinc-200 dark:border-zinc-700' }}"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-950 dark:text-white">{{ $variant->name }}</div>
                            <div class="mt-1 text-zinc-500">{{ $variant->budget_scenario }} · {{ $variant->travelerAccessLabel() }}</div>
                        </div>
                        <flux:badge size="sm">{{ $variant->day_nodes_count }} {{ __('days') }}</flux:badge>
                    </div>
                </button>
            @empty
                <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    {{ __('No timelines yet.') }}
                </div>
            @endforelse
        </div>
    @endif

    <flux:modal name="create-trip" class="md:w-[32rem]">
        <form wire:submit="createTrip" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New trip') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create a separate planning workspace with its own timelines.') }}</flux:text>
            </div>

            <flux:input wire:model="tripForm.name" :label="__('Name')" />
            <flux:textarea wire:model="tripForm.summary" :label="__('Summary')" rows="3" />
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="tripForm.starts_on" :label="__('Starts')" type="date" />
                <flux:input wire:model="tripForm.ends_on" :label="__('Ends')" type="date" />
            </div>
            <flux:input wire:model="tripForm.arrival_preference" :label="__('Arrival preference')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="plus">{{ __('Create trip') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="create-timeline" class="md:w-[32rem]">
        <form wire:submit="createVariant" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New timeline') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Add another route option inside the selected trip.') }}</flux:text>
            </div>

            <flux:input wire:model="variantForm.name" :label="__('Name')" />
            <flux:select wire:model="variantForm.budget_scenario" :label="__('Budget')">
                <flux:select.option value="value">{{ __('Value') }}</flux:select.option>
                <flux:select.option value="premium">{{ __('Premium') }}</flux:select.option>
            </flux:select>
            <flux:input wire:model="variantForm.stopover_type" :label="__('Stopover')" />
            <flux:textarea wire:model="variantForm.flight_strategy" :label="__('Flight strategy')" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="plus">{{ __('Create timeline') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:card>
