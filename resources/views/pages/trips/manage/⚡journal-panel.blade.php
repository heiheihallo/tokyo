<?php

use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\JournalEntry;
use App\Models\Trip;
use App\Models\TripVariant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

new class extends Component {
    use WithFileUploads;

    public ?int $selectedTripId = null;

    public ?int $selectedVariantId = null;

    public ?int $selectedDayId = null;

    public ?int $selectedSlotId = null;

    public ?int $selectedJournalEntryId = null;

    public ?TemporaryUploadedFile $journalMediaUpload = null;

    public array $journalMediaForm = ['caption' => '', 'alt' => '', 'visibility' => 'private'];

    public array $journalForm = [
        'title' => '',
        'excerpt' => '',
        'body' => '',
        'location_label' => '',
        'trip_variant_id' => '',
        'day_node_id' => '',
        'day_itinerary_item_id' => '',
        'happened_at' => '',
        'visibility' => 'private',
        'tags' => '',
        'metadata_weather' => '',
        'metadata_mood' => '',
    ];

    public function mount(): void
    {
        $this->journalForm['trip_variant_id'] = $this->selectedVariantId ? (string) $this->selectedVariantId : '';
        $this->journalForm['day_node_id'] = $this->selectedDayId ? (string) $this->selectedDayId : '';
        $this->journalForm['day_itinerary_item_id'] = $this->selectedSlotId ? (string) $this->selectedSlotId : '';

        if ($this->selectedJournalEntryId) {
            $this->selectJournalEntry($this->selectedJournalEntryId);
        } elseif ($this->selectedSlotId) {
            $this->startJournalForSelectedSlot($this->selectedSlotId);
        } elseif ($this->selectedDayId) {
            $this->startJournalForSelectedDay();
        }
    }

    public function createJournalEntry(): void
    {
        if (! $this->selectedTrip) {
            return;
        }

        $payload = $this->validatedJournalPayload();

        $entry = $this->selectedTrip->journalEntries()->create($payload);

        $this->selectedJournalEntryId = $entry->id;
        $this->fillJournalForm($entry);
        unset($this->journalEntries);

        Flux::toast(variant: 'success', text: __('Journal entry created.'));
    }

    public function selectJournalEntry(int $entryId): void
    {
        $entry = $this->selectedTrip?->journalEntries()->whereKey($entryId)->first();

        if (! $entry) {
            return;
        }

        $this->selectedJournalEntryId = $entry->id;
        $this->fillJournalForm($entry);
    }

    public function updateJournalEntry(): void
    {
        $entry = $this->selectedJournalEntry;

        if (! $entry) {
            return;
        }

        $entry->fill($this->validatedJournalPayload())->save();

        unset($this->journalEntries, $this->selectedJournalEntry);
        $this->selectJournalEntry($entry->id);

        Flux::toast(variant: 'success', text: __('Journal entry updated.'));
    }

    public function publishJournalEntry(): void
    {
        $entry = $this->selectedJournalEntry;

        if (! $entry) {
            return;
        }

        $entry->publish($this->journalForm['visibility'] === 'private' ? 'public' : $this->journalForm['visibility']);

        unset($this->journalEntries, $this->selectedJournalEntry);
        $this->selectJournalEntry($entry->id);

        Flux::toast(variant: 'success', text: __('Journal entry published.'));
    }

    public function unpublishJournalEntry(): void
    {
        $entry = $this->selectedJournalEntry;

        if (! $entry) {
            return;
        }

        $entry->unpublish();

        unset($this->journalEntries, $this->selectedJournalEntry);
        $this->selectJournalEntry($entry->id);

        Flux::toast(text: __('Journal entry unpublished.'));
    }

    public function addJournalMedia(): void
    {
        $entry = $this->selectedJournalEntry;

        if (! $entry) {
            return;
        }

        $validated = $this->validate([
            'journalMediaUpload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'journalMediaForm.caption' => ['nullable', 'string', 'max:500'],
            'journalMediaForm.alt' => ['nullable', 'string', 'max:255'],
            'journalMediaForm.visibility' => ['required', 'in:private,public'],
        ]);

        $upload = $validated['journalMediaUpload'];

        $entry
            ->addMedia($upload->getRealPath())
            ->usingFileName($upload->hashName())
            ->withCustomProperties([
                'caption' => $this->blankToNull($validated['journalMediaForm']['caption']),
                'alt' => $this->blankToNull($validated['journalMediaForm']['alt']),
                'visibility' => $validated['journalMediaForm']['visibility'],
            ])
            ->toMediaCollection(JournalEntry::MEDIA_COLLECTION_IMAGES);

        $this->journalMediaUpload = null;
        $this->journalMediaForm = ['caption' => '', 'alt' => '', 'visibility' => 'private'];

        unset($this->selectedJournalEntry, $this->journalEntries);
        $this->selectJournalEntry($entry->id);

        Flux::toast(variant: 'success', text: __('Journal image added.'));
    }

    public function setJournalHeroMedia(int $mediaId): void
    {
        $entry = $this->selectedJournalEntry;
        $media = $this->selectedJournalMedia($mediaId);

        if (! $entry || ! $media) {
            return;
        }

        $entry->getMedia(JournalEntry::MEDIA_COLLECTION_MAIN_IMAGE)
            ->reject(fn (Media $candidate): bool => $candidate->id === $media->id)
            ->each->delete();

        $media->update(['collection_name' => JournalEntry::MEDIA_COLLECTION_MAIN_IMAGE]);

        unset($this->selectedJournalEntry, $this->journalEntries);
        $this->selectJournalEntry($entry->id);

        Flux::toast(variant: 'success', text: __('Journal hero image updated.'));
    }

    public function toggleJournalMediaVisibility(int $mediaId): void
    {
        $media = $this->selectedJournalMedia($mediaId);

        if (! $media) {
            return;
        }

        $media->setCustomProperty(
            'visibility',
            data_get($media->custom_properties, 'visibility', 'private') === 'public' ? 'private' : 'public',
        );
        $media->save();

        unset($this->selectedJournalEntry, $this->journalEntries);
    }

    public function moveJournalMedia(int $mediaId, string $direction): void
    {
        $entry = $this->selectedJournalEntry;
        $media = $this->selectedJournalMedia($mediaId);

        if (! $entry || ! $media || ! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        $mediaItems = $entry->getMedia($media->collection_name)->values();
        $index = $mediaItems->search(fn (Media $candidate): bool => $candidate->id === $media->id);
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $mediaItems->has($swapIndex)) {
            return;
        }

        $other = $mediaItems->get($swapIndex);
        [$mediaOrder, $otherOrder] = [$media->order_column, $other->order_column];

        $media->update(['order_column' => $otherOrder]);
        $other->update(['order_column' => $mediaOrder]);

        unset($this->selectedJournalEntry, $this->journalEntries);
        $this->selectJournalEntry($entry->id);
    }

    public function removeJournalMedia(int $mediaId): void
    {
        $media = $this->selectedJournalMedia($mediaId);

        if (! $media) {
            return;
        }

        $media->delete();

        unset($this->selectedJournalEntry, $this->journalEntries);

        Flux::toast(text: __('Journal image removed.'));
    }

    public function resetJournalForm(): void
    {
        $this->selectedJournalEntryId = null;
        $this->journalForm = [
            'title' => '',
            'excerpt' => '',
            'body' => '',
            'location_label' => '',
            'trip_variant_id' => $this->selectedVariantId ? (string) $this->selectedVariantId : '',
            'day_node_id' => $this->selectedDayId ? (string) $this->selectedDayId : '',
            'day_itinerary_item_id' => $this->selectedSlotId ? (string) $this->selectedSlotId : '',
            'happened_at' => '',
            'visibility' => 'private',
            'tags' => '',
            'metadata_weather' => '',
            'metadata_mood' => '',
        ];
    }

    public function startJournalForSelectedDay(): void
    {
        $this->resetJournalForm();

        $this->journalForm['title'] = $this->selectedDay ? __('Day :day update', ['day' => $this->selectedDay->day_number]) : '';
        $this->journalForm['location_label'] = $this->selectedDay?->location ?? '';
        $this->journalForm['trip_variant_id'] = $this->selectedVariantId ? (string) $this->selectedVariantId : '';
        $this->journalForm['day_node_id'] = $this->selectedDayId ? (string) $this->selectedDayId : '';
        $this->journalForm['day_itinerary_item_id'] = $this->selectedSlotId ? (string) $this->selectedSlotId : '';
        unset($this->journalSlotOptions);
    }

    public function startJournalForSelectedSlot(int $slotId): void
    {
        $slot = $this->selectedDay?->itineraryItems->firstWhere('id', $slotId);

        if (! $slot) {
            return;
        }

        $this->selectedSlotId = $slot->id;
        $this->resetJournalForm();
        $this->journalForm['title'] = __(':title update', ['title' => $slot->title]);
        $this->journalForm['excerpt'] = $slot->summary ?? '';
        $this->journalForm['location_label'] = $slot->location_label ?? $this->selectedDay?->location ?? '';
        $this->journalForm['trip_variant_id'] = (string) $slot->trip_variant_id;
        $this->journalForm['day_node_id'] = (string) $slot->day_node_id;
        $this->journalForm['day_itinerary_item_id'] = (string) $slot->id;
        unset($this->journalSlotOptions);
    }

    public function publishJournalEntryAs(string $visibility): void
    {
        if (! in_array($visibility, ['family', 'public'], true)) {
            return;
        }

        $entry = $this->selectedJournalEntry;

        if (! $entry) {
            return;
        }

        $entry->publish($visibility);

        unset($this->journalEntries, $this->selectedJournalEntry);
        $this->selectJournalEntry($entry->id);

        Flux::toast(variant: 'success', text: __('Journal entry published.'));
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

    #[Computed]
    public function selectedJournalEntry(): ?JournalEntry
    {
        return $this->selectedJournalEntryId && $this->selectedTrip
            ? $this->selectedTrip->journalEntries()->with('media')->whereKey($this->selectedJournalEntryId)->first()
            : null;
    }

    #[Computed]
    public function journalEntries(): EloquentCollection
    {
        return $this->selectedTrip
            ? $this->selectedTrip->journalEntries()
                ->with(['variant', 'dayNode', 'dayItineraryItem'])
                ->limit(20)
                ->get()
            : new EloquentCollection();
    }

    #[Computed]
    public function journalSlotOptions(): EloquentCollection
    {
        $dayId = (int) ($this->journalForm['day_node_id'] ?: 0);

        return $dayId > 0
            ? DayItineraryItem::query()
                ->where('trip_id', $this->selectedTripId)
                ->where('day_node_id', $dayId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : new EloquentCollection();
    }

    public function updatedJournalFormDayNodeId(): void
    {
        $this->journalForm['day_itinerary_item_id'] = '';
        unset($this->journalSlotOptions);
    }

    private function blankToNull(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }

    private function fillJournalForm(JournalEntry $entry): void
    {
        $this->journalForm = [
            'title' => $entry->title,
            'excerpt' => $entry->excerpt ?? '',
            'body' => $entry->body ?? '',
            'location_label' => $entry->location_label ?? '',
            'trip_variant_id' => $entry->trip_variant_id ? (string) $entry->trip_variant_id : '',
            'day_node_id' => $entry->day_node_id ? (string) $entry->day_node_id : '',
            'day_itinerary_item_id' => $entry->day_itinerary_item_id ? (string) $entry->day_itinerary_item_id : '',
            'happened_at' => $entry->happened_at?->format('Y-m-d\TH:i') ?? '',
            'visibility' => $entry->visibility,
            'tags' => collect($entry->tags ?? [])->join(', '),
            'metadata_weather' => data_get($entry->metadata, 'weather', ''),
            'metadata_mood' => data_get($entry->metadata, 'mood', ''),
        ];
    }

    private function validatedJournalPayload(): array
    {
        $validated = $this->validate([
            'journalForm.title' => ['required', 'string', 'max:255'],
            'journalForm.excerpt' => ['nullable', 'string', 'max:500'],
            'journalForm.body' => ['nullable', 'string', 'max:8000'],
            'journalForm.location_label' => ['nullable', 'string', 'max:255'],
            'journalForm.trip_variant_id' => ['nullable', 'integer'],
            'journalForm.day_node_id' => ['nullable', 'integer'],
            'journalForm.day_itinerary_item_id' => ['nullable', 'integer'],
            'journalForm.happened_at' => ['nullable', 'date'],
            'journalForm.visibility' => ['required', 'in:private,family,public'],
            'journalForm.tags' => ['nullable', 'string', 'max:500'],
            'journalForm.metadata_weather' => ['nullable', 'string', 'max:100'],
            'journalForm.metadata_mood' => ['nullable', 'string', 'max:100'],
        ]);

        $variantId = $this->scopedJournalVariantId((int) ($validated['journalForm']['trip_variant_id'] ?: 0));
        $dayId = $this->scopedJournalDayId((int) ($validated['journalForm']['day_node_id'] ?: 0), $variantId);
        $slotId = $this->scopedJournalSlotId((int) ($validated['journalForm']['day_itinerary_item_id'] ?: 0), $dayId);

        return [
            'trip_variant_id' => $variantId,
            'day_node_id' => $dayId,
            'day_itinerary_item_id' => $slotId,
            'title' => $validated['journalForm']['title'],
            'excerpt' => $this->blankToNull($validated['journalForm']['excerpt']),
            'body' => $this->blankToNull($validated['journalForm']['body']),
            'location_label' => $this->blankToNull($validated['journalForm']['location_label']),
            'happened_at' => $this->blankToNull($validated['journalForm']['happened_at']),
            'visibility' => $validated['journalForm']['visibility'],
            'tags' => collect(explode(',', $validated['journalForm']['tags'] ?? ''))
                ->map(fn (string $tag): string => trim($tag))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'metadata' => collect([
                'weather' => $this->blankToNull($validated['journalForm']['metadata_weather']),
                'mood' => $this->blankToNull($validated['journalForm']['metadata_mood']),
            ])->filter(fn ($value) => $value !== null)->all(),
        ];
    }

    private function scopedJournalVariantId(int $variantId): ?int
    {
        if ($variantId < 1 || ! $this->selectedTrip) {
            return null;
        }

        return $this->selectedTrip->variants()->whereKey($variantId)->exists() ? $variantId : null;
    }

    private function scopedJournalDayId(int $dayId, ?int $variantId): ?int
    {
        if ($dayId < 1 || ! $this->selectedTrip) {
            return null;
        }

        return $this->selectedTrip->dayNodes()
            ->whereKey($dayId)
            ->when($variantId, fn ($query) => $query->where('trip_variant_id', $variantId))
            ->exists() ? $dayId : null;
    }

    private function scopedJournalSlotId(int $slotId, ?int $dayId): ?int
    {
        if ($slotId < 1 || ! $this->selectedTrip) {
            return null;
        }

        return DayItineraryItem::query()
            ->where('trip_id', $this->selectedTrip->id)
            ->whereKey($slotId)
            ->when($dayId, fn ($query) => $query->where('day_node_id', $dayId))
            ->exists() ? $slotId : null;
    }

    private function selectedJournalMedia(int $mediaId): ?Media
    {
        return $this->selectedJournalEntry
            ? $this->selectedJournalEntry->media()->whereKey($mediaId)->first()
            : null;
    }
}; ?>

<flux:card>
    <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <flux:heading>{{ __('Journal') }}</flux:heading>
            <flux:text>{{ __('Trip updates that can attach to the whole route, one day, or one slot.') }}</flux:text>
        </div>
        <flux:modal.trigger name="journal-entry">
            <flux:button size="sm" icon="plus" wire:click="resetJournalForm">
                {{ __('New entry') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <div class="mt-5">
        <div class="space-y-3">
            @forelse ($this->journalEntries as $entry)
                <article
                    wire:key="journal-entry-{{ $entry->id }}"
                    class="rounded-lg border p-4 {{ $this->selectedJournalEntryId === $entry->id ? 'border-teal-700 bg-teal-50 dark:border-teal-300 dark:bg-teal-950/40' : 'border-zinc-200 dark:border-zinc-700' }}"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge color="{{ $entry->visibility === 'public' ? 'green' : ($entry->visibility === 'family' ? 'amber' : 'zinc') }}">
                                    {{ $entry->visibilityLabel() }}
                                </flux:badge>
                                @if ($entry->published_at)
                                    <flux:badge color="teal">{{ __('Published') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc">{{ __('Draft') }}</flux:badge>
                                @endif
                            </div>
                            <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $entry->title }}</div>
                            <div class="mt-1 text-sm text-zinc-500">
                                {{ collect([
                                    $entry->happened_at?->format('M j, Y H:i'),
                                    $entry->variant?->name,
                                    $entry->dayNode ? __('Day :day', ['day' => $entry->dayNode->day_number]) : null,
                                    $entry->dayItineraryItem?->title,
                                ])->filter()->join(' · ') }}
                            </div>
                            @if ($entry->excerpt)
                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $entry->excerpt }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <flux:modal.trigger name="journal-entry">
                                <flux:button size="xs" icon="pencil-square" wire:click="selectJournalEntry({{ $entry->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                            </flux:modal.trigger>
                            <flux:modal.trigger name="journal-media">
                                <flux:button size="xs" icon="photo" wire:click="selectJournalEntry({{ $entry->id }})">
                                    {{ __('Media') }}
                                </flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    {{ __('No journal entries yet.') }}
                </div>
            @endforelse
        </div>
    </div>

    <flux:modal name="journal-entry" class="md:w-[42rem]">
            <form wire:submit="{{ $this->selectedJournalEntry ? 'updateJournalEntry' : 'createJournalEntry' }}" class="space-y-4">
                <div>
                    <div class="text-sm font-semibold text-zinc-950 dark:text-white">
                        {{ $this->selectedJournalEntry ? __('Edit journal entry') : __('New journal entry') }}
                    </div>
                    @if ($this->selectedJournalEntry?->published_at)
                        <div class="mt-1 text-sm text-zinc-500">{{ __('Published :date', ['date' => $this->selectedJournalEntry->published_at->format('M j, Y H:i')]) }}</div>
                    @endif
                </div>

                <flux:input wire:model="journalForm.title" :label="__('Title')" />
                <flux:input wire:model="journalForm.excerpt" :label="__('Short excerpt')" />
                <flux:textarea wire:model="journalForm.body" :label="__('Journal body')" rows="5" />
                <flux:input wire:model="journalForm.location_label" :label="__('Location label')" />

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="journalForm.happened_at" :label="__('Happened at')" type="datetime-local" />
                    <flux:select wire:model="journalForm.visibility" :label="__('Visibility')">
                        <flux:select.option value="private">{{ __('Private') }}</flux:select.option>
                        <flux:select.option value="family">{{ __('Family') }}</flux:select.option>
                        <flux:select.option value="public">{{ __('Public') }}</flux:select.option>
                    </flux:select>
                </div>

                <flux:select wire:model.live="journalForm.trip_variant_id" :label="__('Attach timeline')">
                    <flux:select.option value="">{{ __('Whole trip') }}</flux:select.option>
                    @foreach ($this->variants as $variant)
                        <flux:select.option value="{{ $variant->id }}">{{ $variant->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="journalForm.day_node_id" :label="__('Attach day')">
                    <flux:select.option value="">{{ __('No day') }}</flux:select.option>
                    @foreach ($this->days as $day)
                        <flux:select.option value="{{ $day->id }}">{{ __('Day :day', ['day' => $day->day_number]) }} · {{ $day->title }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="journalForm.day_itinerary_item_id" :label="__('Attach slot')">
                    <flux:select.option value="">{{ __('No slot') }}</flux:select.option>
                    @foreach ($this->journalSlotOptions as $slot)
                        <flux:select.option value="{{ $slot->id }}">{{ collect([$slot->time_label, $slot->title])->filter()->join(' · ') }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="journalForm.metadata_weather" :label="__('Weather')" />
                    <flux:input wire:model="journalForm.metadata_mood" :label="__('Mood')" />
                </div>

                <flux:input wire:model="journalForm.tags" :label="__('Tags')" placeholder="arrival, hotel, food" />

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ $this->selectedJournalEntry ? __('Save entry') : __('Create entry') }}
                    </flux:button>

                    @if ($this->selectedJournalEntry)
                        @if ($this->selectedJournalEntry->published_at)
                            <flux:button type="button" wire:click="unpublishJournalEntry">
                                {{ __('Unpublish') }}
                            </flux:button>
                        @else
                            <flux:button type="button" wire:click="publishJournalEntry" icon="paper-airplane">
                                {{ __('Publish') }}
                            </flux:button>
                            <flux:button type="button" wire:click="publishJournalEntryAs('family')">
                                {{ __('Family publish') }}
                            </flux:button>
                            <flux:button type="button" wire:click="publishJournalEntryAs('public')">
                                {{ __('Public publish') }}
                            </flux:button>
                        @endif
                    @endif
                </div>
            </form>
    </flux:modal>

    <flux:modal name="journal-media" class="md:w-[42rem]">
            @if ($this->selectedJournalEntry)
                <div class="space-y-4">
                    <div>
                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Journal media') }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ __('Images are private until marked public.') }}</div>
                    </div>

                    <form wire:submit="addJournalMedia" class="space-y-4">
                        <flux:input wire:model="journalMediaUpload" :label="__('Image')" type="file" accept="image/jpeg,image/png,image/webp,image/avif" />
                        <flux:input wire:model="journalMediaForm.caption" :label="__('Caption')" />
                        <flux:input wire:model="journalMediaForm.alt" :label="__('Alt text')" />
                        <flux:select wire:model="journalMediaForm.visibility" :label="__('Media visibility')">
                            <flux:select.option value="private">{{ __('Private') }}</flux:select.option>
                            <flux:select.option value="public">{{ __('Public') }}</flux:select.option>
                        </flux:select>
                        <flux:button type="submit" icon="photo" variant="primary">{{ __('Add image') }}</flux:button>
                    </form>

                    <div class="space-y-3">
                        @forelse ($this->selectedJournalEntry->getMedia(JournalEntry::MEDIA_COLLECTION_MAIN_IMAGE)->merge($this->selectedJournalEntry->getMedia(JournalEntry::MEDIA_COLLECTION_IMAGES)) as $media)
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="journal-media-{{ $media->id }}">
                                <div class="grid gap-3 sm:grid-cols-[96px_minmax(0,1fr)]">
                                    <img
                                        src="{{ $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl() }}"
                                        alt="{{ data_get($media->custom_properties, 'alt', $media->name) }}"
                                        class="aspect-[4/3] w-24 rounded-md object-cover"
                                    >
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:badge color="{{ $media->collection_name === JournalEntry::MEDIA_COLLECTION_MAIN_IMAGE ? 'teal' : 'zinc' }}">
                                                {{ $media->collection_name === JournalEntry::MEDIA_COLLECTION_MAIN_IMAGE ? __('Hero') : __('Image') }}
                                            </flux:badge>
                                            <flux:badge color="{{ data_get($media->custom_properties, 'visibility', 'private') === 'public' ? 'green' : 'zinc' }}">
                                                {{ data_get($media->custom_properties, 'visibility', 'private') }}
                                            </flux:badge>
                                        </div>

                                        @if (data_get($media->custom_properties, 'caption'))
                                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ data_get($media->custom_properties, 'caption') }}</p>
                                        @else
                                            <p class="mt-2 truncate text-sm text-zinc-500">{{ $media->file_name }}</p>
                                        @endif

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <flux:button size="xs" wire:click="toggleJournalMediaVisibility({{ $media->id }})">
                                                {{ data_get($media->custom_properties, 'visibility', 'private') === 'public' ? __('Make private') : __('Make public') }}
                                            </flux:button>
                                            @if ($media->collection_name !== JournalEntry::MEDIA_COLLECTION_MAIN_IMAGE)
                                                <flux:button size="xs" wire:click="setJournalHeroMedia({{ $media->id }})">
                                                    {{ __('Set hero') }}
                                                </flux:button>
                                            @endif
                                            <flux:button size="xs" icon="arrow-up" wire:click="moveJournalMedia({{ $media->id }}, 'up')">{{ __('Up') }}</flux:button>
                                            <flux:button size="xs" icon="arrow-down" wire:click="moveJournalMedia({{ $media->id }}, 'down')">{{ __('Down') }}</flux:button>
                                            <flux:button size="xs" variant="danger" wire:click="removeJournalMedia({{ $media->id }})">{{ __('Remove') }}</flux:button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                                {{ __('No images attached yet.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
    </flux:modal>
</flux:card>
