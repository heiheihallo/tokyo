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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $selectedTripId = null;

    public ?int $selectedVariantId = null;

    public string $planningSeverityFilter = 'all';

    public string $planningCategoryFilter = 'all';

    #[Computed]
    public function selectedTrip(): ?Trip
    {
        return $this->selectedTripId ? Trip::query()->find($this->selectedTripId) : null;
    }

    #[Computed]
    public function selectedVariant(): ?TripVariant
    {
        return $this->selectedVariantId ? TripVariant::query()->find($this->selectedVariantId) : null;
    }

    #[Computed]
    public function days(): EloquentCollection
    {
        return $this->selectedVariant
            ? $this->selectedVariant->dayNodes()->with(['itineraryItems', 'tasks'])->orderBy('day_number')->get()
            : new EloquentCollection();
    }

    #[Computed]
    public function planningIssues(): array
    {
        if (! $this->selectedTrip) {
            return [];
        }

        return $this->allPlanningIssues()
            ->when($this->planningSeverityFilter !== 'all', fn (Collection $issues) => $issues->where('severity', $this->planningSeverityFilter))
            ->when($this->planningCategoryFilter !== 'all', fn (Collection $issues) => $issues->where('category_key', $this->planningCategoryFilter))
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

    public function openPlanningIssue(string $issueKey): void
    {
        $issue = $this->findPlanningIssue($issueKey);

        if (! $issue) {
            return;
        }

        $this->dispatch('trip-management-open-planning-issue', issue: $issue);
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

    public function planningSeverityColor(string $severity): string
    {
        return match ($severity) {
            'high' => 'red',
            'medium' => 'amber',
            default => 'zinc',
        };
    }

    private function allPlanningIssues(): Collection
    {
        return collect()
            ->merge($this->publicationPlanningIssues())
            ->merge($this->publicReadinessPlanningIssues())
            ->merge($this->journalPlanningIssues())
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
        unset($this->days, $this->planningIssues, $this->planningIssueCounts, $this->planningCategoryOptions);

        Flux::toast(variant: 'success', text: __('Day marked planned.'));
    }

    private function makePlanningSlotPrivate(int $slotId): void
    {
        $slot = $this->planningSlot($slotId);

        if (! $slot) {
            return;
        }

        $slot->update(['is_public' => false]);
        unset($this->days, $this->planningIssues, $this->planningIssueCounts, $this->planningCategoryOptions);

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
        unset($this->days, $this->planningIssues, $this->planningIssueCounts, $this->planningCategoryOptions);

        Flux::toast(variant: 'success', text: __('Copied coordinates from asset.'));
    }

    private function setPlanningSlotTimePlaceholder(int $slotId): void
    {
        $slot = $this->planningSlot($slotId);

        if (! $slot || filled($slot->time_label)) {
            return;
        }

        $slot->update(['time_label' => 'needs timing']);
        unset($this->days, $this->planningIssues, $this->planningIssueCounts, $this->planningCategoryOptions);

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

    private function journalPlanningIssues(): array
    {
        if (! $this->selectedTrip) {
            return [];
        }

        $issues = [];
        $tripAccess = $this->selectedTrip->travelerAccessMode();

        if (in_array($tripAccess, ['authenticated', 'public'], true) && $this->selectedTrip->journalEntries()->whereNotNull('published_at')->doesntExist()) {
            $issues[] = [
                'key' => 'journal-no-published-'.$this->selectedTrip->id,
                'severity' => 'low',
                'category_key' => 'journal',
                'category' => __('Journal'),
                'title' => __('Traveler frontend has no journal updates'),
                'detail' => __('Add at least one published update before sharing widely.'),
                'action' => __('Open journal'),
                'target_type' => 'trip',
            ];
        }

        $publishedEntries = $this->selectedTrip->journalEntries()
            ->with('media')
            ->whereNotNull('published_at')
            ->limit(20)
            ->get();

        foreach ($publishedEntries as $entry) {
            if (blank($entry->excerpt) && blank($entry->body) && $entry->publicMedia()->isEmpty()) {
                $issues[] = [
                    'key' => 'journal-empty-'.$entry->id,
                    'severity' => 'medium',
                    'category_key' => 'journal',
                    'category' => __('Journal'),
                    'title' => __('Published journal entry has no traveler detail'),
                    'detail' => $entry->title,
                    'action' => __('Edit entry'),
                    'target_type' => 'journal',
                    'journal_entry_id' => $entry->id,
                ];
            }

            foreach ($entry->publicMedia() as $media) {
                if (blank(data_get($media->custom_properties, 'alt'))) {
                    $issues[] = [
                        'key' => 'journal-media-alt-'.$media->id,
                        'severity' => 'low',
                        'category_key' => 'journal',
                        'category' => __('Journal'),
                        'title' => __('Public journal image is missing alt text'),
                        'detail' => $entry->title,
                        'action' => __('Edit entry'),
                        'target_type' => 'journal',
                        'journal_entry_id' => $entry->id,
                    ];
                }
            }

            if ($tripAccess === 'authenticated' && $entry->visibility === 'public' && $entry->publicMedia()->isNotEmpty()) {
                $issues[] = [
                    'key' => 'journal-public-media-login-trip-'.$entry->id,
                    'severity' => 'low',
                    'category_key' => 'journal',
                    'category' => __('Journal'),
                    'title' => __('Public journal media on planning-login trip'),
                    'detail' => $entry->title,
                    'action' => __('Edit entry'),
                    'target_type' => 'journal',
                    'journal_entry_id' => $entry->id,
                ];
            }
        }

        return collect($issues)->take(8)->values()->all();
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

    private function assetLabel(Model $asset): string
    {
        return $asset->route_label ?? $asset->name;
    }
}; ?>

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
