<?php

use App\Models\DayNode;
use App\Models\JournalEntry;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Trip journal')] class extends Component {
    public int $tripId;
    #[Url(as: 'timeline', except: '')]
    public string $variantSlug = '';
    #[Url(as: 'day', except: '')]
    public string $dayKey = '';
    #[Url(except: '')]
    public string $tag = '';
    #[Url(as: 'preview', except: false)]
    public bool $preview = false;

    public function mount(Trip $trip): void
    {
        abort_unless($this->canPreview() || $trip->isVisibleTo(auth()->user()), 404);

        if ($this->variantSlug !== '') {
            abort_unless($this->availableVariantsQuery($trip)->where('slug', $this->variantSlug)->exists(), 404);
        }

        $this->tripId = $trip->id;
    }

    public function updatedVariantSlug(): void
    {
        $this->dayKey = '';
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
    public function variants(): EloquentCollection
    {
        return $this->availableVariantsQuery($this->trip)->get();
    }

    #[Computed]
    public function selectedVariant(): ?TripVariant
    {
        if ($this->variantSlug === '') {
            return null;
        }

        return $this->availableVariantsQuery($this->trip)
            ->where('slug', $this->variantSlug)
            ->firstOrFail();
    }

    #[Computed]
    public function days(): EloquentCollection
    {
        return ($this->selectedVariant?->dayNodes() ?? $this->trip->dayNodes())
            ->orderBy('day_number')
            ->get();
    }

    #[Computed]
    public function selectedDay(): ?DayNode
    {
        if ($this->dayKey === '') {
            return null;
        }

        return $this->days->firstWhere('stable_key', $this->dayKey);
    }

    #[Computed]
    public function entries(): EloquentCollection
    {
        return $this->visibleEntriesQuery()
            ->with(['variant', 'dayNode', 'dayItineraryItem', 'media'])
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function tagOptions(): Collection
    {
        return $this->visibleEntriesQuery()
            ->get(['tags'])
            ->flatMap(fn (JournalEntry $entry): array => $entry->tags ?? [])
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    public function timelineUrl(): string
    {
        return route('trips.public', array_filter([
            'trip' => $this->trip,
            'timeline' => $this->variantSlug ?: null,
            'day' => $this->dayKey ?: null,
            'preview' => $this->canPreview() ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    public function entryUrl(JournalEntry $entry): string
    {
        return route('trips.public.journal.show', array_filter([
            'trip' => $this->trip,
            'journalEntry' => $entry,
            'timeline' => $this->variantSlug ?: $entry->variant?->slug,
            'preview' => $this->canPreview() ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    public function dayUrl(DayNode $day): string
    {
        return route('trips.public.days.show', array_filter([
            'trip' => $this->trip,
            'variant' => $day->variant,
            'dayNode' => $day,
            'preview' => $this->canPreview() ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    public function canPreview(): bool
    {
        return $this->preview && auth()->check();
    }

    private function visibleEntriesQuery(): HasMany
    {
        return $this->trip->journalEntries()
            ->visibleTo(auth()->user())
            ->when($this->selectedVariant, fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->whereNull('trip_variant_id')
                    ->orWhere('trip_variant_id', $this->selectedVariant->id);
            }))
            ->when($this->selectedDay, fn (Builder $query) => $query->where('day_node_id', $this->selectedDay->id))
            ->when($this->tag !== '', fn (Builder $query) => $query->whereJsonContains('tags', $this->tag));
    }

    private function availableVariantsQuery(Trip $trip): HasMany
    {
        return $this->canPreview()
            ? $trip->variants()
            : $trip->visibleVariantsFor(auth()->user());
    }
}; ?>

<main class="min-h-screen bg-white dark:bg-zinc-950">
    <header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-2">
                <flux:button size="sm" icon="arrow-left" :href="$this->timelineUrl()">{{ __('Timeline') }}</flux:button>
                @if ($this->canPreview())
                    <flux:badge color="amber">{{ __('Preview mode') }}</flux:badge>
                @endif
            </div>

            <div class="mt-6 max-w-3xl">
                <div class="text-sm font-medium uppercase tracking-wide text-teal-700 dark:text-teal-300">{{ $this->trip->name }}</div>
                <h1 class="mt-2 text-3xl font-semibold tracking-normal text-zinc-950 dark:text-white">{{ __('Trip journal') }}</h1>
                <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ __('Updates, notes, and photos from the route as it comes together.') }}</p>
            </div>
        </div>
    </header>

    <section class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="space-y-4">
                <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="space-y-4">
                        <flux:select wire:model.live="variantSlug" :label="__('Timeline')">
                            <flux:select.option value="">{{ __('All timelines') }}</flux:select.option>
                            @foreach ($this->variants as $variant)
                                <flux:select.option value="{{ $variant->slug }}">{{ $variant->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="dayKey" :label="__('Day')">
                            <flux:select.option value="">{{ __('All days') }}</flux:select.option>
                            @foreach ($this->days as $day)
                                <flux:select.option value="{{ $day->stable_key }}">{{ __('Day :day', ['day' => $day->day_number]) }} · {{ Str::limit($day->title, 32) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="tag" :label="__('Tag')">
                            <flux:select.option value="">{{ __('All tags') }}</flux:select.option>
                            @foreach ($this->tagOptions as $tagOption)
                                <flux:select.option value="{{ $tagOption }}">{{ $tagOption }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </section>
            </aside>

            <div class="space-y-5">
                @forelse ($this->entries as $entry)
                    <article class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" wire:key="journal-feed-entry-{{ $entry->id }}">
                        @if ($entry->publicMedia()->isNotEmpty())
                            @php
                                $media = $entry->publicMedia()->first();
                            @endphp
                            <a href="{{ $this->entryUrl($entry) }}">
                                <img
                                    src="{{ $media->hasGeneratedConversion('card') ? $media->getUrl('card') : $media->getUrl() }}"
                                    alt="{{ data_get($media->custom_properties, 'alt', $entry->title) }}"
                                    class="aspect-[16/9] w-full rounded-md object-cover"
                                >
                            </a>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                            @if ($entry->happened_at)
                                <span>{{ $entry->happened_at->format('M j, Y H:i') }}</span>
                            @endif
                            @if ($entry->location_label)
                                <span>{{ $entry->location_label }}</span>
                            @endif
                            @if ($entry->dayNode)
                                <a class="underline decoration-zinc-300 underline-offset-4 hover:decoration-teal-600" href="{{ $this->dayUrl($entry->dayNode) }}">{{ __('Day :day', ['day' => $entry->dayNode->day_number]) }}</a>
                            @endif
                        </div>

                        <h2 class="mt-2 text-xl font-semibold text-zinc-950 dark:text-white">
                            <a href="{{ $this->entryUrl($entry) }}">{{ $entry->title }}</a>
                        </h2>

                        @if ($entry->excerpt)
                            <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $entry->excerpt }}</p>
                        @elseif ($entry->body)
                            <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ Str::limit($entry->body, 260) }}</p>
                        @endif

                        @if ($entry->tags)
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($entry->tags as $entryTag)
                                    <a class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" href="{{ route('trips.public.journal', array_filter(['trip' => $this->trip, 'timeline' => $this->variantSlug ?: null, 'tag' => $entryTag, 'preview' => $this->canPreview() ? 1 : null])) }}">{{ $entryTag }}</a>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <section class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                        {{ __('No journal updates match these filters yet.') }}
                    </section>
                @endforelse
            </div>
        </div>
    </section>
</main>
