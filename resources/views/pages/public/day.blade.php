<?php

use App\Models\DayNode;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Day details')] class extends Component {
    public int $tripId;
    public int $variantId;
    public int $dayNodeId;

    public function mount(Trip $trip, TripVariant $variant, DayNode $dayNode): void
    {
        abort_unless($trip->is_public, 404);
        abort_unless($variant->trip_id === $trip->id && $variant->is_public, 404);
        abort_unless($dayNode->trip_id === $trip->id && $dayNode->trip_variant_id === $variant->id, 404);

        $this->tripId = $trip->id;
        $this->variantId = $variant->id;
        $this->dayNodeId = $dayNode->id;
    }

    #[Computed]
    public function trip(): Trip
    {
        return Trip::query()
            ->where('is_public', true)
            ->findOrFail($this->tripId);
    }

    #[Computed]
    public function variant(): TripVariant
    {
        return $this->trip->publishedVariants()->findOrFail($this->variantId);
    }

    #[Computed]
    public function day(): DayNode
    {
        return $this->variant->dayNodes()
            ->with(['accommodations', 'transportLegs', 'activities', 'foodSpots', 'publicItineraryItems.subject'])
            ->findOrFail($this->dayNodeId);
    }

    #[Computed]
    public function days(): Collection
    {
        return $this->variant->dayNodes()
            ->orderBy('day_number')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function previousDay(): ?DayNode
    {
        $index = $this->days->search(fn (DayNode $day) => $day->id === $this->day->id);

        if ($index === false || $index === 0) {
            return null;
        }

        return $this->days->get($index - 1);
    }

    #[Computed]
    public function nextDay(): ?DayNode
    {
        $index = $this->days->search(fn (DayNode $day) => $day->id === $this->day->id);

        if ($index === false) {
            return null;
        }

        return $this->days->get($index + 1);
    }

    #[Computed]
    public function mapPayload(): array
    {
        $slotPoints = $this->day->publicItineraryItems
            ->map(function ($slot) {
                $latitude = $slot->latitude ?? $slot->subject?->latitude;
                $longitude = $slot->longitude ?? $slot->subject?->longitude;

                if ($latitude === null || $longitude === null) {
                    return null;
                }

                return [
                    'name' => $slot->title,
                    'category' => $slot->item_type,
                    'lat' => (float) $latitude,
                    'lng' => (float) $longitude,
                    'route_group' => 'day',
                    'sequence' => $slot->sort_order,
                ];
            })
            ->filter()
            ->values();

        $points = $slotPoints->isNotEmpty()
            ? $slotPoints->all()
            : collect()
                ->merge($this->day->accommodations)
                ->merge($this->day->activities)
                ->merge($this->day->foodSpots)
                ->filter(fn ($item) => $item->latitude !== null && $item->longitude !== null)
                ->map(fn ($item) => [
                    'name' => $item->name,
                    'category' => class_basename($item),
                    'lat' => (float) $item->latitude,
                    'lng' => (float) $item->longitude,
                    'route_group' => 'day',
                    'sequence' => 1,
                ])
                ->values()
                ->all();

        $routes = collect($this->day->transportLegs)
            ->pluck('geo_path')
            ->filter()
            ->map(fn (array $path) => collect($path)->map(fn (array $point) => [(float) $point[0], (float) $point[1]])->all())
            ->values()
            ->all();

        if ($routes === [] && count($points) > 1) {
            $routes = [
                collect($points)->map(fn (array $point) => [$point['lat'], $point['lng']])->all(),
            ];
        }

        return ['points' => $points, 'routes' => $routes];
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

    public function nodeTypesLabel(): string
    {
        return collect($this->day->node_types)->map(fn (string $type) => ucfirst($type))->join(' · ');
    }
}; ?>

<main class="min-h-screen bg-white dark:bg-zinc-950">
    <header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <flux:button size="sm" icon="arrow-left" :href="route('trips.public', $this->trip)">
                {{ __('Back to timeline') }}
            </flux:button>

            <div class="mt-6 max-w-3xl">
                <div class="text-sm font-medium uppercase tracking-wide text-teal-700 dark:text-teal-300">
                    {{ $this->trip->name }} · {{ $this->variant->name }}
                </div>
                <h1 class="mt-2 text-3xl font-semibold tracking-normal text-zinc-950 dark:text-white">{{ $this->day->title }}</h1>
                <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ $this->day->summary }}</p>

                <div class="mt-5 flex flex-wrap gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ __('Day :day', ['day' => $this->day->day_number]) }}</span>
                    @if ($this->day->starts_on)
                        <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ $this->day->starts_on->format('M j, Y') }}</span>
                    @endif
                    <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ $this->day->location }}</span>
                    <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ $this->nodeTypesLabel() }}</span>
                </div>
            </div>
        </div>
    </header>

    <section class="mx-auto grid max-w-6xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:px-8">
        <div class="space-y-6">
            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Day timeline') }}</h2>
                <div class="mt-5">
                    <flux:timeline size="lg" align="start" class="[--flux-timeline-item-gap:1rem]">
                        @forelse ($this->day->publicItineraryItems as $slot)
                            <flux:timeline.item>
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
                                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">{{ $slot->item_type }}</span>
                                                    @if ($slot->time_label)
                                                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $slot->time_label }}</span>
                                                    @endif
                                                </div>
                                                <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $slot->title }}</div>
                                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                    {{ collect([$slot->location_label, $slot->subject?->name ?? $slot->subject?->route_label])->filter()->join(' · ') }}
                                                </div>
                                            </div>
                                        </div>

                                        @if ($slot->summary)
                                            <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $slot->summary }}</p>
                                        @endif
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
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Route and movement') }}</h2>
                <div class="mt-4 grid gap-3">
                    @forelse ($this->day->transportLegs as $transportLeg)
                        <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                            <div class="font-medium text-zinc-950 dark:text-white">{{ $transportLeg->route_label }}</div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ ucfirst($transportLeg->mode) }}
                                @if ($transportLeg->duration_label)
                                    · {{ $transportLeg->duration_label }}
                                @endif
                                @if ($transportLeg->operator)
                                    · {{ $transportLeg->operator }}
                                @endif
                            </div>
                            @if ($transportLeg->notes)
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $transportLeg->notes }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('No major route movement planned for this day.') }}</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Stay') }}</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @forelse ($this->day->accommodations as $accommodation)
                        <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                            <div class="font-medium text-zinc-950 dark:text-white">{{ $accommodation->name }}</div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ collect([$accommodation->neighborhood, $accommodation->city])->filter()->join(' · ') }}
                            </div>
                            @if ($accommodation->breakfast_note || $accommodation->dinner_note)
                                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ collect([$accommodation->breakfast_note, $accommodation->dinner_note])->filter()->join(' · ') }}
                                </div>
                            @endif
                            @if ($accommodation->notes)
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $accommodation->notes }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('No overnight stay attached.') }}</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Activities') }}</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @forelse ($this->day->activities as $activity)
                        <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                            <div class="font-medium text-zinc-950 dark:text-white">{{ $activity->name }}</div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ collect([$activity->area, $activity->city])->filter()->join(' · ') }}
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                                @if ($activity->rain_fit)
                                    <span class="rounded-full bg-white px-2 py-1 dark:bg-zinc-900">{{ __('Rain: :fit', ['fit' => $activity->rain_fit]) }}</span>
                                @endif
                                @if ($activity->age_fit)
                                    <span class="rounded-full bg-white px-2 py-1 dark:bg-zinc-900">{{ __('Age fit: :fit', ['fit' => $activity->age_fit]) }}</span>
                                @endif
                            </div>
                            @if ($activity->notes)
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $activity->notes }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('This day is intentionally flexible.') }}</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Food ideas') }}</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @forelse ($this->day->foodSpots as $foodSpot)
                        <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                            <div class="font-medium text-zinc-950 dark:text-white">{{ $foodSpot->name }}</div>
                            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ collect([$foodSpot->area, $foodSpot->city, $foodSpot->default_meal_type])->filter()->join(' · ') }}
                            </div>
                            @if ($foodSpot->notes)
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $foodSpot->notes }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Food is flexible for this day.') }}</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Map') }}</h2>
                <div
                    class="mt-4"
                    wire:ignore
                    x-data
                    x-init="$nextTick(() => window.renderTripMap?.($refs.map, @js($this->mapPayload)))"
                >
                    <div x-ref="map" class="h-80 overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700"></div>
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            <div class="sticky top-4 space-y-4">
                <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Rain backup') }}</h2>
                    <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ data_get($this->day->details, 'rain_backup', __('Keep the day light and flexible.')) }}</p>
                </section>

                <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Nearby days') }}</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @if ($this->previousDay)
                            <a class="block rounded-lg bg-zinc-50 p-3 hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700" href="{{ route('trips.public.days.show', [$this->trip, $this->variant, $this->previousDay]) }}">
                                <div class="text-zinc-500">{{ __('Previous') }}</div>
                                <div class="font-medium text-zinc-950 dark:text-white">{{ __('Day :day', ['day' => $this->previousDay->day_number]) }} · {{ $this->previousDay->title }}</div>
                            </a>
                        @endif

                        @if ($this->nextDay)
                            <a class="block rounded-lg bg-zinc-50 p-3 hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700" href="{{ route('trips.public.days.show', [$this->trip, $this->variant, $this->nextDay]) }}">
                                <div class="text-zinc-500">{{ __('Next') }}</div>
                                <div class="font-medium text-zinc-950 dark:text-white">{{ __('Day :day', ['day' => $this->nextDay->day_number]) }} · {{ $this->nextDay->title }}</div>
                            </a>
                        @endif
                    </div>
                </section>
            </div>
        </aside>
    </section>
</main>
