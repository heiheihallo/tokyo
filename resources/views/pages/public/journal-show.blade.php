<?php

use App\Models\JournalEntry;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Journal update')] class extends Component {
    public int $tripId;
    public int $journalEntryId;
    #[Url(as: 'timeline', except: '')]
    public string $variantSlug = '';
    #[Url(as: 'preview', except: false)]
    public bool $preview = false;

    public function mount(Trip $trip, JournalEntry $journalEntry): void
    {
        abort_unless($this->canPreview() || $trip->isVisibleTo(auth()->user()), 404);
        abort_unless($journalEntry->trip_id === $trip->id, 404);
        abort_unless($journalEntry->isVisibleTo(auth()->user()), 404);

        if ($journalEntry->variant) {
            abort_unless($this->canPreview() || $journalEntry->variant->isVisibleTo(auth()->user()), 404);
        }

        $this->tripId = $trip->id;
        $this->journalEntryId = $journalEntry->id;
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
    public function entry(): JournalEntry
    {
        return $this->trip->journalEntries()
            ->visibleTo(auth()->user())
            ->with(['variant', 'dayNode', 'dayItineraryItem', 'media'])
            ->whereKey($this->journalEntryId)
            ->firstOrFail();
    }

    public function journalUrl(): string
    {
        return route('trips.public.journal', array_filter([
            'trip' => $this->trip,
            'timeline' => $this->variantSlug ?: $this->entry->variant?->slug,
            'day' => $this->entry->dayNode?->stable_key,
            'preview' => $this->canPreview() ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    public function timelineUrl(): string
    {
        return route('trips.public', array_filter([
            'trip' => $this->trip,
            'timeline' => $this->variantSlug ?: $this->entry->variant?->slug,
            'day' => $this->entry->dayNode?->stable_key,
            'slot' => $this->entry->dayItineraryItem?->stable_key,
            'preview' => $this->canPreview() ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    public function dayUrl(): ?string
    {
        if (! $this->entry->dayNode || ! $this->entry->variant) {
            return null;
        }

        return route('trips.public.days.show', array_filter([
            'trip' => $this->trip,
            'variant' => $this->entry->variant,
            'dayNode' => $this->entry->dayNode,
            'slot' => $this->entry->dayItineraryItem?->stable_key,
            'preview' => $this->canPreview() ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    public function canPreview(): bool
    {
        return $this->preview && auth()->check();
    }
}; ?>

<main class="min-h-screen bg-white dark:bg-zinc-950">
    <header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-2">
                <flux:button size="sm" icon="arrow-left" :href="$this->journalUrl()">{{ __('Journal') }}</flux:button>
                <flux:button size="sm" icon="calendar-days" :href="$this->timelineUrl()">{{ __('Timeline') }}</flux:button>
                @if ($this->dayUrl())
                    <flux:button size="sm" icon="map-pin" :href="$this->dayUrl()">{{ __('Day') }}</flux:button>
                @endif
            </div>

            <div class="mt-6">
                <div class="text-sm font-medium uppercase tracking-wide text-teal-700 dark:text-teal-300">{{ $this->trip->name }}</div>
                <h1 class="mt-2 text-3xl font-semibold tracking-normal text-zinc-950 dark:text-white">{{ $this->entry->title }}</h1>
                <div class="mt-4 flex flex-wrap gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    @if ($this->entry->happened_at)
                        <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ $this->entry->happened_at->format('M j, Y H:i') }}</span>
                    @endif
                    @if ($this->entry->location_label)
                        <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ $this->entry->location_label }}</span>
                    @endif
                    @if ($this->entry->dayNode)
                        <span class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ __('Day :day', ['day' => $this->entry->dayNode->day_number]) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <article class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        @if ($this->entry->publicMedia()->isNotEmpty())
            <div class="grid gap-4">
                @foreach ($this->entry->publicMedia() as $media)
                    <figure>
                        <img
                            src="{{ $media->hasGeneratedConversion('hero') ? $media->getUrl('hero') : $media->getUrl() }}"
                            alt="{{ data_get($media->custom_properties, 'alt', $this->entry->title) }}"
                            class="aspect-[16/9] w-full rounded-lg object-cover"
                        >
                        @if (data_get($media->custom_properties, 'caption'))
                            <figcaption class="mt-2 text-sm text-zinc-500">{{ data_get($media->custom_properties, 'caption') }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @endif

        <div class="mt-6 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            @if ($this->entry->excerpt)
                <p class="text-base leading-7 text-zinc-700 dark:text-zinc-200">{{ $this->entry->excerpt }}</p>
            @endif

            @if ($this->entry->body)
                <div class="mt-5 whitespace-pre-line text-sm leading-7 text-zinc-600 dark:text-zinc-300">{{ $this->entry->body }}</div>
            @endif

            @if ($this->entry->tags)
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach ($this->entry->tags as $tag)
                        <a class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" href="{{ route('trips.public.journal', array_filter(['trip' => $this->trip, 'timeline' => $this->variantSlug ?: $this->entry->variant?->slug, 'tag' => $tag, 'preview' => $this->canPreview() ? 1 : null])) }}">{{ $tag }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </article>
</main>
