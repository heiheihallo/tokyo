<?php

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\DayTask;
use App\Models\FoodSpot;
use App\Models\JournalEntry;
use App\Models\LoyaltyProgramSnapshot;
use App\Models\TransportLeg;
use App\Models\Trip;
use App\Models\TripVariant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

new #[Title('Manage trips')] class extends Component {
    use WithFileUploads;

    public ?int $selectedTripId = null;
    public ?int $selectedVariantId = null;
    public ?int $selectedDayId = null;
    public ?int $selectedSlotId = null;
    public ?int $selectedAssetId = null;
    public string $assetTab = 'accommodations';
    public string $assetSearch = '';
    public string $planningSeverityFilter = 'all';
    public string $planningCategoryFilter = 'all';

    public array $tripForm = ['name' => '', 'summary' => '', 'starts_on' => '', 'ends_on' => '', 'arrival_preference' => 'HND'];
    public array $variantForm = ['name' => '', 'budget_scenario' => 'value', 'stopover_type' => '', 'flight_strategy' => ''];
    public array $dayForm = [];
    public array $slotForm = ['item_type' => 'activity', 'time_label' => '', 'title' => '', 'location_label' => '', 'subject_ref' => '', 'latitude' => '', 'longitude' => '', 'summary' => '', 'is_public' => true];
    public array $slotEditForm = [];
    public array $taskForm = ['task_type' => 'todo', 'title' => '', 'priority' => 'medium', 'notes' => ''];
    public array $assetForm = ['name' => '', 'city' => '', 'country' => '', 'notes' => ''];
    public array $assetEditForm = [];
    public array $assetAttachForm = ['time_label' => '', 'title' => '', 'summary' => '', 'is_public' => true];
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
        $trip = Trip::query()->orderBy('starts_on')->first();

        $this->selectedTripId = $trip?->id;
        $this->selectedVariantId = $trip?->defaultVariant()?->id;
        $this->selectedDayId = $trip?->defaultVariant()?->dayNodes()->orderBy('day_number')->value('id');
        $this->loadDayForm();
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
        $this->slotEditForm = [];
        $this->loadDayForm();
    }

    public function updateDay(): void
    {
        if (! $this->selectedDay) {
            return;
        }

        $validated = $this->validate([
            'dayForm.title' => ['required', 'string', 'max:255'],
            'dayForm.location' => ['required', 'string', 'max:255'],
            'dayForm.booking_priority' => ['required', 'in:high,medium,low'],
            'dayForm.booking_status' => ['required', 'in:unbooked,planned,held,booked,cancelled'],
            'dayForm.cost_value_min_nok' => ['nullable', 'integer', 'min:0'],
            'dayForm.cost_value_max_nok' => ['nullable', 'integer', 'min:0'],
            'dayForm.cost_premium_min_nok' => ['nullable', 'integer', 'min:0'],
            'dayForm.cost_premium_max_nok' => ['nullable', 'integer', 'min:0'],
            'dayForm.rain_backup' => ['nullable', 'string', 'max:1000'],
        ]);

        $details = $this->selectedDay->details ?? [];
        $details['rain_backup'] = $validated['dayForm']['rain_backup'];

        $this->selectedDay->fill([
            'title' => $validated['dayForm']['title'],
            'location' => $validated['dayForm']['location'],
            'booking_priority' => $validated['dayForm']['booking_priority'],
            'booking_status' => $validated['dayForm']['booking_status'],
            'cost_value_min_nok' => $validated['dayForm']['cost_value_min_nok'],
            'cost_value_max_nok' => $validated['dayForm']['cost_value_max_nok'],
            'cost_premium_min_nok' => $validated['dayForm']['cost_premium_min_nok'],
            'cost_premium_max_nok' => $validated['dayForm']['cost_premium_max_nok'],
            'details' => $details,
        ])->save();

        Flux::toast(variant: 'success', text: __('Day updated.'));
    }

    public function createAsset(): void
    {
        $validated = $this->validate([
            'assetForm.name' => ['required', 'string', 'max:255'],
            'assetForm.city' => ['nullable', 'string', 'max:255'],
            'assetForm.country' => ['nullable', 'string', 'max:255'],
            'assetForm.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $model = $this->assetModel();
        $payload = [
            'stable_key' => Str::slug($this->assetTab.'-'.$validated['assetForm']['name']).'-'.Str::lower(Str::random(6)),
            'name' => $validated['assetForm']['name'],
            'city' => $validated['assetForm']['city'],
            'country' => $validated['assetForm']['country'],
            'notes' => $validated['assetForm']['notes'],
        ];

        if ($model === TransportLeg::class) {
            $payload = [
                'stable_key' => Str::slug('transport-'.$validated['assetForm']['name']).'-'.Str::lower(Str::random(6)),
                'mode' => 'rail',
                'route_label' => $validated['assetForm']['name'],
                'notes' => $validated['assetForm']['notes'],
            ];
        }

        $model::create($payload);
        $this->assetForm = ['name' => '', 'city' => '', 'country' => '', 'notes' => ''];

        Flux::toast(variant: 'success', text: __('Shared asset created.'));
    }

    public function selectAsset(int $assetId): void
    {
        $asset = $this->assetModel()::query()->find($assetId);

        if (! $asset) {
            return;
        }

        $this->selectedAssetId = $asset->id;
        $this->assetEditForm = collect($this->assetEditableFields())
            ->mapWithKeys(fn (string $field): array => [$field => $asset->{$field} ?? ''])
            ->all();
        $this->resetAssetAttachForm($asset);
    }

    public function updateAsset(): void
    {
        $asset = $this->selectedAsset;

        if (! $asset) {
            return;
        }

        $validated = $this->validate($this->assetValidationRules());
        $payload = collect($this->assetEditableFields())
            ->mapWithKeys(fn (string $field): array => [$field => $this->blankToNull($validated['assetEditForm'][$field] ?? null)])
            ->all();

        $asset->fill($payload)->save();

        unset($this->selectedAsset, $this->assets, $this->slotSubjects);
        $this->selectAsset($asset->id);

        Flux::toast(variant: 'success', text: __('Shared asset updated.'));
    }

    public function attachAssetToSelectedDay(): void
    {
        $asset = $this->selectedAsset;
        $day = $this->selectedDay;

        if (! $asset || ! $day) {
            return;
        }

        $validated = $this->validate([
            'assetAttachForm.time_label' => ['nullable', 'string', 'max:50'],
            'assetAttachForm.title' => ['required', 'string', 'max:255'],
            'assetAttachForm.summary' => ['nullable', 'string', 'max:1000'],
            'assetAttachForm.is_public' => ['boolean'],
        ]);

        $day->itineraryItems()->create([
            'trip_id' => $day->trip_id,
            'trip_variant_id' => $day->trip_variant_id,
            'stable_key' => 'asset-slot-'.Str::lower(Str::random(10)),
            'item_type' => $this->assetItemType(),
            'time_label' => $validated['assetAttachForm']['time_label'] ?: null,
            'title' => $validated['assetAttachForm']['title'],
            'location_label' => $this->assetLocationLabel($asset),
            'subject_type' => $asset::class,
            'subject_id' => $asset->id,
            'latitude' => $asset instanceof TransportLeg ? null : $asset->latitude,
            'longitude' => $asset instanceof TransportLeg ? null : $asset->longitude,
            'summary' => $validated['assetAttachForm']['summary'] ?: null,
            'is_public' => (bool) $validated['assetAttachForm']['is_public'],
            'sort_order' => ($day->itineraryItems()->max('sort_order') ?? 0) + 10,
            'details' => [],
        ]);

        unset($this->selectedDay, $this->selectedAssetUsages, $this->assets);
        $this->resetAssetAttachForm($asset);

        Flux::toast(variant: 'success', text: __('Asset attached to day.'));
    }

    public function detachAssetFromSelectedDay(int $slotId): void
    {
        $asset = $this->selectedAsset;

        $slot = $asset && $this->selectedDay
            ? $this->selectedDay->itineraryItems()
                ->whereKey($slotId)
                ->where('subject_type', $asset::class)
                ->where('subject_id', $asset->id)
                ->first()
            : null;

        if (! $slot) {
            return;
        }

        $slot->delete();

        unset($this->selectedDay, $this->selectedAssetUsages, $this->assets);

        if ($this->selectedSlotId === $slotId) {
            $this->selectedSlotId = null;
            $this->slotEditForm = [];
        }

        Flux::toast(text: __('Asset detached from selected day.'));
    }

    public function createSlot(): void
    {
        if (! $this->selectedDay) {
            return;
        }

        $validated = $this->validate([
            'slotForm.item_type' => ['required', 'in:stay,move,activity,food,buffer,note'],
            'slotForm.time_label' => ['nullable', 'string', 'max:50'],
            'slotForm.title' => ['required', 'string', 'max:255'],
            'slotForm.location_label' => ['nullable', 'string', 'max:255'],
            'slotForm.subject_ref' => ['nullable', 'string', 'max:255'],
            'slotForm.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'slotForm.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'slotForm.summary' => ['nullable', 'string', 'max:1000'],
            'slotForm.is_public' => ['boolean'],
        ]);

        [$subjectType, $subjectId] = $this->parseSubjectRef($validated['slotForm']['subject_ref']);
        $subject = $this->findSubject($subjectType, $subjectId);

        $this->selectedDay->itineraryItems()->create([
            'trip_id' => $this->selectedDay->trip_id,
            'trip_variant_id' => $this->selectedDay->trip_variant_id,
            'stable_key' => 'slot-'.Str::lower(Str::random(10)),
            'item_type' => $validated['slotForm']['item_type'],
            'time_label' => $validated['slotForm']['time_label'] ?: null,
            'title' => $validated['slotForm']['title'],
            'location_label' => $validated['slotForm']['location_label'] ?: null,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'latitude' => $this->blankToNull($validated['slotForm']['latitude']) ?? $subject?->latitude,
            'longitude' => $this->blankToNull($validated['slotForm']['longitude']) ?? $subject?->longitude,
            'summary' => $validated['slotForm']['summary'] ?: null,
            'is_public' => (bool) $validated['slotForm']['is_public'],
            'sort_order' => ($this->selectedDay->itineraryItems()->max('sort_order') ?? 0) + 10,
            'details' => [],
        ]);

        unset($this->selectedDay);
        $this->slotForm = ['item_type' => 'activity', 'time_label' => '', 'title' => '', 'location_label' => '', 'subject_ref' => '', 'latitude' => '', 'longitude' => '', 'summary' => '', 'is_public' => true];

        Flux::toast(variant: 'success', text: __('Slot added.'));
    }

    public function selectSlot(int $slotId): void
    {
        $slot = $this->selectedDay?->itineraryItems()->whereKey($slotId)->first();

        if (! $slot) {
            return;
        }

        $this->selectedSlotId = $slot->id;
        $this->slotEditForm = [
            'item_type' => $slot->item_type,
            'time_label' => $slot->time_label ?? '',
            'title' => $slot->title,
            'location_label' => $slot->location_label ?? '',
            'subject_ref' => $this->subjectRefForSlot($slot),
            'latitude' => $slot->latitude,
            'longitude' => $slot->longitude,
            'summary' => $slot->summary ?? '',
            'is_public' => $slot->is_public,
        ];
    }

    public function updateSlot(): void
    {
        $slot = $this->selectedSlot;

        if (! $slot) {
            return;
        }

        $validated = $this->validate([
            'slotEditForm.item_type' => ['required', 'in:stay,move,activity,food,buffer,note'],
            'slotEditForm.time_label' => ['nullable', 'string', 'max:50'],
            'slotEditForm.title' => ['required', 'string', 'max:255'],
            'slotEditForm.location_label' => ['nullable', 'string', 'max:255'],
            'slotEditForm.subject_ref' => ['nullable', 'string', 'max:255'],
            'slotEditForm.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'slotEditForm.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'slotEditForm.summary' => ['nullable', 'string', 'max:1000'],
            'slotEditForm.is_public' => ['boolean'],
        ]);

        [$subjectType, $subjectId] = $this->parseSubjectRef($validated['slotEditForm']['subject_ref']);

        $slot->fill([
            'item_type' => $validated['slotEditForm']['item_type'],
            'time_label' => $validated['slotEditForm']['time_label'] ?: null,
            'title' => $validated['slotEditForm']['title'],
            'location_label' => $validated['slotEditForm']['location_label'] ?: null,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'latitude' => $this->blankToNull($validated['slotEditForm']['latitude']),
            'longitude' => $this->blankToNull($validated['slotEditForm']['longitude']),
            'summary' => $validated['slotEditForm']['summary'] ?: null,
            'is_public' => (bool) $validated['slotEditForm']['is_public'],
        ])->save();

        unset($this->selectedDay, $this->selectedSlot);
        $this->selectSlot($slot->id);

        Flux::toast(variant: 'success', text: __('Slot updated.'));
    }

    public function toggleSlotPublication(int $slotId): void
    {
        $slot = $this->selectedDay?->itineraryItems()->whereKey($slotId)->first();

        if (! $slot) {
            return;
        }

        $slot->update(['is_public' => ! $slot->is_public]);
        unset($this->selectedDay, $this->selectedSlot);

        if ($this->selectedSlotId === $slot->id) {
            $this->selectSlot($slot->id);
        }
    }

    public function moveSlot(int $slotId, string $direction): void
    {
        $day = $this->selectedDay;
        $slot = $day?->itineraryItems->firstWhere('id', $slotId);

        if (! $day || ! $slot || ! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        $slots = $day->itineraryItems->values();
        $index = $slots->search(fn (DayItineraryItem $candidate) => $candidate->id === $slot->id);
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $slots->has($swapIndex)) {
            return;
        }

        $other = $slots->get($swapIndex);
        [$slotOrder, $otherOrder] = [$slot->sort_order, $other->sort_order];

        $slot->update(['sort_order' => $otherOrder]);
        $other->update(['sort_order' => $slotOrder]);

        unset($this->selectedDay, $this->selectedSlot);

        if ($this->selectedSlotId === $slot->id) {
            $this->selectSlot($slot->id);
        }
    }

    public function deleteSlot(int $slotId): void
    {
        $slot = $this->selectedDay?->itineraryItems()->whereKey($slotId)->first();

        if (! $slot) {
            return;
        }

        $slot->delete();
        unset($this->selectedDay, $this->selectedSlot);

        if ($this->selectedSlotId === $slotId) {
            $this->selectedSlotId = null;
            $this->slotEditForm = [];
        }

        Flux::toast(text: __('Slot removed.'));
    }

    public function createTask(): void
    {
        if (! $this->selectedDay) {
            return;
        }

        $validated = $this->validate([
            'taskForm.task_type' => ['required', 'in:todo,fix,booking,research'],
            'taskForm.title' => ['required', 'string', 'max:255'],
            'taskForm.priority' => ['required', 'in:high,medium,low'],
            'taskForm.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->selectedDay->tasks()->create([
            'trip_id' => $this->selectedDay->trip_id,
            'trip_variant_id' => $this->selectedDay->trip_variant_id,
            'stable_key' => 'task-'.Str::lower(Str::random(10)),
            'task_type' => $validated['taskForm']['task_type'],
            'title' => $validated['taskForm']['title'],
            'priority' => $validated['taskForm']['priority'],
            'notes' => $validated['taskForm']['notes'] ?: null,
            'status' => 'open',
            'details' => [],
        ]);

        unset($this->selectedDay);
        $this->taskForm = ['task_type' => 'todo', 'title' => '', 'priority' => 'medium', 'notes' => ''];

        Flux::toast(variant: 'success', text: __('Task added.'));
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

    public function toggleTaskStatus(int $taskId): void
    {
        $task = $this->selectedDay?->tasks()->whereKey($taskId)->first();

        if (! $task) {
            return;
        }

        $task->update(['status' => $task->status === 'done' ? 'open' : 'done']);
        unset($this->selectedDay);
    }

    public function openPlanningIssue(string $issueKey): void
    {
        $issue = $this->findPlanningIssue($issueKey);

        if (! $issue) {
            return;
        }

        if ($issue['target_type'] === 'slot') {
            $this->selectDay($issue['day_id']);
            $this->selectSlot($issue['slot_id']);

            return;
        }

        if ($issue['target_type'] === 'day') {
            $this->selectDay($issue['day_id']);

            return;
        }

        if ($issue['target_type'] === 'asset') {
            $this->assetTab = $issue['asset_tab'];
            $this->selectAsset($issue['asset_id']);
        }
    }

    public function quickFixPlanningIssue(string $issueKey): void
    {
        if (str_starts_with($issueKey, 'day-high-unbooked-')) {
            $this->markPlanningDayPlanned((int) Str::after($issueKey, 'day-high-unbooked-'));

            return;
        }

        if (str_starts_with($issueKey, 'slot-gap-')) {
            $slot = $this->planningSlot((int) Str::after($issueKey, 'slot-gap-'));

            if (! $slot) {
                return;
            }

            match ($this->slotPlanningQuickFix($slot)) {
                'make_slot_private' => $this->makePlanningSlotPrivate($slot->id),
                'copy_slot_coordinates' => $this->copyPlanningSlotCoordinates($slot->id),
                'set_slot_time_placeholder' => $this->setPlanningSlotTimePlaceholder($slot->id),
                default => null,
            };

            return;
        }

        $issue = $this->findPlanningIssue($issueKey);

        if (! $issue || blank($issue['quick_fix'] ?? null)) {
            return;
        }

        match ($issue['quick_fix']) {
            'mark_day_planned' => $this->markPlanningDayPlanned($issue['day_id']),
            'make_slot_private' => $this->makePlanningSlotPrivate($issue['slot_id']),
            'copy_slot_coordinates' => $this->copyPlanningSlotCoordinates($issue['slot_id']),
            'set_slot_time_placeholder' => $this->setPlanningSlotTimePlaceholder($issue['slot_id']),
            default => null,
        };
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
    public function loyaltySnapshot(): ?LoyaltyProgramSnapshot
    {
        return $this->selectedTrip?->loyaltyProgramSnapshots()
            ->with(['vouchers', 'bonusGrabTrips.legs'])
            ->first();
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

    #[Computed]
    public function selectedAsset(): ?Model
    {
        return $this->selectedAssetId ? $this->assetModel()::query()->find($this->selectedAssetId) : null;
    }

    #[Computed]
    public function assets(): EloquentCollection
    {
        $query = $this->assetModel()::query();
        $search = trim($this->assetSearch);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                foreach ($this->assetSearchColumns() as $column) {
                    $query->orWhere($column, 'ilike', '%'.$search.'%');
                }
            });
        }

        $assets = $query->orderBy($this->assetLabelColumn())->limit(40)->get();
        $usageCounts = DayItineraryItem::query()
            ->where('subject_type', $this->assetModel())
            ->whereIn('subject_id', $assets->modelKeys())
            ->selectRaw('subject_id, count(*) as aggregate')
            ->groupBy('subject_id')
            ->pluck('aggregate', 'subject_id');

        return $assets->each(fn (Model $asset) => $asset->setAttribute('usage_count', (int) ($usageCounts[$asset->id] ?? 0)));
    }

    #[Computed]
    public function selectedAssetUsages(): EloquentCollection
    {
        return $this->selectedAsset
            ? DayItineraryItem::query()
                ->with(['dayNode.variant.trip'])
                ->join('day_nodes', 'day_nodes.id', '=', 'day_itinerary_items.day_node_id')
                ->where('day_itinerary_items.subject_type', $this->assetModel())
                ->where('day_itinerary_items.subject_id', $this->selectedAsset->id)
                ->orderBy('day_nodes.day_number')
                ->orderBy('day_itinerary_items.sort_order')
                ->select('day_itinerary_items.*')
                ->limit(20)
                ->get()
            : new EloquentCollection();
    }

    #[Computed]
    public function planningIssues(): array
    {
        if (! $this->selectedTrip) {
            return [];
        }

        return $this->allPlanningIssues()
            ->when($this->planningSeverityFilter !== 'all', fn ($issues) => $issues->where('severity', $this->planningSeverityFilter))
            ->when($this->planningCategoryFilter !== 'all', fn ($issues) => $issues->where('category_key', $this->planningCategoryFilter))
            ->take(16)
            ->values()
            ->all();
    }

    #[Computed]
    public function planningIssueCounts(): array
    {
        $issues = collect($this->planningIssues);

        return [
            'total' => $issues->count(),
            'high' => $issues->where('severity', 'high')->count(),
            'medium' => $issues->where('severity', 'medium')->count(),
            'low' => $issues->where('severity', 'low')->count(),
        ];
    }

    #[Computed]
    public function planningCategoryOptions(): array
    {
        return $this->allPlanningIssues()
            ->mapWithKeys(fn (array $issue): array => [$issue['category_key'] => $issue['category']])
            ->sort()
            ->all();
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

    #[Computed]
    public function slotSubjects(): array
    {
        return [
            'Hotels' => Accommodation::query()->orderBy('name')->get()->map(fn (Accommodation $asset) => [
                'value' => $asset::class.':'.$asset->id,
                'label' => $asset->name,
            ])->all(),
            'Transport' => TransportLeg::query()->orderBy('route_label')->get()->map(fn (TransportLeg $asset) => [
                'value' => $asset::class.':'.$asset->id,
                'label' => $asset->route_label,
            ])->all(),
            'Activities' => Activity::query()->orderBy('name')->get()->map(fn (Activity $asset) => [
                'value' => $asset::class.':'.$asset->id,
                'label' => $asset->name,
            ])->all(),
            'Food' => FoodSpot::query()->orderBy('name')->get()->map(fn (FoodSpot $asset) => [
                'value' => $asset::class.':'.$asset->id,
                'label' => $asset->name,
            ])->all(),
        ];
    }

    public function updatedSelectedTripId(): void
    {
        $this->selectedVariantId = $this->selectedTrip?->defaultVariant()?->id;
        $this->selectedDayId = $this->selectedVariant?->dayNodes()->orderBy('day_number')->value('id');
        $this->selectedSlotId = null;
        $this->slotEditForm = [];
        $this->resetJournalForm();
        $this->loadDayForm();
    }

    public function updatedSelectedVariantId(): void
    {
        $this->selectedDayId = $this->selectedVariant?->dayNodes()->orderBy('day_number')->value('id');
        $this->selectedSlotId = null;
        $this->slotEditForm = [];
        $this->journalForm['trip_variant_id'] = $this->selectedVariantId ? (string) $this->selectedVariantId : '';
        $this->journalForm['day_node_id'] = $this->selectedDayId ? (string) $this->selectedDayId : '';
        $this->journalForm['day_itinerary_item_id'] = '';
        $this->loadDayForm();
    }

    public function updatedJournalFormDayNodeId(): void
    {
        $this->journalForm['day_itinerary_item_id'] = '';
        unset($this->journalSlotOptions);
    }

    public function updatedAssetTab(): void
    {
        $this->selectedAssetId = null;
        $this->assetEditForm = [];
    }

    public function assetLabel(Model $asset): string
    {
        return $asset->route_label ?? $asset->name;
    }

    public function assetLocation(Model $asset): string
    {
        return collect([
            $asset->city ?? $asset->origin ?? null,
            $asset->area ?? $asset->neighborhood ?? $asset->destination ?? null,
        ])->filter()->join(' / ') ?: '—';
    }

    public function assetQualityFlags(Model $asset): array
    {
        return collect([
            ($asset instanceof TransportLeg && blank($asset->geo_path ?? null)) ? __('No route') : null,
            (! ($asset instanceof TransportLeg) && ($asset->latitude === null || $asset->longitude === null)) ? __('No map') : null,
            blank($asset->reservation_url ?? null) ? __('No URL') : null,
            blank($asset->notes ?? null) ? __('No notes') : null,
        ])->filter()->values()->all();
    }

    public function planningSeverityColor(string $severity): string
    {
        return match ($severity) {
            'high' => 'red',
            'medium' => 'amber',
            default => 'zinc',
        };
    }

    private function loadDayForm(): void
    {
        $day = $this->selectedDay;

        $this->dayForm = [
            'title' => $day?->title ?? '',
            'location' => $day?->location ?? '',
            'booking_priority' => $day?->booking_priority ?? 'low',
            'booking_status' => $day?->booking_status ?? 'unbooked',
            'cost_value_min_nok' => $day?->cost_value_min_nok,
            'cost_value_max_nok' => $day?->cost_value_max_nok,
            'cost_premium_min_nok' => $day?->cost_premium_min_nok,
            'cost_premium_max_nok' => $day?->cost_premium_max_nok,
            'rain_backup' => data_get($day?->details, 'rain_backup', ''),
        ];
    }

    /**
     * @return class-string<Accommodation|Activity|FoodSpot|TransportLeg>
     */
    private function assetModel(): string
    {
        return match ($this->assetTab) {
            'activities' => Activity::class,
            'food' => FoodSpot::class,
            'transport' => TransportLeg::class,
            default => Accommodation::class,
        };
    }

    private function assetLabelColumn(): string
    {
        return $this->assetTab === 'transport' ? 'route_label' : 'name';
    }

    private function assetSearchColumns(): array
    {
        return match ($this->assetTab) {
            'activities', 'food' => ['name', 'area', 'city', 'country', 'notes'],
            'transport' => ['route_label', 'mode', 'operator', 'origin', 'destination', 'notes'],
            default => ['name', 'neighborhood', 'city', 'country', 'notes'],
        };
    }

    private function assetEditableFields(): array
    {
        return match ($this->assetTab) {
            'activities' => ['name', 'area', 'city', 'country', 'rain_fit', 'age_fit', 'prebooking_status', 'reservation_url', 'latitude', 'longitude', 'notes'],
            'food' => ['name', 'area', 'city', 'country', 'default_meal_type', 'fallback_type', 'latitude', 'longitude', 'notes'],
            'transport' => ['route_label', 'mode', 'operator', 'origin', 'destination', 'duration_label', 'reservation_url', 'notes'],
            default => ['name', 'neighborhood', 'city', 'country', 'breakfast_note', 'dinner_note', 'reservation_url', 'latitude', 'longitude', 'notes'],
        };
    }

    private function assetValidationRules(): array
    {
        $rules = [];

        foreach ($this->assetEditableFields() as $field) {
            $rules['assetEditForm.'.$field] = match ($field) {
                'name', 'route_label' => ['required', 'string', 'max:255'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'reservation_url' => ['nullable', 'url', 'max:2048'],
                'notes', 'breakfast_note', 'dinner_note' => ['nullable', 'string', 'max:4000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    private function allPlanningIssues(): \Illuminate\Support\Collection
    {
        return collect()
            ->merge($this->publicationPlanningIssues())
            ->merge($this->publicReadinessPlanningIssues())
            ->merge($this->dayPlanningIssues())
            ->merge($this->slotPlanningIssues())
            ->merge($this->assetPlanningIssues());
    }

    private function findPlanningIssue(string $issueKey): ?array
    {
        return $this->allPlanningIssues()->firstWhere('key', $issueKey) ?? $this->fallbackPlanningIssue($issueKey);
    }

    private function fallbackPlanningIssue(string $issueKey): ?array
    {
        if (str_starts_with($issueKey, 'day-high-unbooked-')) {
            return [
                'key' => $issueKey,
                'quick_fix' => 'mark_day_planned',
                'day_id' => (int) Str::after($issueKey, 'day-high-unbooked-'),
                'target_type' => 'day',
            ];
        }

        if (str_starts_with($issueKey, 'slot-gap-')) {
            $slot = $this->planningSlot((int) Str::after($issueKey, 'slot-gap-'));

            return $slot ? [
                'key' => $issueKey,
                'quick_fix' => $this->slotPlanningQuickFix($slot),
                'slot_id' => $slot->id,
                'day_id' => $slot->day_node_id,
                'target_type' => 'slot',
            ] : null;
        }

        return null;
    }

    private function markPlanningDayPlanned(int $dayId): void
    {
        $day = DayNode::query()->whereKey($dayId)->first();

        if (! $day || in_array($day->booking_status, ['booked', 'held'], true)) {
            return;
        }

        $day->update(['booking_status' => 'planned']);
        unset($this->days, $this->selectedDay, $this->planningIssues, $this->planningIssueCounts, $this->planningCategoryOptions);
        $this->loadDayForm();

        Flux::toast(variant: 'success', text: __('Day marked planned.'));
    }

    private function makePlanningSlotPrivate(int $slotId): void
    {
        $slot = $this->planningSlot($slotId);

        if (! $slot) {
            return;
        }

        $slot->update(['is_public' => false]);
        unset($this->selectedDay, $this->selectedSlot, $this->planningIssues, $this->planningIssueCounts, $this->planningCategoryOptions);

        if ($this->selectedSlotId === $slot->id) {
            $this->selectSlot($slot->id);
        }

        Flux::toast(text: __('Slot made private.'));
    }

    private function copyPlanningSlotCoordinates(int $slotId): void
    {
        $slot = $this->planningSlot($slotId);
        $subject = $slot?->subject;

        if (! $slot || ! $subject || $subject instanceof TransportLeg || $subject->latitude === null || $subject->longitude === null) {
            return;
        }

        $slot->update([
            'latitude' => $subject->latitude,
            'longitude' => $subject->longitude,
        ]);
        unset($this->selectedDay, $this->selectedSlot, $this->planningIssues, $this->planningIssueCounts, $this->planningCategoryOptions);

        if ($this->selectedSlotId === $slot->id) {
            $this->selectSlot($slot->id);
        }

        Flux::toast(variant: 'success', text: __('Copied coordinates from asset.'));
    }

    private function setPlanningSlotTimePlaceholder(int $slotId): void
    {
        $slot = $this->planningSlot($slotId);

        if (! $slot || filled($slot->time_label)) {
            return;
        }

        $slot->update(['time_label' => 'needs timing']);
        unset($this->selectedDay, $this->selectedSlot, $this->planningIssues, $this->planningIssueCounts, $this->planningCategoryOptions);

        if ($this->selectedSlotId === $slot->id) {
            $this->selectSlot($slot->id);
        }

        Flux::toast(variant: 'success', text: __('Time placeholder added.'));
    }

    private function planningSlot(int $slotId): ?DayItineraryItem
    {
        return DayItineraryItem::query()
            ->with('subject')
            ->whereKey($slotId)
            ->first();
    }

    private function publicationPlanningIssues(): array
    {
        $issues = [];

        if ($this->selectedTrip?->is_public && $this->selectedTrip->variants()->where('is_public', true)->doesntExist()) {
            $issues[] = [
                'key' => 'trip-public-no-variants-'.$this->selectedTrip->id,
                'severity' => 'high',
                'category_key' => 'publication',
                'category' => __('Publication'),
                'title' => __('Published trip has no public timelines'),
                'detail' => __('Show at least one timeline or unpublish the trip.'),
                'action' => __('Review publishing'),
                'target_type' => 'trip',
            ];
        }

        return $issues;
    }

    private function publicReadinessPlanningIssues(): array
    {
        if (! $this->selectedTrip?->is_public || ! $this->selectedVariant?->is_public) {
            return [];
        }

        return $this->days
            ->filter(fn (DayNode $day): bool => $day->itineraryItems()->where('is_public', true)->doesntExist())
            ->take(4)
            ->map(fn (DayNode $day): array => [
                'key' => 'public-day-empty-'.$day->id,
                'severity' => 'medium',
                'category_key' => 'public',
                'category' => __('Public'),
                'title' => __('Published day has no public slots'),
                'detail' => __('Day :day · :title', ['day' => $day->day_number, 'title' => $day->title]),
                'action' => __('Open day'),
                'quick_fix' => 'mark_day_planned',
                'quick_fix_label' => __('Mark planned'),
                'target_type' => 'day',
                'day_id' => $day->id,
            ])
            ->values()
            ->all();
    }

    private function dayPlanningIssues(): array
    {
        return $this->days
            ->where('booking_priority', 'high')
            ->whereNotIn('booking_status', ['booked', 'held'])
            ->take(5)
            ->map(fn (DayNode $day): array => [
                'key' => 'day-high-unbooked-'.$day->id,
                'severity' => 'high',
                'category_key' => 'booking',
                'category' => __('Booking'),
                'title' => __('High-priority day is not booked'),
                'detail' => __('Day :day · :title', ['day' => $day->day_number, 'title' => $day->title]),
                'action' => __('Open day'),
                'target_type' => 'day',
                'day_id' => $day->id,
            ])
            ->values()
            ->all();
    }

    private function slotPlanningIssues(): array
    {
        if (! $this->selectedVariant) {
            return [];
        }

        return DayItineraryItem::query()
            ->with(['dayNode', 'subject'])
            ->where('trip_variant_id', $this->selectedVariant->id)
            ->where(function ($query): void {
                $query
                    ->whereNull('subject_id')
                    ->orWhereNull('time_label')
                    ->orWhere(function ($query): void {
                        $query->where('is_public', true)
                            ->where(function ($query): void {
                                $query->whereNull('latitude')->orWhereNull('longitude');
                            });
                    });
            })
            ->orderBy('day_node_id')
            ->orderBy('sort_order')
            ->limit(8)
            ->get()
            ->map(fn (DayItineraryItem $slot): array => [
                'key' => 'slot-gap-'.$slot->id,
                'severity' => $slot->is_public && ($slot->latitude === null || $slot->longitude === null) ? 'medium' : 'low',
                'category_key' => 'timeline',
                'category' => __('Timeline'),
                'title' => $this->slotPlanningTitle($slot),
                'detail' => __('Day :day · :title', ['day' => $slot->dayNode->day_number, 'title' => $slot->title]),
                'action' => __('Open slot'),
                'quick_fix' => $this->slotPlanningQuickFix($slot),
                'quick_fix_label' => $this->slotPlanningQuickFixLabel($slot),
                'target_type' => 'slot',
                'day_id' => $slot->day_node_id,
                'slot_id' => $slot->id,
            ])
            ->values()
            ->all();
    }

    private function assetPlanningIssues(): array
    {
        return collect([
            'accommodations' => Accommodation::class,
            'activities' => Activity::class,
            'food' => FoodSpot::class,
            'transport' => TransportLeg::class,
        ])->flatMap(function (string $model, string $assetTab): array {
            return $model::query()
                ->where(function ($query) use ($model): void {
                    $query->whereNull('notes');

                    if ($model !== FoodSpot::class) {
                        $query->orWhereNull('reservation_url');
                    }

                    if ($model !== TransportLeg::class) {
                        $query->orWhereNull('latitude')->orWhereNull('longitude');
                    }
                })
                ->orderBy($model === TransportLeg::class ? 'route_label' : 'name')
                ->limit(2)
                ->get()
                ->map(fn (Model $asset): array => [
                    'key' => 'asset-gap-'.$assetTab.'-'.$asset->id,
                    'severity' => 'low',
                    'category_key' => 'assets',
                    'category' => __('Assets'),
                    'title' => __('Shared asset needs cleanup'),
                    'detail' => $this->assetLabel($asset),
                    'action' => __('Open asset'),
                    'target_type' => 'asset',
                    'asset_tab' => $assetTab,
                    'asset_id' => $asset->id,
                ])
                ->all();
        })->values()->all();
    }

    private function slotPlanningTitle(DayItineraryItem $slot): string
    {
        if ($slot->is_public && ($slot->latitude === null || $slot->longitude === null)) {
            return __('Public slot is missing map coordinates');
        }

        if (! $slot->subject_id) {
            return __('Slot is not linked to a shared asset');
        }

        return __('Slot has no time label');
    }

    private function slotPlanningQuickFix(DayItineraryItem $slot): ?string
    {
        if ($slot->is_public && ($slot->latitude === null || $slot->longitude === null)) {
            return $this->slotCanCopyCoordinates($slot) ? 'copy_slot_coordinates' : 'make_slot_private';
        }

        if (! $slot->time_label) {
            return 'set_slot_time_placeholder';
        }

        return null;
    }

    private function slotPlanningQuickFixLabel(DayItineraryItem $slot): ?string
    {
        return match ($this->slotPlanningQuickFix($slot)) {
            'copy_slot_coordinates' => __('Copy coords'),
            'make_slot_private' => __('Make private'),
            'set_slot_time_placeholder' => __('Needs timing'),
            default => null,
        };
    }

    private function slotCanCopyCoordinates(DayItineraryItem $slot): bool
    {
        $subject = $slot->subject;

        return $subject
            && ! ($subject instanceof TransportLeg)
            && $subject->latitude !== null
            && $subject->longitude !== null;
    }

    private function resetAssetAttachForm(Model $asset): void
    {
        $this->assetAttachForm = [
            'time_label' => '',
            'title' => $this->assetLabel($asset),
            'summary' => '',
            'is_public' => true,
        ];
    }

    private function assetItemType(): string
    {
        return match ($this->assetTab) {
            'accommodations' => 'stay',
            'transport' => 'move',
            'food' => 'food',
            default => 'activity',
        };
    }

    private function assetLocationLabel(Model $asset): ?string
    {
        $location = match ($this->assetTab) {
            'transport' => collect([$asset->origin, $asset->destination])->filter()->join(' to '),
            'accommodations' => collect([$asset->neighborhood, $asset->city])->filter()->join(', '),
            default => collect([$asset->area ?? null, $asset->city ?? null])->filter()->join(', '),
        };

        return $location !== '' ? $location : null;
    }

    private function parseSubjectRef(?string $subjectRef): array
    {
        if (! $subjectRef) {
            return [null, null];
        }

        [$class, $id] = array_pad(explode(':', $subjectRef, 2), 2, null);
        $allowed = [Accommodation::class, TransportLeg::class, Activity::class, FoodSpot::class];

        if (! in_array($class, $allowed, true) || ! $id || ! $class::query()->whereKey($id)->exists()) {
            return [null, null];
        }

        return [$class, (int) $id];
    }

    private function subjectRefForSlot(DayItineraryItem $slot): string
    {
        return $slot->subject_type && $slot->subject_id ? $slot->subject_type.':'.$slot->subject_id : '';
    }

    private function findSubject(?string $subjectType, ?int $subjectId): mixed
    {
        return $subjectType && $subjectId ? $subjectType::query()->find($subjectId) : null;
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

<section class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Manage trips') }}</flux:heading>
            <flux:text>{{ __('Create separate trips, compare timelines, edit day cards, and maintain shared hotels, activities, food spots, and transport assets.') }}</flux:text>
        </div>

        <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
            <div class="space-y-6">
                <flux:card>
                    <flux:heading>{{ __('Trip switcher') }}</flux:heading>
                    <div class="mt-4 space-y-4">
                        <flux:select wire:model.live="selectedTripId" :label="__('Trip')">
                            <flux:select.option value="">{{ __('Select trip') }}</flux:select.option>
                            @foreach ($this->trips as $trip)
                                <flux:select.option value="{{ $trip->id }}">{{ $trip->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="selectedVariantId" :label="__('Timeline')">
                            <flux:select.option value="">{{ __('Select timeline') }}</flux:select.option>
                            @foreach ($this->variants as $variant)
                                <flux:select.option value="{{ $variant->id }}">{{ $variant->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if ($this->selectedTrip)
                            <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ __('Frontend access') }}</div>
                                        <div class="text-zinc-500">{{ $this->selectedTrip->travelerAccessLabel() }}</div>
                                    </div>
                                    <flux:button size="sm" wire:click="toggleTripPublication">
                                        {{ $this->selectedTrip->visibility === 'public' || $this->selectedTrip->is_public ? __('Unpublish') : __('Publish') }}
                                    </flux:button>
                                </div>

                                <div class="mt-3 grid grid-cols-3 gap-2">
                                    @foreach (['private' => __('Private'), 'authenticated' => __('Planning login'), 'public' => __('Public launch')] as $access => $label)
                                        <flux:button size="xs" :variant="$this->selectedTrip->travelerAccessMode() === $access ? 'primary' : 'outline'" wire:click="setTripFrontendAccess('{{ $access }}')">
                                            {{ $label }}
                                        </flux:button>
                                    @endforeach
                                </div>

                                @if ($this->selectedTrip->travelerAccessMode() === 'public' && $this->publicTripUrl())
                                    <flux:link class="mt-3 block truncate" :href="$this->publicTripUrl()" target="_blank">
                                        {{ $this->publicTripUrl() }}
                                    </flux:link>
                                @elseif ($this->selectedTrip->travelerAccessMode() === 'authenticated' && $this->publicTripUrl())
                                    <flux:link class="mt-3 block truncate" :href="$this->publicTripUrl()" target="_blank">
                                        {{ __('Planning login link') }} · {{ $this->publicTripUrl() }}
                                    </flux:link>
                                @endif

                                @if ($this->publicPreviewUrl())
                                    <flux:link class="mt-2 block truncate text-amber-700 dark:text-amber-300" :href="$this->publicPreviewUrl()" target="_blank">
                                        {{ __('Preview current timeline') }}
                                    </flux:link>
                                @endif
                            </div>
                        @endif

                        @if ($this->variants->isNotEmpty())
                            <div class="space-y-2">
                                <div class="text-sm font-medium">{{ __('Published timelines') }}</div>
                                @foreach ($this->variants as $variant)
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                                        <div class="min-w-0">
                                            <div class="truncate font-medium">{{ $variant->name }}</div>
                                            <div class="text-zinc-500">{{ $variant->travelerAccessLabel() }}</div>
                                            @if ($this->publicPreviewUrl($variant))
                                                <flux:link class="mt-1 block truncate text-xs text-amber-700 dark:text-amber-300" :href="$this->publicPreviewUrl($variant)" target="_blank">
                                                    {{ __('Preview') }}
                                                </flux:link>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 gap-1">
                                            @foreach (['private' => __('Private'), 'authenticated' => __('Login'), 'public' => __('Public')] as $access => $label)
                                                <flux:button size="xs" :variant="$variant->travelerAccessMode() === $access ? 'primary' : 'outline'" wire:click="setVariantFrontendAccess({{ $variant->id }}, '{{ $access }}')">
                                                    {{ $label }}
                                                </flux:button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading>{{ __('EuroBonus plan') }}</flux:heading>
                            <flux:text>{{ __('Private points, voucher, and bonus grab tracking for premium flight decisions.') }}</flux:text>
                        </div>
                        <flux:badge size="sm">{{ __('Private') }}</flux:badge>
                    </div>

                    @if ($this->loyaltySnapshot)
                        @php
                            $projectedPoints = $this->loyaltySnapshot->current_points + $this->loyaltySnapshot->signup_bonus_points + $this->loyaltySnapshot->projected_card_points;
                            $projectedLevelPoints = $this->loyaltySnapshot->current_level_points + $this->loyaltySnapshot->expected_trip_level_points + $this->loyaltySnapshot->projected_card_level_points;
                            $levelGap = max(0, $this->loyaltySnapshot->target_level_points - $projectedLevelPoints);
                            $voucherCount = $this->loyaltySnapshot->vouchers->whereIn('status', ['earned', 'expected'])->sum('quantity');
                        @endphp

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="text-zinc-500">{{ __('Projected points') }}</div>
                                <div class="mt-1 text-lg font-semibold">{{ number_format($projectedPoints, 0, ',', ' ') }}</div>
                            </div>
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="text-zinc-500">{{ __('Level gap') }}</div>
                                <div class="mt-1 text-lg font-semibold">{{ number_format($levelGap, 0, ',', ' ') }}</div>
                            </div>
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="text-zinc-500">{{ __('2-for-1 vouchers') }}</div>
                                <div class="mt-1 text-lg font-semibold">{{ $voucherCount }}</div>
                            </div>
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="text-zinc-500">{{ __('Qualification ends') }}</div>
                                <div class="mt-1 text-lg font-semibold">{{ $this->loyaltySnapshot->qualification_ends_on?->format('d.m.Y') ?? __('TBD') }}</div>
                            </div>
                        </div>

                        @if ($this->loyaltySnapshot->bonusGrabTrips->isNotEmpty())
                            <div class="mt-4 space-y-2">
                                <div class="text-sm font-medium">{{ __('Bonus grab candidates') }}</div>
                                @foreach ($this->loyaltySnapshot->bonusGrabTrips as $bonusGrabTrip)
                                    <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="truncate font-medium">{{ $bonusGrabTrip->title }}</div>
                                                <div class="text-zinc-500">{{ $bonusGrabTrip->route_label }} · {{ $bonusGrabTrip->nights_away }} {{ __('nights') }}</div>
                                            </div>
                                            <flux:badge size="sm">{{ $bonusGrabTrip->status }}</flux:badge>
                                        </div>
                                        <div class="mt-2 grid grid-cols-2 gap-2 text-zinc-600 dark:text-zinc-300">
                                            <div>{{ __('Level') }}: {{ number_format($bonusGrabTrip->expected_level_points, 0, ',', ' ') }}</div>
                                            <div>{{ __('Points') }}: {{ number_format($bonusGrabTrip->expected_bonus_points, 0, ',', ' ') }}</div>
                                            <div>{{ __('Cost') }}: {{ $bonusGrabTrip->cash_cost_min_nok ? number_format($bonusGrabTrip->cash_cost_min_nok, 0, ',', ' ') : 'TBD' }}-{{ $bonusGrabTrip->cash_cost_max_nok ? number_format($bonusGrabTrip->cash_cost_max_nok, 0, ',', ' ') : 'TBD' }} NOK</div>
                                            <div>{{ __('Score') }}: {{ $bonusGrabTrip->feasibility_score ?? 'TBD' }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="mt-4 rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                            {{ __('No EuroBonus snapshot yet. Use the MCP loyalty tools to add current points, Amex assumptions, vouchers, and bonus grab candidates.') }}
                        </div>
                    @endif
                </flux:card>

                <flux:card>
                    <flux:heading>{{ __('New trip') }}</flux:heading>
                    <form wire:submit="createTrip" class="mt-4 space-y-4">
                        <flux:input wire:model="tripForm.name" :label="__('Name')" />
                        <flux:textarea wire:model="tripForm.summary" :label="__('Summary')" rows="3" />
                        <div class="grid grid-cols-2 gap-3">
                            <flux:input wire:model="tripForm.starts_on" :label="__('Starts')" type="date" />
                            <flux:input wire:model="tripForm.ends_on" :label="__('Ends')" type="date" />
                        </div>
                        <flux:input wire:model="tripForm.arrival_preference" :label="__('Arrival preference')" />
                        <flux:button type="submit" variant="primary" icon="plus">{{ __('Create trip') }}</flux:button>
                    </form>
                </flux:card>

                <flux:card>
                    <flux:heading>{{ __('New timeline') }}</flux:heading>
                    <form wire:submit="createVariant" class="mt-4 space-y-4">
                        <flux:input wire:model="variantForm.name" :label="__('Name')" />
                        <flux:select wire:model="variantForm.budget_scenario" :label="__('Budget')">
                            <flux:select.option value="value">{{ __('Value') }}</flux:select.option>
                            <flux:select.option value="premium">{{ __('Premium') }}</flux:select.option>
                        </flux:select>
                        <flux:input wire:model="variantForm.stopover_type" :label="__('Stopover')" />
                        <flux:textarea wire:model="variantForm.flight_strategy" :label="__('Flight strategy')" rows="3" />
                        <flux:button type="submit" icon="plus">{{ __('Create timeline') }}</flux:button>
                    </form>
                </flux:card>
            </div>

            <div class="space-y-6">
                <flux:card>
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <flux:heading>{{ __('Planning health') }}</flux:heading>
                            <flux:text>{{ __('Actionable gaps for the selected trip and timeline.') }}</flux:text>
                        </div>
                        <div class="grid grid-cols-4 gap-2 text-center text-sm">
                            <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <div class="font-semibold text-zinc-950 dark:text-white">{{ $this->planningIssueCounts['total'] }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Total') }}</div>
                            </div>
                            <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <div class="font-semibold text-red-600">{{ $this->planningIssueCounts['high'] }}</div>
                                <div class="text-xs text-zinc-500">{{ __('High') }}</div>
                            </div>
                            <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <div class="font-semibold text-amber-600">{{ $this->planningIssueCounts['medium'] }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Medium') }}</div>
                            </div>
                            <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <div class="font-semibold text-zinc-600 dark:text-zinc-300">{{ $this->planningIssueCounts['low'] }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Low') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:max-w-xl">
                        <flux:select wire:model.live="planningSeverityFilter" :label="__('Severity')">
                            <flux:select.option value="all">{{ __('All severities') }}</flux:select.option>
                            <flux:select.option value="high">{{ __('High') }}</flux:select.option>
                            <flux:select.option value="medium">{{ __('Medium') }}</flux:select.option>
                            <flux:select.option value="low">{{ __('Low') }}</flux:select.option>
                        </flux:select>

                        <flux:select wire:model.live="planningCategoryFilter" :label="__('Category')">
                            <flux:select.option value="all">{{ __('All categories') }}</flux:select.option>
                            @foreach ($this->planningCategoryOptions as $categoryKey => $categoryLabel)
                                <flux:select.option value="{{ $categoryKey }}">{{ $categoryLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="mt-5 grid gap-3 lg:grid-cols-2">
                        @forelse ($this->planningIssues as $issue)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="planning-issue-{{ $issue['key'] }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:badge size="sm" color="{{ $this->planningSeverityColor($issue['severity']) }}">{{ $issue['severity'] }}</flux:badge>
                                            <span class="text-xs font-medium uppercase text-zinc-500">{{ $issue['category'] }}</span>
                                        </div>
                                        <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $issue['title'] }}</div>
                                        <div class="mt-1 truncate text-sm text-zinc-500">{{ $issue['detail'] }}</div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @if (($issue['target_type'] ?? null) !== 'trip')
                                            <flux:button size="xs" wire:click="openPlanningIssue('{{ $issue['key'] }}')">
                                                {{ $issue['action'] }}
                                            </flux:button>
                                        @endif
                                        @if (filled($issue['quick_fix'] ?? null))
                                            <flux:button size="xs" variant="primary" wire:click="quickFixPlanningIssue('{{ $issue['key'] }}')">
                                                {{ $issue['quick_fix_label'] }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                                {{ __('No planning gaps found for the current selection.') }}
                            </div>
                        @endforelse
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <flux:heading>{{ __('Journal') }}</flux:heading>
                            <flux:text>{{ __('Trip updates that can attach to the whole route, one day, or one slot.') }}</flux:text>
                        </div>
                        <flux:button size="sm" icon="plus" wire:click="resetJournalForm">
                            {{ __('New entry') }}
                        </flux:button>
                    </div>

                    <div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                        <div class="space-y-3">
                            @forelse ($this->journalEntries as $entry)
                                <button
                                    type="button"
                                    wire:key="journal-entry-{{ $entry->id }}"
                                    wire:click="selectJournalEntry({{ $entry->id }})"
                                    class="block w-full rounded-lg border p-4 text-left transition hover:border-teal-600 {{ $this->selectedJournalEntryId === $entry->id ? 'border-teal-700 bg-teal-50 dark:border-teal-300 dark:bg-teal-950/40' : 'border-zinc-200 dark:border-zinc-700' }}"
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
                                    </div>
                                </button>
                            @empty
                                <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                                    {{ __('No journal entries yet.') }}
                                </div>
                            @endforelse
                        </div>

                        <div>
                            <form wire:submit="{{ $this->selectedJournalEntry ? 'updateJournalEntry' : 'createJournalEntry' }}" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
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
                                        @endif
                                    @endif
                                </div>
                            </form>

                            @if ($this->selectedJournalEntry)
                                <div class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
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
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                        <div class="lg:w-64">
                            <flux:heading>{{ __('Day nodes') }}</flux:heading>
                            <div class="mt-4 max-h-[520px] space-y-2 overflow-auto pr-2">
                                @foreach ($this->days as $day)
                                    <button type="button" wire:click="selectDay({{ $day->id }})" class="w-full rounded-lg border px-3 py-2 text-left text-sm {{ $this->selectedDayId === $day->id ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}">
                                        <div class="font-medium">{{ __('Day') }} {{ $day->day_number }}</div>
                                        <div class="truncate text-zinc-500">{{ $day->title }}</div>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <flux:heading>{{ __('Edit selected day') }}</flux:heading>
                            @if ($this->selectedDay)
                                <form wire:submit="updateDay" class="mt-4 grid gap-4 lg:grid-cols-2">
                                    <flux:input wire:model="dayForm.title" :label="__('Title')" />
                                    <flux:input wire:model="dayForm.location" :label="__('Location')" />
                                    <flux:select wire:model="dayForm.booking_priority" :label="__('Priority')">
                                        <flux:select.option value="high">{{ __('High') }}</flux:select.option>
                                        <flux:select.option value="medium">{{ __('Medium') }}</flux:select.option>
                                        <flux:select.option value="low">{{ __('Low') }}</flux:select.option>
                                    </flux:select>
                                    <flux:select wire:model="dayForm.booking_status" :label="__('Booking status')">
                                        <flux:select.option value="unbooked">{{ __('Unbooked') }}</flux:select.option>
                                        <flux:select.option value="planned">{{ __('Planned') }}</flux:select.option>
                                        <flux:select.option value="held">{{ __('Held') }}</flux:select.option>
                                        <flux:select.option value="booked">{{ __('Booked') }}</flux:select.option>
                                        <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                                    </flux:select>
                                    <flux:input wire:model="dayForm.cost_value_min_nok" :label="__('Value min NOK')" type="number" />
                                    <flux:input wire:model="dayForm.cost_value_max_nok" :label="__('Value max NOK')" type="number" />
                                    <flux:input wire:model="dayForm.cost_premium_min_nok" :label="__('Premium min NOK')" type="number" />
                                    <flux:input wire:model="dayForm.cost_premium_max_nok" :label="__('Premium max NOK')" type="number" />
                                    <div class="lg:col-span-2">
                                        <flux:textarea wire:model="dayForm.rain_backup" :label="__('Rain backup')" rows="3" />
                                    </div>
                                    <div class="lg:col-span-2">
                                        <flux:button type="submit" variant="primary" icon="check">{{ __('Save day') }}</flux:button>
                                    </div>
                                </form>
                            @else
                                <flux:text class="mt-4">{{ __('Select a timeline with days to edit day details.') }}</flux:text>
                            @endif
                        </div>
                    </div>
                </flux:card>

                @if ($this->selectedDay)
                    <flux:card>
                        <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <flux:heading>{{ __('Day slots') }}</flux:heading>
                                <flux:text>{{ __('Loose typed anchors for movement, stays, meals, activities, and buffers. Add times only when they matter.') }}</flux:text>
                            </div>
                            <flux:badge>{{ $this->selectedDay->itineraryItems->count() }} {{ __('slots') }}</flux:badge>
                        </div>

                        <div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                            <div class="space-y-3">
                                @forelse ($this->selectedDay->itineraryItems as $slot)
                                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <flux:badge>{{ $slot->item_type }}</flux:badge>
                                                    @if ($slot->time_label)
                                                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $slot->time_label }}</span>
                                                    @else
                                                        <flux:badge color="amber">{{ __('No time') }}</flux:badge>
                                                    @endif
                                                    @unless ($slot->subject)
                                                        <flux:badge color="amber">{{ __('No asset') }}</flux:badge>
                                                    @endunless
                                                    @if ($slot->latitude === null || $slot->longitude === null)
                                                        <flux:badge color="amber">{{ __('No map') }}</flux:badge>
                                                    @endif
                                                    @unless ($slot->is_public)
                                                        <flux:badge color="zinc">{{ __('Private') }}</flux:badge>
                                                    @endunless
                                                </div>
                                                <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $slot->title }}</div>
                                                <div class="mt-1 text-sm text-zinc-500">
                                                    {{ collect([$slot->location_label, $slot->subject?->name ?? $slot->subject?->route_label])->filter()->join(' · ') }}
                                                </div>
                                                @if ($slot->summary)
                                                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $slot->summary }}</p>
                                                @endif
                                            </div>

                                            <div class="flex flex-wrap gap-2 sm:justify-end">
                                                <flux:button size="xs" icon="arrow-up" wire:click="moveSlot({{ $slot->id }}, 'up')" :disabled="$loop->first">
                                                    {{ __('Up') }}
                                                </flux:button>
                                                <flux:button size="xs" icon="arrow-down" wire:click="moveSlot({{ $slot->id }}, 'down')" :disabled="$loop->last">
                                                    {{ __('Down') }}
                                                </flux:button>
                                                <flux:button size="xs" wire:click="toggleSlotPublication({{ $slot->id }})">
                                                    {{ $slot->is_public ? __('Make private') : __('Make public') }}
                                                </flux:button>
                                                <flux:button size="xs" icon="pencil-square" wire:click="selectSlot({{ $slot->id }})">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:button size="xs" variant="danger" wire:click="deleteSlot({{ $slot->id }})">
                                                    {{ __('Remove') }}
                                                </flux:button>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <flux:text>{{ __('No slots yet. Add only the anchors that help the day make sense.') }}</flux:text>
                                @endforelse
                            </div>

                            <div class="space-y-6">
                                @if ($this->selectedSlot)
                                    <form wire:submit="updateSlot" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                        <div>
                                            <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Edit slot') }}</div>
                                            <div class="mt-1 text-sm text-zinc-500">{{ $this->selectedSlot->title }}</div>
                                        </div>

                                        <flux:select wire:model="slotEditForm.item_type" :label="__('Type')">
                                            <flux:select.option value="stay">{{ __('Stay / hotel') }}</flux:select.option>
                                            <flux:select.option value="move">{{ __('Move / transport') }}</flux:select.option>
                                            <flux:select.option value="activity">{{ __('Activity') }}</flux:select.option>
                                            <flux:select.option value="food">{{ __('Food') }}</flux:select.option>
                                            <flux:select.option value="buffer">{{ __('Buffer') }}</flux:select.option>
                                            <flux:select.option value="note">{{ __('Note') }}</flux:select.option>
                                        </flux:select>

                                        <flux:input wire:model="slotEditForm.time_label" :label="__('Time label')" placeholder="10:30, morning, after lunch" />
                                        <flux:input wire:model="slotEditForm.title" :label="__('Title')" />
                                        <flux:input wire:model="slotEditForm.location_label" :label="__('Location')" />

                                        <flux:select wire:model="slotEditForm.subject_ref" :label="__('Linked shared asset')">
                                            <flux:select.option value="">{{ __('No linked asset') }}</flux:select.option>
                                            @foreach ($this->slotSubjects as $group => $assets)
                                                @foreach ($assets as $asset)
                                                    <flux:select.option value="{{ $asset['value'] }}">{{ $group }} · {{ $asset['label'] }}</flux:select.option>
                                                @endforeach
                                            @endforeach
                                        </flux:select>

                                        <div class="grid grid-cols-2 gap-3">
                                            <flux:input wire:model="slotEditForm.latitude" :label="__('Latitude')" type="number" step="0.0000001" />
                                            <flux:input wire:model="slotEditForm.longitude" :label="__('Longitude')" type="number" step="0.0000001" />
                                        </div>
                                        <flux:textarea wire:model="slotEditForm.summary" :label="__('Traveler note')" rows="3" />
                                        <flux:checkbox wire:model="slotEditForm.is_public" :label="__('Show publicly')" />
                                        <flux:button type="submit" variant="primary" icon="check">{{ __('Save slot') }}</flux:button>
                                    </form>
                                @endif

                                <form wire:submit="createSlot" class="space-y-4">
                                    <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Add slot') }}</div>
                                    <flux:select wire:model="slotForm.item_type" :label="__('Type')">
                                        <flux:select.option value="stay">{{ __('Stay / hotel') }}</flux:select.option>
                                        <flux:select.option value="move">{{ __('Move / transport') }}</flux:select.option>
                                        <flux:select.option value="activity">{{ __('Activity') }}</flux:select.option>
                                        <flux:select.option value="food">{{ __('Food') }}</flux:select.option>
                                        <flux:select.option value="buffer">{{ __('Buffer') }}</flux:select.option>
                                        <flux:select.option value="note">{{ __('Note') }}</flux:select.option>
                                    </flux:select>

                                    <flux:input wire:model="slotForm.time_label" :label="__('Time label')" placeholder="10:30, morning, after lunch" />
                                    <flux:input wire:model="slotForm.title" :label="__('Title')" />
                                    <flux:input wire:model="slotForm.location_label" :label="__('Location')" />

                                    <flux:select wire:model="slotForm.subject_ref" :label="__('Linked shared asset')">
                                        <flux:select.option value="">{{ __('No linked asset') }}</flux:select.option>
                                        @foreach ($this->slotSubjects as $group => $assets)
                                            @foreach ($assets as $asset)
                                                <flux:select.option value="{{ $asset['value'] }}">{{ $group }} · {{ $asset['label'] }}</flux:select.option>
                                            @endforeach
                                        @endforeach
                                    </flux:select>

                                    <div class="grid grid-cols-2 gap-3">
                                        <flux:input wire:model="slotForm.latitude" :label="__('Latitude')" type="number" step="0.0000001" />
                                        <flux:input wire:model="slotForm.longitude" :label="__('Longitude')" type="number" step="0.0000001" />
                                    </div>
                                    <flux:textarea wire:model="slotForm.summary" :label="__('Traveler note')" rows="3" />
                                    <flux:checkbox wire:model="slotForm.is_public" :label="__('Show publicly')" />
                                    <flux:button type="submit" variant="primary" icon="plus">{{ __('Add slot') }}</flux:button>
                                </form>
                            </div>
                        </div>
                    </flux:card>

                    <flux:card>
                        <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <flux:heading>{{ __('Todo / fix list') }}</flux:heading>
                                <flux:text>{{ __('Private planning tasks for unresolved timing, tickets, routes, and cleanup.') }}</flux:text>
                            </div>
                            <flux:badge>{{ $this->selectedDay->tasks->where('status', 'open')->count() }} {{ __('open') }}</flux:badge>
                        </div>

                        <div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                            <div class="space-y-3">
                                @forelse ($this->selectedDay->tasks as $task)
                                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap gap-2">
                                                    <flux:badge>{{ $task->task_type }}</flux:badge>
                                                    <flux:badge color="{{ $task->priority === 'high' ? 'red' : ($task->priority === 'medium' ? 'amber' : 'zinc') }}">{{ $task->priority }}</flux:badge>
                                                    <flux:badge color="{{ $task->status === 'done' ? 'green' : 'zinc' }}">{{ $task->status }}</flux:badge>
                                                </div>
                                                <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $task->title }}</div>
                                                @if ($task->notes)
                                                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $task->notes }}</p>
                                                @endif
                                            </div>

                                            <flux:button size="xs" wire:click="toggleTaskStatus({{ $task->id }})">
                                                {{ $task->status === 'done' ? __('Reopen') : __('Done') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @empty
                                    <flux:text>{{ __('No open planning tasks for this day.') }}</flux:text>
                                @endforelse
                            </div>

                            <form wire:submit="createTask" class="space-y-4">
                                <flux:select wire:model="taskForm.task_type" :label="__('Type')">
                                    <flux:select.option value="todo">{{ __('Todo') }}</flux:select.option>
                                    <flux:select.option value="fix">{{ __('Fix') }}</flux:select.option>
                                    <flux:select.option value="booking">{{ __('Booking') }}</flux:select.option>
                                    <flux:select.option value="research">{{ __('Research') }}</flux:select.option>
                                </flux:select>
                                <flux:input wire:model="taskForm.title" :label="__('Title')" />
                                <flux:select wire:model="taskForm.priority" :label="__('Priority')">
                                    <flux:select.option value="high">{{ __('High') }}</flux:select.option>
                                    <flux:select.option value="medium">{{ __('Medium') }}</flux:select.option>
                                    <flux:select.option value="low">{{ __('Low') }}</flux:select.option>
                                </flux:select>
                                <flux:textarea wire:model="taskForm.notes" :label="__('Notes')" rows="3" />
                                <flux:button type="submit" icon="plus">{{ __('Add task') }}</flux:button>
                            </form>
                        </div>
                    </flux:card>
                @endif

                <flux:card>
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <flux:heading>{{ __('Shared assets') }}</flux:heading>
                            <flux:text>{{ __('Assets are reusable across trips and timelines; day-specific notes live on the attachment.') }}</flux:text>
                        </div>
                        <flux:tabs wire:model.live="assetTab">
                            <flux:tab name="accommodations">{{ __('Hotels') }}</flux:tab>
                            <flux:tab name="activities">{{ __('Activities') }}</flux:tab>
                            <flux:tab name="food">{{ __('Food') }}</flux:tab>
                            <flux:tab name="transport">{{ __('Transport') }}</flux:tab>
                        </flux:tabs>
                    </div>

                    <form wire:submit="createAsset" class="mt-4 grid gap-3 lg:grid-cols-4">
                        <flux:input wire:model="assetForm.name" :label="__('Name')" />
                        <flux:input wire:model="assetForm.city" :label="__('City')" />
                        <flux:input wire:model="assetForm.country" :label="__('Country')" />
                        <div class="flex items-end">
                            <flux:button type="submit" icon="plus">{{ __('Add asset') }}</flux:button>
                        </div>
                    </form>

                    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <div>
                            <flux:input wire:model.live.debounce.300ms="assetSearch" icon="magnifying-glass" :label="__('Search shared assets')" placeholder="{{ __('Name, area, city, route, notes') }}" />

                            <flux:table class="mt-4">
                                <flux:table.columns>
                                    <flux:table.column>{{ __('Asset') }}</flux:table.column>
                                    <flux:table.column>{{ __('Place') }}</flux:table.column>
                                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                                    <flux:table.column>{{ __('') }}</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @forelse ($this->assets as $asset)
                                        <flux:table.row wire:key="asset-row-{{ $this->assetTab }}-{{ $asset->id }}">
                                            <flux:table.cell>
                                                <div class="font-medium text-zinc-950 dark:text-white">{{ $this->assetLabel($asset) }}</div>
                                                @if ($asset->notes)
                                                    <div class="mt-1 max-w-sm truncate text-sm text-zinc-500">{{ $asset->notes }}</div>
                                                @endif
                                            </flux:table.cell>
                                            <flux:table.cell>{{ $this->assetLocation($asset) }}</flux:table.cell>
                                            <flux:table.cell>
                                                <div class="flex flex-wrap gap-1">
                                                    <flux:badge size="sm" color="{{ $asset->usage_count > 0 ? 'blue' : 'zinc' }}">
                                                        {{ trans_choice(':count use|:count uses', $asset->usage_count, ['count' => $asset->usage_count]) }}
                                                    </flux:badge>
                                                    @forelse ($this->assetQualityFlags($asset) as $flag)
                                                        <flux:badge size="sm" color="amber">{{ $flag }}</flux:badge>
                                                    @empty
                                                        <flux:badge size="sm" color="green">{{ __('Ready') }}</flux:badge>
                                                    @endforelse
                                                </div>
                                            </flux:table.cell>
                                            <flux:table.cell>
                                                <flux:button size="xs" icon="pencil-square" wire:click="selectAsset({{ $asset->id }})">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @empty
                                        <flux:table.row>
                                            <flux:table.cell colspan="4">{{ __('No matching shared assets.') }}</flux:table.cell>
                                        </flux:table.row>
                                    @endforelse
                                </flux:table.rows>
                            </flux:table>
                        </div>

                        <div>
                            @if ($this->selectedAsset)
                                <form wire:submit="updateAsset" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                    <div>
                                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Edit shared asset') }}</div>
                                        <div class="mt-1 text-sm text-zinc-500">{{ $this->assetLabel($this->selectedAsset) }}</div>
                                    </div>

                                    @if ($this->assetTab === 'transport')
                                        <flux:input wire:model="assetEditForm.route_label" :label="__('Route label')" />
                                        <div class="grid grid-cols-2 gap-3">
                                            <flux:input wire:model="assetEditForm.mode" :label="__('Mode')" />
                                            <flux:input wire:model="assetEditForm.operator" :label="__('Operator')" />
                                        </div>
                                        <flux:input wire:model="assetEditForm.origin" :label="__('Origin')" />
                                        <flux:input wire:model="assetEditForm.destination" :label="__('Destination')" />
                                        <flux:input wire:model="assetEditForm.duration_label" :label="__('Duration')" />
                                        <flux:input wire:model="assetEditForm.reservation_url" :label="__('Reservation URL')" type="url" />
                                    @else
                                        <flux:input wire:model="assetEditForm.name" :label="__('Name')" />
                                        <div class="grid grid-cols-2 gap-3">
                                            <flux:input wire:model="assetEditForm.city" :label="__('City')" />
                                            <flux:input wire:model="assetEditForm.country" :label="__('Country')" />
                                        </div>

                                        @if ($this->assetTab === 'accommodations')
                                            <flux:input wire:model="assetEditForm.neighborhood" :label="__('Neighborhood')" />
                                            <flux:input wire:model="assetEditForm.breakfast_note" :label="__('Breakfast note')" />
                                            <flux:input wire:model="assetEditForm.dinner_note" :label="__('Dinner note')" />
                                            <flux:input wire:model="assetEditForm.reservation_url" :label="__('Reservation URL')" type="url" />
                                        @elseif ($this->assetTab === 'activities')
                                            <flux:input wire:model="assetEditForm.area" :label="__('Area')" />
                                            <div class="grid grid-cols-2 gap-3">
                                                <flux:input wire:model="assetEditForm.rain_fit" :label="__('Rain fit')" />
                                                <flux:input wire:model="assetEditForm.age_fit" :label="__('Kid fit')" />
                                            </div>
                                            <flux:input wire:model="assetEditForm.prebooking_status" :label="__('Prebooking')" />
                                            <flux:input wire:model="assetEditForm.reservation_url" :label="__('Reservation URL')" type="url" />
                                        @elseif ($this->assetTab === 'food')
                                            <flux:input wire:model="assetEditForm.area" :label="__('Area')" />
                                            <div class="grid grid-cols-2 gap-3">
                                                <flux:input wire:model="assetEditForm.default_meal_type" :label="__('Meal type')" />
                                                <flux:input wire:model="assetEditForm.fallback_type" :label="__('Fallback')" />
                                            </div>
                                        @endif

                                        <div class="grid grid-cols-2 gap-3">
                                            <flux:input wire:model="assetEditForm.latitude" :label="__('Latitude')" type="number" step="0.0000001" />
                                            <flux:input wire:model="assetEditForm.longitude" :label="__('Longitude')" type="number" step="0.0000001" />
                                        </div>
                                    @endif

                                    <flux:textarea wire:model="assetEditForm.notes" :label="__('Notes')" rows="4" />
                                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save asset') }}</flux:button>
                                </form>

                                <form wire:submit="attachAssetToSelectedDay" class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                    <div>
                                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Add to selected day') }}</div>
                                        <div class="mt-1 text-sm text-zinc-500">
                                            {{ $this->selectedDay ? __('Day :day · :title', ['day' => $this->selectedDay->day_number, 'title' => $this->selectedDay->title]) : __('Select a day first.') }}
                                        </div>
                                    </div>

                                    <flux:input wire:model="assetAttachForm.time_label" :label="__('Time label')" placeholder="morning, lunch, arrival night" />
                                    <flux:input wire:model="assetAttachForm.title" :label="__('Timeline title')" />
                                    <flux:textarea wire:model="assetAttachForm.summary" :label="__('Traveler note')" rows="3" />
                                    <flux:checkbox wire:model="assetAttachForm.is_public" :label="__('Show publicly')" />
                                    <flux:button type="submit" icon="plus" :disabled="! $this->selectedDay">{{ __('Attach to day') }}</flux:button>
                                </form>

                                <div class="mt-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Used in') }}</div>
                                        <flux:badge size="sm">{{ $this->selectedAssetUsages->count() }}</flux:badge>
                                    </div>

                                    <div class="mt-3 space-y-2">
                                        @forelse ($this->selectedAssetUsages as $usage)
                                            <div class="rounded-md bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <div class="font-medium text-zinc-950 dark:text-white">
                                                            {{ __('Day') }} {{ $usage->dayNode->day_number }} · {{ $usage->title }}
                                                        </div>
                                                        <div class="mt-1 text-zinc-500">
                                                            {{ collect([$usage->dayNode->variant->trip->name, $usage->dayNode->variant->name, $usage->time_label])->filter()->join(' · ') }}
                                                        </div>
                                                    </div>
                                                    @if ($usage->day_node_id === $this->selectedDayId)
                                                        <flux:button size="xs" variant="danger" wire:click="detachAssetFromSelectedDay({{ $usage->id }})">
                                                            {{ __('Detach') }}
                                                        </flux:button>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-sm text-zinc-500">{{ __('Not attached to any day slots yet.') }}</div>
                                        @endforelse
                                    </div>
                                </div>
                            @else
                                <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                                    {{ __('Select a shared asset to fill coordinates, traveler notes, URLs, and type-specific planning details.') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
</section>
