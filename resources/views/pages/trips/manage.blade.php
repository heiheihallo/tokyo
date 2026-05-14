<?php

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\DayTask;
use App\Models\FoodSpot;
use App\Models\TransportLeg;
use App\Models\Trip;
use App\Models\TripVariant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage trips')] class extends Component {
    public ?int $selectedTripId = null;
    public ?int $selectedVariantId = null;
    public ?int $selectedDayId = null;
    public string $assetTab = 'accommodations';

    public array $tripForm = ['name' => '', 'summary' => '', 'starts_on' => '', 'ends_on' => '', 'arrival_preference' => 'HND'];
    public array $variantForm = ['name' => '', 'budget_scenario' => 'value', 'stopover_type' => '', 'flight_strategy' => ''];
    public array $dayForm = [];
    public array $slotForm = ['item_type' => 'activity', 'time_label' => '', 'title' => '', 'location_label' => '', 'subject_ref' => '', 'summary' => '', 'is_public' => true];
    public array $taskForm = ['task_type' => 'todo', 'title' => '', 'priority' => 'medium', 'notes' => ''];
    public array $assetForm = ['name' => '', 'city' => '', 'country' => '', 'notes' => ''];

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

        if ($this->selectedTrip->is_public) {
            $this->selectedTrip->unpublish();

            Flux::toast(text: __('Trip unpublished.'));

            return;
        }

        $this->selectedTrip->publish();

        Flux::toast(variant: 'success', text: __('Trip published.'));
    }

    public function toggleVariantPublication(int $variantId): void
    {
        $variant = $this->selectedTrip?->variants()->whereKey($variantId)->first();

        if (! $variant) {
            return;
        }

        if ($variant->is_public) {
            $variant->unpublish();

            Flux::toast(text: __('Timeline unpublished.'));

            return;
        }

        $variant->publish();

        Flux::toast(variant: 'success', text: __('Timeline published.'));
    }

    public function selectDay(int $dayId): void
    {
        $this->selectedDayId = $dayId;
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
            'slotForm.summary' => ['nullable', 'string', 'max:1000'],
            'slotForm.is_public' => ['boolean'],
        ]);

        [$subjectType, $subjectId] = $this->parseSubjectRef($validated['slotForm']['subject_ref']);

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
            'summary' => $validated['slotForm']['summary'] ?: null,
            'is_public' => (bool) $validated['slotForm']['is_public'],
            'sort_order' => ($this->selectedDay->itineraryItems()->max('sort_order') ?? 0) + 10,
            'details' => [],
        ]);

        unset($this->selectedDay);
        $this->slotForm = ['item_type' => 'activity', 'time_label' => '', 'title' => '', 'location_label' => '', 'subject_ref' => '', 'summary' => '', 'is_public' => true];

        Flux::toast(variant: 'success', text: __('Slot added.'));
    }

    public function deleteSlot(int $slotId): void
    {
        $slot = $this->selectedDay?->itineraryItems()->whereKey($slotId)->first();

        if (! $slot) {
            return;
        }

        $slot->delete();
        unset($this->selectedDay);

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
    public function assets(): EloquentCollection
    {
        return $this->assetModel()::query()->orderBy('name')->limit(20)->get();
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
        $this->loadDayForm();
    }

    public function updatedSelectedVariantId(): void
    {
        $this->selectedDayId = $this->selectedVariant?->dayNodes()->orderBy('day_number')->value('id');
        $this->loadDayForm();
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
                                        <div class="font-medium">{{ __('Public trip page') }}</div>
                                        <div class="text-zinc-500">{{ $this->selectedTrip->is_public ? __('Published') : __('Private') }}</div>
                                    </div>
                                    <flux:button size="sm" wire:click="toggleTripPublication">
                                        {{ $this->selectedTrip->is_public ? __('Unpublish') : __('Publish') }}
                                    </flux:button>
                                </div>

                                @if ($this->selectedTrip->is_public)
                                    <flux:link class="mt-3 block truncate" :href="route('trips.public', $this->selectedTrip)" target="_blank">
                                        {{ route('trips.public', $this->selectedTrip) }}
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
                                            <div class="text-zinc-500">{{ $variant->is_public ? __('Visible publicly') : __('Hidden publicly') }}</div>
                                        </div>
                                        <flux:button size="xs" wire:click="toggleVariantPublication({{ $variant->id }})">
                                            {{ $variant->is_public ? __('Hide') : __('Show') }}
                                        </flux:button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
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

                                            <flux:button size="xs" variant="danger" wire:click="deleteSlot({{ $slot->id }})">
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @empty
                                    <flux:text>{{ __('No slots yet. Add only the anchors that help the day make sense.') }}</flux:text>
                                @endforelse
                            </div>

                            <form wire:submit="createSlot" class="space-y-4">
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

                                <flux:textarea wire:model="slotForm.summary" :label="__('Traveler note')" rows="3" />
                                <flux:checkbox wire:model="slotForm.is_public" :label="__('Show publicly')" />
                                <flux:button type="submit" variant="primary" icon="plus">{{ __('Add slot') }}</flux:button>
                            </form>
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

                    <flux:table class="mt-4">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                            <flux:table.column>{{ __('City') }}</flux:table.column>
                            <flux:table.column>{{ __('Notes') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->assets as $asset)
                                <flux:table.row>
                                    <flux:table.cell>{{ $asset->name ?? $asset->route_label }}</flux:table.cell>
                                    <flux:table.cell>{{ $asset->city ?? $asset->origin ?? '—' }}</flux:table.cell>
                                    <flux:table.cell class="max-w-lg truncate">{{ $asset->notes }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            </div>
        </div>
</section>
