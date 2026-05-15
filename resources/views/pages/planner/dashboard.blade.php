<?php

use App\Models\DayNode;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tokyo Trip Planner')] class extends Component {
    public ?string $tripSlug = null;
    public ?string $variantSlug = null;
    public string $costTier = 'value';
    public string $currency = 'NOK';
    public string $nodeType = 'all';
    public string $priority = 'all';
    public string $view = 'timeline';
    public ?int $selectedDayId = null;
    public bool $showDayTimeline = false;

    public function mount(): void
    {
        $trip = Trip::query()->orderBy('starts_on')->first();

        if (! $trip) {
            return;
        }

        $this->tripSlug = $trip->slug;
        $this->variantSlug = $trip->defaultVariant()?->slug;
    }

    public function updatedTripSlug(): void
    {
        $this->variantSlug = $this->trip?->defaultVariant()?->slug;
        $this->selectedDayId = null;
        $this->showDayTimeline = false;
    }

    public function updatedVariantSlug(): void
    {
        $this->selectedDayId = null;
        $this->showDayTimeline = false;
    }

    public function selectDay(int $dayId): void
    {
        if ($this->selectedDayId !== $dayId) {
            $this->showDayTimeline = false;
        }

        $this->selectedDayId = $dayId;
    }

    public function toggleDayTimeline(): void
    {
        $this->showDayTimeline = ! $this->showDayTimeline;
    }

    #[Computed]
    public function trips(): EloquentCollection
    {
        return Trip::query()->withCount('variants')->orderBy('starts_on')->orderBy('name')->get();
    }

    #[Computed]
    public function trip(): ?Trip
    {
        if (! $this->tripSlug) {
            return null;
        }

        return Trip::query()->where('slug', $this->tripSlug)->first();
    }

    #[Computed]
    public function variants(): EloquentCollection
    {
        return $this->trip?->variants()->get() ?? new EloquentCollection();
    }

    #[Computed]
    public function variant(): ?TripVariant
    {
        if (! $this->trip || ! $this->variantSlug) {
            return null;
        }

        return $this->trip->variants()->where('slug', $this->variantSlug)->first();
    }

    #[Computed]
    public function days(): EloquentCollection
    {
        if (! $this->variant) {
            return new EloquentCollection();
        }

        return $this->variant->dayNodes()
            ->with(['accommodations', 'transportLegs', 'activities', 'foodSpots', 'sources', 'itineraryItems.subject', 'tasks'])
            ->when($this->nodeType !== 'all', fn ($query) => $query->whereJsonContains('node_types', $this->nodeType))
            ->when($this->priority !== 'all', fn ($query) => $query->where('booking_priority', $this->priority))
            ->orderBy('day_number')
            ->get();
    }

    #[Computed]
    public function selectedDay(): ?DayNode
    {
        if ($this->selectedDayId === null) {
            return null;
        }

        return $this->days->firstWhere('id', $this->selectedDayId);
    }

    #[Computed]
    public function totals(): array
    {
        $days = $this->variant?->dayNodes()->get() ?? collect();
        $minColumn = $this->costTier === 'premium' ? 'cost_premium_min_nok' : 'cost_value_min_nok';
        $maxColumn = $this->costTier === 'premium' ? 'cost_premium_max_nok' : 'cost_value_max_nok';

        return [
            'nights' => max($days->count() - 1, 0),
            'min' => (int) $days->sum($minColumn),
            'max' => (int) $days->sum($maxColumn),
            'booked' => $days->where('booking_status', 'booked')->count(),
            'unbooked' => $days->where('booking_status', '!=', 'booked')->count(),
            'next_priority' => $days->where('booking_status', '!=', 'booked')->sortBy(fn (DayNode $day) => match ($day->booking_priority) {
                'high' => 1,
                'medium' => 2,
                default => 3,
            })->first()?->title ?? 'None',
        ];
    }

    #[Computed]
    public function mapPayload(): array
    {
        if (! $this->variant) {
            return ['points' => [], 'routes' => []];
        }

        $points = $this->variant->routePoints()
            ->get()
            ->map(fn ($point) => [
                'name' => $point->name,
                'category' => $point->category,
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
                'route_group' => $point->route_group,
                'sequence' => $point->sequence,
            ])
            ->values()
            ->all();

        $routes = collect($points)
            ->groupBy('route_group')
            ->map(fn (Collection $group) => $group->sortBy('sequence')->map(fn (array $point) => [$point['lat'], $point['lng']])->values()->all())
            ->values()
            ->all();

        return ['points' => $points, 'routes' => $routes];
    }

    public function costRange(DayNode $day): string
    {
        return $this->costTier === 'premium' ? $day->premiumCostRange() : $day->valueCostRange();
    }

    public function dayIndicatorColor(DayNode $day): string
    {
        $nodeTypes = collect($day->node_types);

        if ($nodeTypes->contains('travel')) {
            return 'sky';
        }

        if ($nodeTypes->contains('stay')) {
            return 'teal';
        }

        return 'amber';
    }

    public function slotColor(string $type): string
    {
        return match ($type) {
            'stay' => 'teal',
            'move' => 'sky',
            'food' => 'rose',
            'buffer' => 'zinc',
            default => 'amber',
        };
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-4">
        <div class="sticky top-0 z-10 py-3 backdrop-blur">
            @if ($this->trips->isNotEmpty())
                <div class="mb-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                    <flux:select wire:model.live="tripSlug" :label="__('Trip')">
                        @foreach ($this->trips as $trip)
                            <flux:select.option value="{{ $trip->slug }}">{{ $trip->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="variantSlug" :label="__('Timeline')">
                        @foreach ($this->variants as $variant)
                            <flux:select.option value="{{ $variant->slug }}">{{ $variant->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:modal.trigger name="planner-filters">
                        <flux:button class="w-full lg:w-auto" icon="funnel">{{ __('Advanced filters') }}</flux:button>
                    </flux:modal.trigger>
                </div>

                <flux:modal name="planner-filters" flyout variant="floating" class="md:w-96">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Planner filters') }}</flux:heading>
                            <flux:text class="mt-2">{{ __('Tune the admin timeline without crowding the main planner view.') }}</flux:text>
                        </div>

                        <flux:select wire:model.live="costTier" :label="__('Cost tier')">
                            <flux:select.option value="value">{{ __('Value') }}</flux:select.option>
                            <flux:select.option value="premium">{{ __('Premium') }}</flux:select.option>
                        </flux:select>

                        <flux:select wire:model.live="nodeType" :label="__('Node type')">
                            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                            <flux:select.option value="travel">{{ __('Travel') }}</flux:select.option>
                            <flux:select.option value="stay">{{ __('Stay') }}</flux:select.option>
                            <flux:select.option value="activity">{{ __('Activity') }}</flux:select.option>
                        </flux:select>

                        <flux:select wire:model.live="priority" :label="__('Priority')">
                            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                            <flux:select.option value="high">{{ __('High') }}</flux:select.option>
                            <flux:select.option value="medium">{{ __('Medium') }}</flux:select.option>
                            <flux:select.option value="low">{{ __('Low') }}</flux:select.option>
                        </flux:select>

                        <flux:separator />

                        <flux:button class="w-full" icon="wrench-screwdriver" :href="route('trips.manage')" wire:navigate>{{ __('Manage trips') }}</flux:button>
                    </div>
                </flux:modal>
            @endif

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <flux:heading size="xl">{{ $this->trip?->name ?? __('Trip planner') }}</flux:heading>
                    <flux:text size="sm" class="mt-1 max-w-3xl">{{ $this->trip?->summary ?? __('Import or create a trip to start planning timelines.') }}</flux:text>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm lg:grid-cols-4">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-zinc-500">{{ __('Nights') }}</div>
                        <div class="font-semibold">{{ $this->totals['nights'] }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-zinc-500">{{ __('Modeled cost') }}</div>
                        <div class="font-semibold">{{ number_format($this->totals['min'], 0, '.', ' ') }} - {{ number_format($this->totals['max'], 0, '.', ' ') }} NOK</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-zinc-500">{{ __('Booked') }}</div>
                        <div class="font-semibold">{{ $this->totals['booked'] }} / {{ $this->totals['booked'] + $this->totals['unbooked'] }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-zinc-500">{{ __('Next') }}</div>
                        <div class="max-w-40 truncate font-semibold">{{ $this->totals['next_priority'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if ($this->trips->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <flux:heading>{{ __('No trips yet') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Run php artisan trip:import-japan-reference or create a trip in Manage trips.') }}</flux:text>
                <flux:button class="mt-4" icon="wrench-screwdriver" :href="route('trips.manage')" wire:navigate>{{ __('Manage trips') }}</flux:button>
            </div>
        @else
            <div class="space-y-4">
                <main class="min-w-0 space-y-4">
                    <flux:tabs wire:model.live="view">
                        <flux:tab name="timeline" icon="calendar-days">{{ __('Timeline') }}</flux:tab>
                        <flux:tab name="map" icon="map">{{ __('Map') }}</flux:tab>
                    </flux:tabs>

                    @if ($view === 'map')
                        <flux:card>
                            <div
                                wire:ignore
                                x-data
                                x-init="$nextTick(() => window.renderTripMap?.($refs.map, @js($this->mapPayload)))"
                                x-effect="$nextTick(() => window.renderTripMap?.($refs.map, @js($this->mapPayload)))"
                            >
                                <div x-ref="map" class="h-[520px] overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700"></div>
                            </div>
                        </flux:card>
                    @else
                        <div
                            x-data
                            x-init="$nextTick(() => {
                                const selected = document.getElementById('admin-day-{{ $this->selectedDay?->stable_key }}');
                                selected?.scrollIntoView({ block: 'center' });
                            })"
                        >
                            <flux:timeline size="lg" align="start" class="[--flux-timeline-item-gap:1rem]">
                            @foreach ($this->days as $day)
                                @php
                                    $nodeTypes = collect($day->node_types);
                                @endphp

                                <flux:timeline.item align="start" wire:key="admin-day-item-{{ $day->id }}">
                                    <flux:timeline.indicator :color="$this->dayIndicatorColor($day)">
                                        @if ($nodeTypes->contains('travel'))
                                            <flux:icon.paper-airplane variant="micro" />
                                        @elseif ($nodeTypes->contains('stay'))
                                            <flux:icon.building-office-2 variant="micro" />
                                        @else
                                            <flux:icon.sparkles variant="micro" />
                                        @endif
                                    </flux:timeline.indicator>

                                    <flux:timeline.content>
                                        <button
                                            id="admin-day-{{ $day->stable_key }}"
                                            type="button"
                                            wire:click="selectDay({{ $day->id }})"
                                            class="block w-full rounded-lg border p-4 text-left transition hover:border-teal-600 {{ $this->selectedDay?->id === $day->id ? 'border-teal-700 bg-teal-50 dark:border-teal-300 dark:bg-teal-950/40' : 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900' }}"
                                        >
                                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-500">{{ __('Day :day', ['day' => $day->day_number]) }} · {{ $day->starts_on?->format('M j') }}</div>
                                                    <div class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $day->title }}</div>
                                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $day->location }}</div>
                                                </div>

                                                <div class="flex flex-wrap gap-2">
                                                    <flux:badge size="sm" :color="$day->booking_priority === 'high' ? 'red' : ($day->booking_priority === 'medium' ? 'amber' : 'zinc')">
                                                        {{ $day->booking_priority }}
                                                    </flux:badge>
                                                    <flux:badge size="sm" :color="$day->booking_status === 'booked' ? 'green' : 'zinc'">
                                                        {{ $day->booking_status }}
                                                    </flux:badge>
                                                    @foreach ($day->node_types as $type)
                                                        <flux:badge size="sm">{{ $type }}</flux:badge>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="mt-4 grid gap-2 text-sm text-zinc-600 dark:text-zinc-300 sm:grid-cols-3">
                                                <div>
                                                    <span class="font-medium text-zinc-950 dark:text-white">{{ __('Cost') }}:</span>
                                                    {{ $this->costRange($day) }}
                                                </div>
                                                <div>
                                                    <span class="font-medium text-zinc-950 dark:text-white">{{ __('Slots') }}:</span>
                                                    {{ $day->itineraryItems->count() }}
                                                </div>
                                                <div>
                                                    <span class="font-medium text-zinc-950 dark:text-white">{{ __('Open tasks') }}:</span>
                                                    {{ $day->tasks->where('status', 'open')->count() }}
                                                </div>
                                            </div>
                                        </button>
                                    </flux:timeline.content>
                                </flux:timeline.item>

                                @if ($this->selectedDay?->id === $day->id)
                                    <flux:timeline.item wire:key="admin-day-detail-{{ $day->id }}">
                                        <flux:timeline.block>
                                            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                                    <div class="max-w-3xl">
                                                        <flux:heading>{{ $day->title }}</flux:heading>
                                                        <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $day->summary }}</p>
                                                    </div>

                                                    <div class="flex flex-wrap gap-2">
                                                        <flux:badge :color="$day->booking_priority === 'high' ? 'red' : ($day->booking_priority === 'medium' ? 'amber' : 'zinc')">
                                                            {{ __('Priority: :priority', ['priority' => $day->booking_priority]) }}
                                                        </flux:badge>
                                                        <flux:badge :color="$day->booking_status === 'booked' ? 'green' : 'zinc'">
                                                            {{ __('Status: :status', ['status' => $day->booking_status]) }}
                                                        </flux:badge>
                                                    </div>
                                                </div>

                                                <div class="mt-5 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                                                    <section>
                                                        <div class="flex items-center gap-2 font-semibold text-zinc-950 dark:text-white">
                                                            <flux:icon.paper-airplane class="size-4 text-sky-600 dark:text-sky-300" />
                                                            {{ __('Route') }}
                                                        </div>
                                                        <div class="mt-2 space-y-1 text-zinc-600 dark:text-zinc-300">
                                                            @forelse ($day->transportLegs as $transportLeg)
                                                                <div>{{ $transportLeg->route_label }} @if ($transportLeg->duration_label) · {{ $transportLeg->duration_label }} @endif</div>
                                                            @empty
                                                                <div>{{ $day->transport_method ?? __('No major route notes for this day.') }}</div>
                                                            @endforelse
                                                        </div>
                                                    </section>

                                                    <section>
                                                        <div class="flex items-center gap-2 font-semibold text-zinc-950 dark:text-white">
                                                            <flux:icon.building-office-2 class="size-4 text-teal-600 dark:text-teal-300" />
                                                            {{ __('Stay') }}
                                                        </div>
                                                        <div class="mt-2 space-y-1 text-zinc-600 dark:text-zinc-300">
                                                            @forelse ($day->accommodations as $accommodation)
                                                                <div>{{ $accommodation->name }} @if ($accommodation->neighborhood) · {{ $accommodation->neighborhood }} @endif</div>
                                                            @empty
                                                                <div>{{ __('No overnight stay attached.') }}</div>
                                                            @endforelse
                                                        </div>
                                                    </section>

                                                    <section>
                                                        <div class="flex items-center gap-2 font-semibold text-zinc-950 dark:text-white">
                                                            <flux:icon.sparkles class="size-4 text-amber-600 dark:text-amber-300" />
                                                            {{ __('Activities') }}
                                                        </div>
                                                        <div class="mt-2 space-y-1 text-zinc-600 dark:text-zinc-300">
                                                            @forelse ($day->activities as $activity)
                                                                <div>{{ $activity->name }} @if ($activity->area) · {{ $activity->area }} @endif</div>
                                                            @empty
                                                                <div>{{ __('Flexible day.') }}</div>
                                                            @endforelse
                                                        </div>
                                                    </section>

                                                    <section>
                                                        <div class="flex items-center gap-2 font-semibold text-zinc-950 dark:text-white">
                                                            <flux:icon.map-pin class="size-4 text-rose-600 dark:text-rose-300" />
                                                            {{ __('Food') }}
                                                        </div>
                                                        <div class="mt-2 space-y-1 text-zinc-600 dark:text-zinc-300">
                                                            @forelse ($day->foodSpots as $foodSpot)
                                                                <div>{{ $foodSpot->name }} @if ($foodSpot->area) · {{ $foodSpot->area }} @endif</div>
                                                            @empty
                                                                <div>{{ __('Food is flexible for this day.') }}</div>
                                                            @endforelse
                                                        </div>
                                                    </section>
                                                </div>

                                                <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                                                    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                            <div>
                                                                <div class="font-semibold text-zinc-950 dark:text-white">{{ __('Day timeline') }}</div>
                                                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Admin view includes public and private itinerary anchors.') }}</p>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <flux:badge>{{ $day->itineraryItems->count() }} {{ __('slots') }}</flux:badge>
                                                                <flux:button size="xs" wire:click="toggleDayTimeline">
                                                                    {{ $showDayTimeline ? __('Hide day timeline') : __('Show day timeline') }}
                                                                </flux:button>
                                                            </div>
                                                        </div>

                                                        @if ($showDayTimeline)
                                                            <div class="mt-5">
                                                                <flux:timeline size="sm" align="start" class="[--flux-timeline-item-gap:0.75rem]">
                                                                    @forelse ($day->itineraryItems as $slot)
                                                                        <flux:timeline.item wire:key="admin-day-slot-{{ $slot->id }}">
                                                                            <flux:timeline.indicator :color="$this->slotColor($slot->item_type)">
                                                                                @if ($slot->item_type === 'stay')
                                                                                    <flux:icon.building-office-2 variant="micro" />
                                                                                @elseif ($slot->item_type === 'move')
                                                                                    <flux:icon.paper-airplane variant="micro" />
                                                                                @elseif ($slot->item_type === 'food')
                                                                                    <flux:icon.map-pin variant="micro" />
                                                                                @else
                                                                                    <flux:icon.sparkles variant="micro" />
                                                                                @endif
                                                                            </flux:timeline.indicator>

                                                                            <flux:timeline.content>
                                                                                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                                                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                                                        <div class="min-w-0">
                                                                                            <div class="flex flex-wrap items-center gap-2">
                                                                                                <flux:badge size="sm">{{ $slot->item_type }}</flux:badge>
                                                                                                @if ($slot->time_label)
                                                                                                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $slot->time_label }}</span>
                                                                                                @endif
                                                                                                @unless ($slot->is_public)
                                                                                                    <flux:badge size="sm" color="zinc">{{ __('Private') }}</flux:badge>
                                                                                                @endunless
                                                                                            </div>
                                                                                            <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $slot->title }}</div>
                                                                                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                                                                {{ collect([$slot->location_label, $slot->subject?->name ?? $slot->subject?->route_label])->filter()->join(' · ') }}
                                                                                            </div>
                                                                                            @if ($slot->summary)
                                                                                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $slot->summary }}</p>
                                                                                            @endif
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </flux:timeline.content>
                                                                        </flux:timeline.item>
                                                                    @empty
                                                                        <flux:timeline.item>
                                                                            <flux:timeline.indicator color="zinc">
                                                                                <flux:icon.sparkles variant="micro" />
                                                                            </flux:timeline.indicator>
                                                                            <flux:timeline.content>
                                                                                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('No day slots have been added yet.') }}</p>
                                                                            </flux:timeline.content>
                                                                        </flux:timeline.item>
                                                                    @endforelse
                                                                </flux:timeline>
                                                            </div>
                                                        @endif
                                                    </section>

                                                    <aside class="space-y-4">
                                                        <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                                            <div class="font-semibold text-zinc-950 dark:text-white">{{ __('Planning snapshot') }}</div>
                                                            <dl class="mt-3 space-y-2 text-zinc-600 dark:text-zinc-300">
                                                                <div class="flex justify-between gap-3">
                                                                    <dt>{{ __('Cost') }}</dt>
                                                                    <dd class="font-medium text-zinc-950 dark:text-white">{{ $this->costRange($day) }}</dd>
                                                                </div>
                                                                <div class="flex justify-between gap-3">
                                                                    <dt>{{ __('Rain backup') }}</dt>
                                                                    <dd class="max-w-44 text-right">{{ data_get($day->details, 'rain_backup', __('TBD')) }}</dd>
                                                                </div>
                                                                @if ($day->cancellation_window_at)
                                                                    <div class="flex justify-between gap-3">
                                                                        <dt>{{ __('Cancel by') }}</dt>
                                                                        <dd>{{ $day->cancellation_window_at->format('M j') }}</dd>
                                                                    </div>
                                                                @endif
                                                            </dl>
                                                        </section>

                                                        <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                                            <div class="font-semibold text-zinc-950 dark:text-white">{{ __('Open tasks') }}</div>
                                                            <div class="mt-3 space-y-2">
                                                                @forelse ($day->tasks->where('status', 'open')->take(4) as $task)
                                                                    <div class="rounded-md bg-white p-3 dark:bg-zinc-900">
                                                                        <div class="flex flex-wrap gap-2">
                                                                            <flux:badge size="sm">{{ $task->task_type }}</flux:badge>
                                                                            <flux:badge size="sm" color="{{ $task->priority === 'high' ? 'red' : ($task->priority === 'medium' ? 'amber' : 'zinc') }}">{{ $task->priority }}</flux:badge>
                                                                        </div>
                                                                        <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $task->title }}</div>
                                                                    </div>
                                                                @empty
                                                                    <p class="text-zinc-600 dark:text-zinc-300">{{ __('No open planning tasks for this day.') }}</p>
                                                                @endforelse
                                                            </div>
                                                        </section>

                                                        @if ($day->sources->isNotEmpty())
                                                            <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                                                <div class="font-semibold text-zinc-950 dark:text-white">{{ __('Sources') }}</div>
                                                                <div class="mt-3 flex flex-wrap gap-2">
                                                                    @foreach ($day->sources as $source)
                                                                        <flux:badge size="sm">{{ $source->source_key }}</flux:badge>
                                                                    @endforeach
                                                                </div>
                                                            </section>
                                                        @endif
                                                    </aside>
                                                </div>
                                            </div>
                                        </flux:timeline.block>
                                    </flux:timeline.item>
                                @endif
                            @endforeach
                            </flux:timeline>
                        </div>
                    @endif
                </main>
            </div>
        @endif
</section>
