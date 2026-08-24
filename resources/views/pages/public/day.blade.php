<?php

use App\Models\DayNode;
use App\Models\DayItineraryItem;
use App\Models\JournalEntry;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Day details')] class extends Component {
    public int $tripId;
    public int $variantId;
    public int $dayNodeId;
    #[Url(as: 'slot', except: '')]
    public string $selectedSlotKey = '';
    #[Url(as: 'preview', except: false)]
    public bool $preview = false;

    public function mount(Trip $trip, TripVariant $variant, DayNode $dayNode): void
    {
        abort_unless($this->canPreview() || $trip->isVisibleTo(auth()->user()), 404);
        abort_unless($variant->trip_id === $trip->id && ($this->canPreview() || $variant->isVisibleTo(auth()->user())), 404);
        abort_unless($dayNode->trip_id === $trip->id && $dayNode->trip_variant_id === $variant->id, 404);

        $this->tripId = $trip->id;
        $this->variantId = $variant->id;
        $this->dayNodeId = $dayNode->id;
    }

    #[Computed]
    public function trip(): Trip
    {
        return Trip::query()
            ->when(! $this->canPreview(), fn ($query) => $query->where(function ($query): void {
                $query->where('is_public', true)
                    ->orWhere('visibility', 'public')
                    ->orWhere('frontend_access', 'public');

                if (auth()->check()) {
                    $query->orWhere('visibility', 'family')
                        ->orWhere('frontend_access', 'authenticated');
                }
            }))
            ->findOrFail($this->tripId);
    }

    #[Computed]
    public function variant(): TripVariant
    {
        return ($this->canPreview() ? $this->trip->variants() : $this->trip->visibleVariantsFor(auth()->user()))
            ->findOrFail($this->variantId);
    }

    #[Computed]
    public function day(): DayNode
    {
        return $this->variant->dayNodes()
            ->with(['accommodations', 'transportLegs', 'activities', 'foodSpots', 'publicItineraryItems.subject'])
            ->findOrFail($this->dayNodeId);
    }

    #[Computed]
    public function selectedSlot(): ?DayItineraryItem
    {
        if ($this->selectedSlotKey === '') {
            return null;
        }

        return $this->day->publicItineraryItems->firstWhere('stable_key', $this->selectedSlotKey);
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
                    'selected' => $this->selectedSlot?->id === $slot->id,
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
                    'selected' => false,
                ])
                ->values()
                ->all();

        $routes = collect($this->day->transportLegs)
            ->filter(fn ($transportLeg) => filled($transportLeg->geo_path))
            ->filter()
            ->map(fn ($transportLeg) => [
                'label' => $transportLeg->route_label,
                'path' => collect($transportLeg->geo_path)->map(fn (array $point) => [(float) $point[0], (float) $point[1]])->all(),
            ])
            ->values()
            ->all();

        if ($routes === [] && count($points) > 1) {
            $routes = [
                [
                    'label' => __('Day flow'),
                    'path' => collect($points)->map(fn (array $point) => [$point['lat'], $point['lng']])->all(),
                ],
            ];
        }

        return ['points' => $points, 'routes' => $routes];
    }

    #[Computed]
    public function publicSlotsMissingCoordinates(): Collection
    {
        return $this->day->publicItineraryItems
            ->filter(function (DayItineraryItem $slot): bool {
                $latitude = $slot->latitude ?? $slot->subject?->latitude;
                $longitude = $slot->longitude ?? $slot->subject?->longitude;

                return $latitude === null || $longitude === null;
            })
            ->values();
    }

    #[Computed]
    public function journalEntries(): Collection
    {
        return $this->day->journalEntries()
            ->visibleTo(auth()->user())
            ->with(['dayItineraryItem', 'media'])
            ->get();
    }

    public function slotJournalEntries(DayItineraryItem $slot): Collection
    {
        return $this->journalEntries
            ->filter(fn (JournalEntry $entry): bool => $entry->day_itinerary_item_id === $slot->id)
            ->values();
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

    public function selectSlot(string $slotKey): void
    {
        $this->selectedSlotKey = $this->selectedSlotKey === $slotKey ? '' : $slotKey;
    }

    public function canPreview(): bool
    {
        return $this->preview && auth()->check();
    }

    public function timelineUrl(): string
    {
        return route('trips.public', array_filter([
            'trip' => $this->trip,
            'timeline' => $this->variant->slug,
            'day' => $this->day->stable_key,
            'slot' => $this->selectedSlotKey ?: null,
            'preview' => $this->canPreview() ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    public function dayUrl(?DayNode $day = null): string
    {
        return route('trips.public.days.show', array_filter([
            'trip' => $this->trip,
            'variant' => $this->variant,
            'dayNode' => $day ?? $this->day,
            'slot' => $day && $day->id !== $this->day->id ? null : ($this->selectedSlotKey ?: null),
            'preview' => $this->canPreview() ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    public function nodeTypesLabel(): string
    {
        return collect($this->day->node_types)->map(fn (string $type) => ucfirst($type))->join(' · ');
    }
}; ?>

<main class="min-h-screen bg-white dark:bg-zinc-950">
    <header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-2">
                <flux:button size="sm" icon="arrow-left" :href="$this->timelineUrl()">
                {{ __('Back to timeline') }}
                </flux:button>

                <flux:button size="sm" icon="link" :href="$this->dayUrl()">
                    {{ __('Share day') }}
                </flux:button>

                @if ($this->canPreview())
                    <flux:badge color="amber">{{ __('Preview mode') }}</flux:badge>
                @endif
            </div>

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
                                    <button
                                        id="slot-{{ $slot->stable_key }}"
                                        wire:key="public-day-slot-{{ $slot->id }}"
                                        type="button"
                                        wire:click="selectSlot('{{ $slot->stable_key }}')"
                                        class="block w-full rounded-lg border p-4 text-left transition hover:border-teal-600 {{ $this->selectedSlot?->id === $slot->id ? 'border-teal-700 bg-teal-50 dark:border-teal-300 dark:bg-teal-950/40' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}"
                                    >
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

                                        @if ($this->selectedSlot?->id === $slot->id)
                                            <div class="mt-4 rounded-lg bg-white p-3 text-sm leading-6 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                                                @if ($slot->summary)
                                                    <p>{{ $slot->summary }}</p>
                                                @endif

                                                @if ($slot->subject)
                                                    <div class="mt-3 font-medium text-zinc-950 dark:text-white">
                                                        {{ $slot->subject->name ?? $slot->subject->route_label }}
                                                    </div>
                                                @endif

                                                @if ($slot->location_label)
                                                    <div class="mt-1">{{ $slot->location_label }}</div>
                                                @endif

                                                @if ($this->slotJournalEntries($slot)->isNotEmpty())
                                                    <div class="mt-4 space-y-2">
                                                        <div class="font-medium text-zinc-950 dark:text-white">{{ __('Updates') }}</div>
                                                        @foreach ($this->slotJournalEntries($slot) as $entry)
                                                            <article class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                                                @if ($entry->publicMedia()->isNotEmpty())
                                                                    @php
                                                                        $media = $entry->publicMedia()->first();
                                                                    @endphp
                                                                    <img
                                                                        src="{{ $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl() }}"
                                                                        alt="{{ data_get($media->custom_properties, 'alt', $entry->title) }}"
                                                                        class="mb-3 aspect-[4/3] w-32 rounded-md object-cover"
                                                                    >
                                                                @endif
                                                                <div class="text-xs text-zinc-500">{{ $entry->happened_at?->format('M j, H:i') }}</div>
                                                                <div class="mt-1 font-medium text-zinc-950 dark:text-white">{{ $entry->title }}</div>
                                                                @if ($entry->excerpt)
                                                                    <p class="mt-1">{{ $entry->excerpt }}</p>
                                                                @endif
                                                            </article>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </button>
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

            @if ($this->journalEntries->isNotEmpty())
                <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Journal updates') }}</h2>
                    <div class="mt-4 grid gap-3">
                        @foreach ($this->journalEntries as $entry)
                            <article class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                                <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                                    @if ($entry->happened_at)
                                        <span>{{ $entry->happened_at->format('M j, Y H:i') }}</span>
                                    @endif
                                    @if ($entry->location_label)
                                        <span>{{ $entry->location_label }}</span>
                                    @endif
                                    @if ($entry->dayItineraryItem)
                                        <span>{{ $entry->dayItineraryItem->title }}</span>
                                    @endif
                                </div>
                                <h3 class="mt-2 font-semibold text-zinc-950 dark:text-white">{{ $entry->title }}</h3>
                                @if ($entry->excerpt)
                                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $entry->excerpt }}</p>
                                @endif
                                @if ($entry->body)
                                    <div class="mt-3 whitespace-pre-line text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $entry->body }}</div>
                                @endif
                                @if ($entry->publicMedia()->isNotEmpty())
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        @foreach ($entry->publicMedia() as $media)
                                            <figure>
                                                <img
                                                    src="{{ $media->hasGeneratedConversion('card') ? $media->getUrl('card') : $media->getUrl() }}"
                                                    alt="{{ data_get($media->custom_properties, 'alt', $entry->title) }}"
                                                    class="aspect-[4/3] w-full rounded-md object-cover"
                                                >
                                                @if (data_get($media->custom_properties, 'caption'))
                                                    <figcaption class="mt-2 text-sm text-zinc-500">{{ data_get($media->custom_properties, 'caption') }}</figcaption>
                                                @endif
                                            </figure>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

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
                    x-effect="$nextTick(() => window.renderTripMap?.($refs.map, @js($this->mapPayload)))"
                >
                    <div x-ref="map" class="h-80 overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700"></div>
                </div>

                @if ($this->publicSlotsMissingCoordinates->isNotEmpty())
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                        <div class="font-semibold">{{ __('Needs map pin') }}</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($this->publicSlotsMissingCoordinates as $slot)
                                <span class="rounded-full bg-white px-2.5 py-1 dark:bg-zinc-900">{{ $slot->title }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
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
                            <a class="block rounded-lg bg-zinc-50 p-3 hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700" href="{{ $this->dayUrl($this->previousDay) }}">
                                <div class="text-zinc-500">{{ __('Previous') }}</div>
                                <div class="font-medium text-zinc-950 dark:text-white">{{ __('Day :day', ['day' => $this->previousDay->day_number]) }} · {{ $this->previousDay->title }}</div>
                            </a>
                        @endif

                        @if ($this->nextDay)
                            <a class="block rounded-lg bg-zinc-50 p-3 hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700" href="{{ $this->dayUrl($this->nextDay) }}">
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
