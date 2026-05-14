<?php

use App\Models\DayNode;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Trip timeline')] class extends Component {
    public int $tripId;
    public ?string $variantSlug = null;
    public string $view = 'timeline';
    #[Url(as: 'day', except: '')]
    public string $selectedDayKey = '';

    public function mount(Trip $trip): void
    {
        abort_unless($trip->is_public, 404);

        $variant = $trip->publishedVariants()->first();

        abort_unless($variant, 404);

        $this->tripId = $trip->id;
        $this->variantSlug = $variant->slug;
    }

    public function updatedVariantSlug(): void
    {
        $this->selectedDayKey = '';
    }

    public function selectDay(string $dayKey): void
    {
        $this->selectedDayKey = $this->selectedDayKey === $dayKey ? '' : $dayKey;
    }

    #[Computed]
    public function trip(): Trip
    {
        return Trip::query()
            ->where('is_public', true)
            ->findOrFail($this->tripId);
    }

    #[Computed]
    public function variants(): EloquentCollection
    {
        return $this->trip->publishedVariants()->get();
    }

    #[Computed]
    public function variant(): TripVariant
    {
        return $this->trip->publishedVariants()
            ->where('slug', $this->variantSlug)
            ->firstOrFail();
    }

    #[Computed]
    public function days(): EloquentCollection
    {
        return $this->variant->dayNodes()
            ->with(['accommodations', 'transportLegs', 'activities', 'foodSpots'])
            ->orderBy('day_number')
            ->get();
    }

    #[Computed]
    public function selectedDay(): ?DayNode
    {
        if ($this->selectedDayKey === '') {
            return null;
        }

        return $this->days->firstWhere('stable_key', $this->selectedDayKey);
    }

    #[Computed]
    public function mapPayload(): array
    {
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
}; ?>

<main class="min-h-screen">
    <header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-sm font-medium uppercase tracking-wide text-teal-700 dark:text-teal-300">{{ __('Published trip timeline') }}</div>
                    <h1 class="mt-2 text-3xl font-semibold tracking-normal text-zinc-950 dark:text-white">{{ $this->trip->name }}</h1>
                    <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ $this->trip->summary }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                        @if ($this->trip->starts_on && $this->trip->ends_on)
                            <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ $this->trip->starts_on->format('M j, Y') }} - {{ $this->trip->ends_on->format('M j, Y') }}</span>
                        @endif

                        @if ($this->trip->arrival_preference)
                            <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ __('Arrival: :airport', ['airport' => $this->trip->arrival_preference]) }}</span>
                        @endif
                    </div>
                </div>

                <div class="w-full rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 lg:w-80">
                    <flux:select wire:model.live="variantSlug" :label="__('Timeline')">
                        @foreach ($this->variants as $variant)
                            <flux:select.option value="{{ $variant->slug }}">{{ $variant->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($this->variant->description)
                        <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $this->variant->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <section class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="min-w-0 space-y-5">
            <flux:tabs wire:model.live="view">
                <flux:tab name="timeline" icon="calendar-days">{{ __('Timeline') }}</flux:tab>
                <flux:tab name="map" icon="map">{{ __('Map') }}</flux:tab>
            </flux:tabs>

            @if ($view === 'map')
                <div class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <div
                        wire:ignore
                        x-data
                        x-init="$nextTick(() => window.renderTripMap?.($refs.map, @js($this->mapPayload)))"
                        x-effect="$nextTick(() => window.renderTripMap?.($refs.map, @js($this->mapPayload)))"
                    >
                        <div x-ref="map" class="h-[520px] overflow-hidden rounded-md"></div>
                    </div>
                </div>
            @else
                <div
                    x-data
                    x-init="$nextTick(() => {
                        const key = new URLSearchParams(window.location.search).get('day');
                        if (! key) return;
                        document.getElementById(`day-${key}`)?.scrollIntoView({ block: 'center' });
                    })"
                >
                    <flux:timeline size="lg" align="start" class="[--flux-timeline-item-gap:1rem]">
                    @foreach ($this->days as $day)
                        @php
                            $nodeTypes = collect($day->node_types);
                            $indicatorColor = $nodeTypes->contains('travel')
                                ? 'sky'
                                : ($nodeTypes->contains('stay')
                                    ? 'teal'
                                    : 'amber');
                        @endphp

                        <flux:timeline.item align="start">
                            <flux:timeline.indicator :color="$indicatorColor">
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
                                    id="day-{{ $day->stable_key }}"
                                    type="button"
                                    wire:click="selectDay('{{ $day->stable_key }}')"
                                    class="block w-full rounded-lg border p-4 text-left transition hover:border-teal-600 {{ $this->selectedDay?->id === $day->id ? 'border-teal-700 bg-teal-50 dark:border-teal-300 dark:bg-teal-950/40' : 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900' }}"
                                >
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="text-sm font-medium text-zinc-500">{{ __('Day :day', ['day' => $day->day_number]) }} · {{ $day->starts_on?->format('M j') }}</div>
                                            <div class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $day->title }}</div>
                                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $day->location }}</div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($day->node_types as $type)
                                                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $type }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </button>
                            </flux:timeline.content>
                        </flux:timeline.item>

                        @if ($this->selectedDay?->id === $day->id)
                            <flux:timeline.item>
                                <flux:timeline.block>
                                    <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="max-w-2xl">
                                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $day->title }}</h2>
                                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $day->summary }}</p>
                                            </div>

                                            <flux:button
                                                size="sm"
                                                icon="arrow-top-right-on-square"
                                                :href="route('trips.public.days.show', [$this->trip, $this->variant, $day])"
                                            >
                                                {{ __('Open full day') }}
                                            </flux:button>
                                        </div>

                                        <div class="mt-5 grid gap-4 text-sm md:grid-cols-2">
                                            <section>
                                                <div class="flex items-center gap-2 font-semibold text-zinc-950 dark:text-white">
                                                    <flux:icon.paper-airplane class="size-4 text-sky-600 dark:text-sky-300" />
                                                    {{ __('Route') }}
                                                </div>
                                                <div class="mt-2 space-y-1 text-zinc-600 dark:text-zinc-300">
                                                    @forelse ($day->transportLegs as $transportLeg)
                                                        <div>{{ $transportLeg->route_label }} @if ($transportLeg->duration_label) · {{ $transportLeg->duration_label }} @endif</div>
                                                    @empty
                                                        <div>{{ __('No major route notes for this day.') }}</div>
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

                                        <div class="mt-5 rounded-lg bg-zinc-50 p-4 text-sm leading-6 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                            <div class="font-semibold text-zinc-950 dark:text-white">{{ __('Rain backup') }}</div>
                                            <p class="mt-1">{{ data_get($day->details, 'rain_backup', __('Keep the day light and flexible.')) }}</p>
                                        </div>
                                    </div>
                                </flux:timeline.block>
                            </flux:timeline.item>
                        @endif
                    @endforeach
                    </flux:timeline>
                </div>
            @endif
        </div>
    </section>
</main>
