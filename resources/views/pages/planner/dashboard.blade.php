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

    public function mount(): void
    {
        $trip = Trip::query()->orderBy('starts_on')->first();

        if (! $trip) {
            return;
        }

        $this->tripSlug = $trip->slug;
        $this->variantSlug = $trip->defaultVariant()?->slug;
        $this->selectedDayId = $trip->defaultVariant()?->dayNodes()->orderBy('day_number')->value('id');
    }

    public function updatedTripSlug(): void
    {
        $this->variantSlug = $this->trip?->defaultVariant()?->slug;
        $this->selectedDayId = $this->days->first()?->id;
    }

    public function updatedVariantSlug(): void
    {
        $this->selectedDayId = $this->days->first()?->id;
    }

    public function selectDay(int $dayId): void
    {
        $this->selectedDayId = $dayId;
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
            ->with(['accommodations', 'transportLegs', 'activities', 'foodSpots', 'sources'])
            ->when($this->nodeType !== 'all', fn ($query) => $query->whereJsonContains('node_types', $this->nodeType))
            ->when($this->priority !== 'all', fn ($query) => $query->where('booking_priority', $this->priority))
            ->orderBy('day_number')
            ->get();
    }

    #[Computed]
    public function selectedDay(): ?DayNode
    {
        return $this->days->firstWhere('id', $this->selectedDayId) ?? $this->days->first();
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
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-4">
        <div class="sticky top-0 z-10 border-b border-zinc-200 bg-white/95 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <flux:heading size="xl">{{ $this->trip?->name ?? __('Trip planner') }}</flux:heading>
                    <flux:text class="mt-1 max-w-3xl">{{ $this->trip?->summary ?? __('Import or create a trip to start planning timelines.') }}</flux:text>
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
            <div class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)_360px]">
                <aside class="space-y-4">
                    <flux:card class="space-y-4">
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

                        <flux:button class="w-full" icon="wrench-screwdriver" :href="route('trips.manage')" wire:navigate>{{ __('Manage trips') }}</flux:button>
                    </flux:card>
                </aside>

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
                        <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                            @foreach ($this->days as $day)
                                <button
                                    type="button"
                                    wire:click="selectDay({{ $day->id }})"
                                    class="rounded-lg border p-4 text-left transition hover:border-zinc-400 {{ $this->selectedDay?->id === $day->id ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Day') }} {{ $day->day_number }} · {{ $day->starts_on?->format('M j') }}</div>
                                            <div class="mt-1 font-semibold">{{ $day->title }}</div>
                                            <div class="mt-1 text-sm text-zinc-500">{{ $day->location }}</div>
                                        </div>
                                        <flux:badge size="sm" :color="$day->booking_priority === 'high' ? 'red' : ($day->booking_priority === 'medium' ? 'amber' : 'zinc')">
                                            {{ $day->booking_priority }}
                                        </flux:badge>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($day->node_types as $type)
                                            <flux:badge size="sm">{{ $type }}</flux:badge>
                                        @endforeach
                                    </div>
                                    <div class="mt-4 text-sm font-medium">{{ $this->costRange($day) }}</div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </main>

                <aside>
                    <flux:card class="sticky top-32 space-y-4">
                        @if ($this->selectedDay)
                            <div>
                                <flux:heading>{{ $this->selectedDay->title }}</flux:heading>
                                <flux:text>{{ __('Day :day · :location', ['day' => $this->selectedDay->day_number, 'location' => $this->selectedDay->location]) }}</flux:text>
                            </div>

                            <flux:separator />

                            <div class="space-y-3 text-sm">
                                <div>
                                    <div class="font-medium">{{ __('Route notes') }}</div>
                                    <div class="text-zinc-500">{{ $this->selectedDay->transport_method ?? __('No transport attached.') }}</div>
                                </div>

                                <div>
                                    <div class="font-medium">{{ __('Accommodation') }}</div>
                                    <div class="text-zinc-500">{{ $this->selectedDay->accommodations->pluck('name')->join(', ') ?: __('None') }}</div>
                                </div>

                                <div>
                                    <div class="font-medium">{{ __('Activities') }}</div>
                                    <div class="text-zinc-500">{{ $this->selectedDay->activities->pluck('name')->join(', ') ?: __('None') }}</div>
                                </div>

                                <div>
                                    <div class="font-medium">{{ __('Food') }}</div>
                                    <div class="text-zinc-500">{{ $this->selectedDay->foodSpots->pluck('name')->join(', ') ?: __('None') }}</div>
                                </div>

                                <div>
                                    <div class="font-medium">{{ __('Rain backup') }}</div>
                                    <div class="text-zinc-500">{{ data_get($this->selectedDay->details, 'rain_backup', __('TBD')) }}</div>
                                </div>
                            </div>

                            <flux:separator />

                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->selectedDay->sources as $source)
                                    <flux:badge size="sm">{{ $source->source_key }}</flux:badge>
                                @endforeach
                            </div>
                        @else
                            <flux:text>{{ __('Select a day to see details.') }}</flux:text>
                        @endif
                    </flux:card>
                </aside>
            </div>
        @endif
</section>
