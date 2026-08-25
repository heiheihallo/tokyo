<?php

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\FoodSpot;
use App\Models\JournalEntry;
use App\Models\TransportLeg;
use App\Models\Trip;
use App\Models\TripVariant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $selectedTripId = null;

    public ?int $selectedVariantId = null;

    public ?int $selectedDayId = null;

    public ?int $selectedSlotId = null;

    public array $dayForm = [];

    public array $slotForm = ['item_type' => 'activity', 'time_label' => '', 'title' => '', 'location_label' => '', 'subject_ref' => '', 'latitude' => '', 'longitude' => '', 'summary' => '', 'is_public' => true];

    public array $slotEditForm = [];

    public array $taskForm = ['task_type' => 'todo', 'title' => '', 'priority' => 'medium', 'notes' => ''];

    public function mount(): void
    {
        $this->selectedDayId ??= $this->selectedVariant?->dayNodes()->orderBy('day_number')->value('id');
        $this->loadDayForm();
    }

    public function startJournalForSelectedDay(): void
    {
        if (! $this->selectedDay) {
            return;
        }

        $this->dispatch('trip-management-start-day-journal', dayId: $this->selectedDay->id);
    }

    public function startJournalForSelectedSlot(int $slotId): void
    {
        $slot = $this->selectedDay?->itineraryItems()->whereKey($slotId)->first();

        if (! $slot) {
            return;
        }

        $this->dispatch('trip-management-start-slot-journal', dayId: $slot->day_node_id, slotId: $slot->id);
    }

    public function selectDay(int $dayId): void
    {
        $this->selectedDayId = $dayId;
        $this->selectedSlotId = null;
        $this->dispatch('trip-management-day-selection-changed', dayId: $dayId);
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

    public function createQuickDaySlot(string $type): void
    {
        $day = $this->selectedDay;

        if (! $day || ! in_array($type, ['stay', 'move', 'activity', 'food', 'buffer'], true)) {
            return;
        }

        $subject = match ($type) {
            'stay' => $day->accommodations->first(),
            'move' => $day->transportLegs->first(),
            'food' => $day->foodSpots->first(),
            'activity' => $day->activities->first(),
            default => null,
        };

        $title = match ($type) {
            'stay' => $subject ? __('Stay at :name', ['name' => $this->assetLabel($subject)]) : __('Hotel stay'),
            'move' => $subject ? $this->assetLabel($subject) : __('Movement anchor'),
            'food' => $subject ? $this->assetLabel($subject) : __('Food anchor'),
            'buffer' => __('Flexible buffer'),
            default => $subject ? $this->assetLabel($subject) : __('Activity anchor'),
        };

        $day->itineraryItems()->create([
            'trip_id' => $day->trip_id,
            'trip_variant_id' => $day->trip_variant_id,
            'stable_key' => 'quick-slot-'.Str::lower(Str::random(10)),
            'item_type' => $type,
            'time_label' => $this->quickSlotTimeLabel($type),
            'title' => $title,
            'location_label' => $subject instanceof Model ? $this->assetLocationLabelForModel($subject) : $day->location,
            'subject_type' => $subject instanceof Model ? $subject::class : null,
            'subject_id' => $subject instanceof Model ? $subject->id : null,
            'latitude' => $subject instanceof TransportLeg ? null : ($subject->latitude ?? null),
            'longitude' => $subject instanceof TransportLeg ? null : ($subject->longitude ?? null),
            'summary' => __('Fill in the traveler-facing note when the timing is clearer.'),
            'is_public' => true,
            'sort_order' => ($day->itineraryItems()->max('sort_order') ?? 0) + 10,
            'details' => [],
        ]);

        unset($this->selectedDay);

        Flux::toast(variant: 'success', text: __('Quick slot added.'));
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

    #[Computed]
    public function selectedTrip(): ?Trip
    {
        return $this->selectedTripId ? Trip::find($this->selectedTripId) : null;
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
    public function selectedDayJournalEntries(): EloquentCollection
    {
        return $this->selectedDay
            ? $this->selectedDay->journalEntries()
                ->with(['dayItineraryItem', 'media'])
                ->limit(10)
                ->get()
            : new EloquentCollection();
    }

    #[Computed]
    public function selectedDayMapStatus(): array
    {
        $slots = $this->selectedDay?->itineraryItems ?? collect();
        $publicSlots = $slots->where('is_public', true);

        return [
            'public_slots' => $publicSlots->count(),
            'mapped_slots' => $publicSlots
                ->filter(fn (DayItineraryItem $slot): bool => $slot->latitude !== null && $slot->longitude !== null)
                ->count(),
            'missing_slots' => $publicSlots
                ->filter(fn (DayItineraryItem $slot): bool => $slot->latitude === null || $slot->longitude === null)
                ->count(),
        ];
    }

    #[Computed]
    public function selectedDayBoardSummary(): array
    {
        $day = $this->selectedDay;

        if (! $day) {
            return [
                'stays' => 0,
                'moves' => 0,
                'activities' => 0,
                'food' => 0,
                'private_slots' => 0,
            ];
        }

        return [
            'stays' => $day->itineraryItems->where('item_type', 'stay')->count(),
            'moves' => $day->itineraryItems->where('item_type', 'move')->count(),
            'activities' => $day->itineraryItems->where('item_type', 'activity')->count(),
            'food' => $day->itineraryItems->where('item_type', 'food')->count(),
            'private_slots' => $day->itineraryItems->where('is_public', false)->count(),
        ];
    }

    public function publicSelectedDayUrl(): ?string
    {
        if (! $this->selectedTrip || ! $this->selectedVariant || ! $this->selectedDay) {
            return null;
        }

        return route('trips.public.days.show', array_filter([
            'trip' => $this->selectedTrip,
            'variant' => $this->selectedVariant,
            'dayNode' => $this->selectedDay,
            'slot' => $this->selectedSlot?->stable_key,
            'preview' => 1,
        ], fn ($value) => $value !== null));
    }

    public function publicJournalUrl(): ?string
    {
        if (! $this->selectedTrip) {
            return null;
        }

        return route('trips.public.journal', array_filter([
            'trip' => $this->selectedTrip,
            'timeline' => $this->selectedVariant?->slug,
            'day' => $this->selectedDay?->stable_key,
            'preview' => 1,
        ], fn ($value) => $value !== null));
    }

    public function publicGuestPreviewUrl(?TripVariant $variant = null): ?string
    {
        if (! $this->selectedTrip) {
            return null;
        }

        return route('trips.public', array_filter([
            'trip' => $this->selectedTrip,
            'timeline' => $variant?->slug ?? $this->selectedVariant?->slug,
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

    public function assetLabel(Model $asset): string
    {
        return $asset->route_label ?? $asset->name;
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

    private function assetLocationLabelForModel(Model $asset): ?string
    {
        return match (true) {
            $asset instanceof TransportLeg => collect([$asset->origin, $asset->destination])->filter()->join(' to ') ?: null,
            $asset instanceof Accommodation => collect([$asset->neighborhood, $asset->city])->filter()->join(', ') ?: null,
            $asset instanceof Activity, $asset instanceof FoodSpot => collect([$asset->area ?? null, $asset->city ?? null])->filter()->join(', ') ?: null,
            default => null,
        };
    }

    private function quickSlotTimeLabel(string $type): string
    {
        return match ($type) {
            'stay' => 'overnight',
            'move' => 'move window',
            'food' => 'meal window',
            'buffer' => 'flex',
            default => 'activity window',
        };
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
}; ?>

<div class="space-y-6">
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
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ __('Selected day') }}</flux:heading>
                    <flux:text>{{ __('Review the current day and open the editor only when you need it.') }}</flux:text>
                </div>
                @if ($this->selectedDay)
                    <flux:modal.trigger name="edit-day">
                        <flux:button size="sm" icon="pencil-square">{{ __('Edit day') }}</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
            @if ($this->selectedDay)
                <div class="mt-4 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <div class="font-medium text-zinc-950 dark:text-white">{{ $this->selectedDay->title }}</div>
                    <div class="mt-1 text-zinc-500">{{ __('Day :day', ['day' => $this->selectedDay->day_number]) }} · {{ $this->selectedDay->location }}</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <flux:badge color="{{ $this->selectedDay->booking_priority === 'high' ? 'red' : ($this->selectedDay->booking_priority === 'medium' ? 'amber' : 'zinc') }}">{{ $this->selectedDay->booking_priority }}</flux:badge>
                        <flux:badge color="{{ $this->selectedDay->booking_status === 'booked' ? 'green' : 'zinc' }}">{{ $this->selectedDay->booking_status }}</flux:badge>
                        <flux:badge>{{ $this->selectedDay->itineraryItems->count() }} {{ __('slots') }}</flux:badge>
                    </div>
                </div>

                <flux:modal name="edit-day" class="md:w-[42rem]">
                    <form wire:submit="updateDay" class="grid gap-4 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <flux:heading size="lg">{{ __('Edit day') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Update private planning status and traveler-facing day backup notes.') }}</flux:text>
                    </div>
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
                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button type="button">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="primary" icon="check">{{ __('Save day') }}</flux:button>
                        </div>
                    </div>
                    </form>
                </flux:modal>
            @else
                <flux:text class="mt-4">{{ __('Select a timeline with days to edit day details.') }}</flux:text>
            @endif
        </div>
    </div>
</flux:card>

@if ($this->selectedDay)
    <flux:card>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <flux:heading>{{ __('Day workspace') }}</flux:heading>
                <flux:text>{{ __('One place to check public preview, map readiness, open tasks, and journal coverage for the selected day.') }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($this->publicSelectedDayUrl())
                    <flux:button size="sm" icon="arrow-top-right-on-square" :href="$this->publicSelectedDayUrl()" target="_blank">
                        {{ __('Preview day') }}
                    </flux:button>
                @endif
                @if ($this->publicJournalUrl())
                    <flux:button size="sm" icon="newspaper" :href="$this->publicJournalUrl()" target="_blank">
                        {{ __('Journal feed') }}
                    </flux:button>
                @endif
                <flux:button size="sm" icon="pencil-square" wire:click="startJournalForSelectedDay">
                    {{ __('New day journal') }}
                </flux:button>
            </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                <div class="text-zinc-500">{{ __('Public slots') }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $this->selectedDayMapStatus['public_slots'] }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                <div class="text-zinc-500">{{ __('Mapped slots') }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $this->selectedDayMapStatus['mapped_slots'] }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                <div class="text-zinc-500">{{ __('Map gaps') }}</div>
                <div class="mt-1 text-lg font-semibold {{ $this->selectedDayMapStatus['missing_slots'] > 0 ? 'text-amber-600' : 'text-zinc-950 dark:text-white' }}">{{ $this->selectedDayMapStatus['missing_slots'] }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                <div class="text-zinc-500">{{ __('Open tasks') }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $this->selectedDay->tasks->where('status', 'open')->count() }}</div>
            </div>
        </div>

        <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Day board') }}</div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <div class="text-zinc-500">{{ __('Stay') }}</div>
                        <div class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ $this->selectedDayBoardSummary['stays'] }}</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <div class="text-zinc-500">{{ __('Move') }}</div>
                        <div class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ $this->selectedDayBoardSummary['moves'] }}</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <div class="text-zinc-500">{{ __('Do') }}</div>
                        <div class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ $this->selectedDayBoardSummary['activities'] }}</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <div class="text-zinc-500">{{ __('Eat') }}</div>
                        <div class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ $this->selectedDayBoardSummary['food'] }}</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <div class="text-zinc-500">{{ __('Private') }}</div>
                        <div class="mt-1 font-semibold text-zinc-950 dark:text-white">{{ $this->selectedDayBoardSummary['private_slots'] }}</div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <flux:button size="xs" icon="building-office-2" wire:click="createQuickDaySlot('stay')">{{ __('Stay slot') }}</flux:button>
                    <flux:button size="xs" icon="paper-airplane" wire:click="createQuickDaySlot('move')">{{ __('Move slot') }}</flux:button>
                    <flux:button size="xs" icon="sparkles" wire:click="createQuickDaySlot('activity')">{{ __('Activity slot') }}</flux:button>
                    <flux:button size="xs" icon="map-pin" wire:click="createQuickDaySlot('food')">{{ __('Food slot') }}</flux:button>
                    <flux:button size="xs" icon="clock" wire:click="createQuickDaySlot('buffer')">{{ __('Buffer') }}</flux:button>
                </div>
            </section>

            <section class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Traveler previews') }}</div>
                <div class="mt-3 space-y-2">
                    @if ($this->publicSelectedDayUrl())
                        <flux:button class="w-full justify-start" size="sm" icon="arrow-top-right-on-square" :href="$this->publicSelectedDayUrl()" target="_blank">
                            {{ __('Preview as admin') }}
                        </flux:button>
                    @endif
                    @if ($this->publicGuestPreviewUrl())
                        <flux:button class="w-full justify-start" size="sm" icon="eye" :href="$this->publicGuestPreviewUrl()" target="_blank">
                            {{ __('Preview as guest') }}
                        </flux:button>
                    @endif
                    @if ($this->selectedTrip?->travelerAccessMode() === 'authenticated')
                        <div class="rounded-lg bg-amber-50 p-3 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            {{ __('Planning-login views require a signed-in account; public launch is still private to guests.') }}
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <section>
                <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Journal coverage') }}</div>
                <div class="mt-3 space-y-2">
                    @forelse ($this->selectedDayJournalEntries as $entry)
                        <button type="button" wire:key="day-workspace-journal-{{ $entry->id }}" wire:click="selectJournalEntry({{ $entry->id }})" class="block w-full rounded-lg border border-zinc-200 px-3 py-2 text-left text-sm hover:border-teal-600 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge color="{{ $entry->published_at ? 'teal' : 'zinc' }}">{{ $entry->published_at ? __('Published') : __('Draft') }}</flux:badge>
                                <flux:badge color="{{ $entry->visibility === 'public' ? 'green' : ($entry->visibility === 'family' ? 'amber' : 'zinc') }}">{{ $entry->visibilityLabel() }}</flux:badge>
                            </div>
                            <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $entry->title }}</div>
                            @if ($entry->dayItineraryItem)
                                <div class="mt-1 text-zinc-500">{{ $entry->dayItineraryItem->title }}</div>
                            @endif
                        </button>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                            {{ __('No journal updates attached to this day yet.') }}
                        </div>
                    @endforelse
                </div>
            </section>

            <section>
                <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Open fixes') }}</div>
                <div class="mt-3 space-y-2">
                    @forelse ($this->selectedDay->tasks->where('status', 'open')->take(5) as $task)
                        <div class="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge>{{ $task->task_type }}</flux:badge>
                                <flux:badge color="{{ $task->priority === 'high' ? 'red' : ($task->priority === 'medium' ? 'amber' : 'zinc') }}">{{ $task->priority }}</flux:badge>
                            </div>
                            <div class="mt-2 font-medium text-zinc-950 dark:text-white">{{ $task->title }}</div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                            {{ __('No open fixes for this day.') }}
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </flux:card>

    <flux:card>
        <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <flux:heading>{{ __('Day slots') }}</flux:heading>
                <flux:text>{{ __('Loose typed anchors for movement, stays, meals, activities, and buffers. Add times only when they matter.') }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:badge>{{ $this->selectedDay->itineraryItems->count() }} {{ __('slots') }}</flux:badge>
                <flux:modal.trigger name="create-slot">
                    <flux:button size="sm" icon="plus">{{ __('Slot') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <div class="mt-5">
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
                                <flux:button size="xs" icon="newspaper" wire:click="startJournalForSelectedSlot({{ $slot->id }})">
                                    {{ __('Journal') }}
                                </flux:button>
                                <flux:modal.trigger name="edit-slot">
                                    <flux:button size="xs" icon="pencil-square" wire:click="selectSlot({{ $slot->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:modal.trigger>
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
        </div>

        <flux:modal name="edit-slot" class="md:w-[40rem]">
                @if ($this->selectedSlot)
                    <form wire:submit="updateSlot" class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('Edit slot') }}</flux:heading>
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
                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button type="button">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="primary" icon="check">{{ __('Save slot') }}</flux:button>
                        </div>
                    </form>
                @endif
        </flux:modal>

        <flux:modal name="create-slot" class="md:w-[40rem]">
                <form wire:submit="createSlot" class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Add slot') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Add only the anchors that help the day make sense.') }}</flux:text>
                    </div>
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
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary" icon="plus">{{ __('Add slot') }}</flux:button>
                    </div>
                </form>
        </flux:modal>
    </flux:card>

    <flux:card>
        <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <flux:heading>{{ __('Todo / fix list') }}</flux:heading>
                <flux:text>{{ __('Private planning tasks for unresolved timing, tickets, routes, and cleanup.') }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:badge>{{ $this->selectedDay->tasks->where('status', 'open')->count() }} {{ __('open') }}</flux:badge>
                <flux:modal.trigger name="create-task">
                    <flux:button size="sm" icon="plus">{{ __('Task') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <div class="mt-5">
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
        </div>

        <flux:modal name="create-task" class="md:w-[32rem]">
            <form wire:submit="createTask" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Add task') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Capture a private planning todo, fix, booking, or research item.') }}</flux:text>
                </div>
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
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="plus">{{ __('Add task') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </flux:card>
@endif

</div>
