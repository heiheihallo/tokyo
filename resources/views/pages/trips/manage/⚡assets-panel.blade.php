<?php

use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\FoodSpot;
use App\Models\TransportLeg;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $selectedDayId = null;

    public ?int $selectedAssetId = null;

    public string $assetTab = 'accommodations';

    public string $assetSearch = '';

    public string $assetQualityFilter = 'all';

    public string $assetUsageFilter = 'all';

    public array $assetForm = ['name' => '', 'city' => '', 'country' => '', 'notes' => ''];

    public array $assetEditForm = [];

    public array $assetAttachForm = ['time_label' => '', 'title' => '', 'summary' => '', 'is_public' => true];

    public function mount(): void
    {
        if ($this->selectedAssetId) {
            $this->selectAsset($this->selectedAssetId);
        }
    }

    #[Computed]
    public function selectedDay(): ?DayNode
    {
        return $this->selectedDayId
            ? DayNode::query()->with(['itineraryItems', 'tasks'])->find($this->selectedDayId)
            : null;
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

        Flux::toast(text: __('Asset detached from selected day.'));
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
            $query->where(function (Builder $query) use ($search): void {
                $searchTerm = '%'.mb_strtolower($search).'%';

                foreach ($this->assetSearchColumns() as $column) {
                    $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

                    $query->orWhereRaw("lower({$wrappedColumn}) like ?", [$searchTerm]);
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

        return $assets
            ->each(fn (Model $asset) => $asset->setAttribute('usage_count', (int) ($usageCounts[$asset->id] ?? 0)))
            ->filter(fn (Model $asset): bool => $this->assetMatchesUsageFilter($asset))
            ->filter(fn (Model $asset): bool => $this->assetMatchesQualityFilter($asset))
            ->values();
    }

    #[Computed]
    public function assetLibrarySummary(): array
    {
        $assets = $this->assets;

        return [
            'shown' => $assets->count(),
            'used' => $assets->filter(fn (Model $asset): bool => (int) $asset->usage_count > 0)->count(),
            'needs_cleanup' => $assets->filter(fn (Model $asset): bool => $this->assetQualityFlags($asset) !== [])->count(),
            'mapped' => $assets->filter(fn (Model $asset): bool => $asset instanceof TransportLeg || ($asset->latitude !== null && $asset->longitude !== null))->count(),
        ];
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
            'activities' => ['name', 'area', 'city', 'country', 'rain_fit', 'age_fit', 'prebooking_status', 'reservation_url', 'latitude', 'longitude', 'price_min_nok', 'price_max_nok', 'price_min_jpy', 'price_max_jpy', 'price_basis', 'price_notes', 'notes'],
            'food' => ['name', 'area', 'city', 'country', 'default_meal_type', 'fallback_type', 'latitude', 'longitude', 'price_min_nok', 'price_max_nok', 'price_min_jpy', 'price_max_jpy', 'price_basis', 'price_notes', 'notes'],
            'transport' => ['route_label', 'mode', 'operator', 'origin', 'destination', 'duration_label', 'reservation_url', 'price_min_nok', 'price_max_nok', 'price_min_jpy', 'price_max_jpy', 'price_basis', 'price_notes', 'notes'],
            default => ['name', 'neighborhood', 'city', 'country', 'breakfast_note', 'dinner_note', 'reservation_url', 'latitude', 'longitude', 'price_min_nok', 'price_max_nok', 'price_min_jpy', 'price_max_jpy', 'price_basis', 'price_notes', 'notes'],
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
                'price_min_nok', 'price_max_nok', 'price_min_jpy', 'price_max_jpy' => ['nullable', 'integer', 'min:0'],
                'reservation_url' => ['nullable', 'url', 'max:2048'],
                'notes', 'breakfast_note', 'dinner_note', 'price_notes' => ['nullable', 'string', 'max:4000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    private function assetMatchesUsageFilter(Model $asset): bool
    {
        return match ($this->assetUsageFilter) {
            'used' => (int) $asset->usage_count > 0,
            'unused' => (int) $asset->usage_count === 0,
            default => true,
        };
    }

    private function assetMatchesQualityFilter(Model $asset): bool
    {
        return match ($this->assetQualityFilter) {
            'needs_cleanup' => $this->assetQualityFlags($asset) !== [],
            'mapped' => $asset instanceof TransportLeg || ($asset->latitude !== null && $asset->longitude !== null),
            'priced' => $asset->price_min_nok !== null || $asset->price_min_jpy !== null,
            default => true,
        };
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

    private function blankToNull(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}; ?>

<flux:card>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <flux:heading>{{ __('Shared assets') }}</flux:heading>
            <flux:text>{{ __('Assets are reusable across trips and timelines; day-specific notes live on the attachment.') }}</flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:tabs wire:model.live="assetTab">
                <flux:tab name="accommodations">{{ __('Hotels') }}</flux:tab>
                <flux:tab name="activities">{{ __('Activities') }}</flux:tab>
                <flux:tab name="food">{{ __('Food') }}</flux:tab>
                <flux:tab name="transport">{{ __('Transport') }}</flux:tab>
            </flux:tabs>
            <flux:modal.trigger name="create-asset">
                <flux:button size="sm" icon="plus">{{ __('Asset') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <flux:modal name="create-asset" class="md:w-[34rem]">
        <form wire:submit="createAsset" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('New shared asset') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create a reusable hotel, activity, food spot, or transport leg in the active tab.') }}</flux:text>
            </div>
            <flux:input wire:model="assetForm.name" :label="__('Name')" />
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="assetForm.city" :label="__('City')" />
                <flux:input wire:model="assetForm.country" :label="__('Country')" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="plus">{{ __('Add asset') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
            <div class="text-zinc-500">{{ __('Shown') }}</div>
            <div class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $this->assetLibrarySummary['shown'] }}</div>
        </div>
        <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
            <div class="text-zinc-500">{{ __('Used') }}</div>
            <div class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $this->assetLibrarySummary['used'] }}</div>
        </div>
        <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
            <div class="text-zinc-500">{{ __('Needs cleanup') }}</div>
            <div class="mt-1 text-lg font-semibold {{ $this->assetLibrarySummary['needs_cleanup'] > 0 ? 'text-amber-600' : 'text-zinc-950 dark:text-white' }}">{{ $this->assetLibrarySummary['needs_cleanup'] }}</div>
        </div>
        <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
            <div class="text-zinc-500">{{ __('Map ready') }}</div>
            <div class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $this->assetLibrarySummary['mapped'] }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div>
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_180px]">
                <flux:input wire:model.live.debounce.300ms="assetSearch" icon="magnifying-glass" :label="__('Search shared assets')" placeholder="{{ __('Name, area, city, route, notes') }}" />
                <flux:select wire:model.live="assetUsageFilter" :label="__('Usage')">
                    <flux:select.option value="all">{{ __('All usage') }}</flux:select.option>
                    <flux:select.option value="used">{{ __('Used') }}</flux:select.option>
                    <flux:select.option value="unused">{{ __('Unused') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="assetQualityFilter" :label="__('Quality')">
                    <flux:select.option value="all">{{ __('All quality') }}</flux:select.option>
                    <flux:select.option value="needs_cleanup">{{ __('Needs cleanup') }}</flux:select.option>
                    <flux:select.option value="mapped">{{ __('Mapped') }}</flux:select.option>
                    <flux:select.option value="priced">{{ __('Priced') }}</flux:select.option>
                </flux:select>
            </div>

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
                                <flux:modal.trigger name="edit-asset">
                                    <flux:button size="xs" icon="pencil-square" wire:click="selectAsset({{ $asset->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:modal.trigger>
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
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $this->assetLabel($this->selectedAsset) }}</div>
                            <div class="mt-1 text-sm text-zinc-500">{{ $this->assetLocation($this->selectedAsset) }}</div>
                            @if ($this->selectedAsset->notes)
                                <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $this->selectedAsset->notes }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            <flux:modal.trigger name="edit-asset">
                                <flux:button size="sm" icon="pencil-square">{{ __('Edit') }}</flux:button>
                            </flux:modal.trigger>
                            <flux:modal.trigger name="attach-asset">
                                <flux:button size="sm" icon="plus" :disabled="! $this->selectedDay">{{ __('Attach') }}</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @forelse ($this->assetQualityFlags($this->selectedAsset) as $flag)
                            <flux:badge size="sm" color="amber">{{ $flag }}</flux:badge>
                        @empty
                            <flux:badge size="sm" color="green">{{ __('Ready') }}</flux:badge>
                        @endforelse
                    </div>
                </div>

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

                <flux:modal name="edit-asset" class="md:w-[42rem]">
                    <form wire:submit="updateAsset" class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('Edit shared asset') }}</flux:heading>
                            <flux:text class="mt-2">{{ $this->assetLabel($this->selectedAsset) }}</flux:text>
                        </div>

                        @if ($this->assetTab === 'transport')
                            <flux:input wire:model="assetEditForm.route_label" :label="__('Route label')" />
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:input wire:model="assetEditForm.mode" :label="__('Mode')" />
                                <flux:input wire:model="assetEditForm.operator" :label="__('Operator')" />
                            </div>
                            <flux:input wire:model="assetEditForm.origin" :label="__('Origin')" />
                            <flux:input wire:model="assetEditForm.destination" :label="__('Destination')" />
                            <flux:input wire:model="assetEditForm.duration_label" :label="__('Duration')" />
                            <flux:input wire:model="assetEditForm.reservation_url" :label="__('Reservation URL')" type="url" />
                        @else
                            <flux:input wire:model="assetEditForm.name" :label="__('Name')" />
                            <div class="grid gap-3 sm:grid-cols-2">
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
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <flux:input wire:model="assetEditForm.rain_fit" :label="__('Rain fit')" />
                                    <flux:input wire:model="assetEditForm.age_fit" :label="__('Kid fit')" />
                                </div>
                                <flux:input wire:model="assetEditForm.prebooking_status" :label="__('Prebooking')" />
                                <flux:input wire:model="assetEditForm.reservation_url" :label="__('Reservation URL')" type="url" />
                            @elseif ($this->assetTab === 'food')
                                <flux:input wire:model="assetEditForm.area" :label="__('Area')" />
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <flux:input wire:model="assetEditForm.default_meal_type" :label="__('Meal type')" />
                                    <flux:input wire:model="assetEditForm.fallback_type" :label="__('Fallback')" />
                                </div>
                            @endif

                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:input wire:model="assetEditForm.latitude" :label="__('Latitude')" type="number" step="0.0000001" />
                                <flux:input wire:model="assetEditForm.longitude" :label="__('Longitude')" type="number" step="0.0000001" />
                            </div>
                        @endif

                        <div class="grid gap-3 sm:grid-cols-2">
                            <flux:input wire:model="assetEditForm.price_min_nok" :label="__('Min NOK')" type="number" />
                            <flux:input wire:model="assetEditForm.price_max_nok" :label="__('Max NOK')" type="number" />
                            <flux:input wire:model="assetEditForm.price_min_jpy" :label="__('Min JPY')" type="number" />
                            <flux:input wire:model="assetEditForm.price_max_jpy" :label="__('Max JPY')" type="number" />
                        </div>
                        <flux:input wire:model="assetEditForm.price_basis" :label="__('Price basis')" placeholder="per night, per person, estimate" />
                        <flux:textarea wire:model="assetEditForm.price_notes" :label="__('Price notes')" rows="2" />
                        <flux:textarea wire:model="assetEditForm.notes" :label="__('Notes')" rows="4" />

                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button type="button">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="primary" icon="check">{{ __('Save asset') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>

                <flux:modal name="attach-asset" class="md:w-[34rem]">
                    <form wire:submit="attachAssetToSelectedDay" class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('Add to selected day') }}</flux:heading>
                            <flux:text class="mt-2">
                                {{ $this->selectedDay ? __('Day :day · :title', ['day' => $this->selectedDay->day_number, 'title' => $this->selectedDay->title]) : __('Select a day first.') }}
                            </flux:text>
                        </div>

                        <flux:input wire:model="assetAttachForm.time_label" :label="__('Time label')" placeholder="morning, lunch, arrival night" />
                        <flux:input wire:model="assetAttachForm.title" :label="__('Timeline title')" />
                        <flux:textarea wire:model="assetAttachForm.summary" :label="__('Traveler note')" rows="3" />
                        <flux:checkbox wire:model="assetAttachForm.is_public" :label="__('Show publicly')" />

                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button type="button">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="primary" icon="plus" :disabled="! $this->selectedDay">{{ __('Attach to day') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            @else
                <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                    {{ __('Select a shared asset to fill coordinates, traveler notes, URLs, and type-specific planning details.') }}
                </div>
            @endif
        </div>
    </div>
</flux:card>
