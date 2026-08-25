<?php

use App\Models\LoyaltyProgramSnapshot;
use App\Models\Trip;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $selectedTripId = null;

    #[Computed]
    public function selectedTrip(): ?Trip
    {
        return $this->selectedTripId ? Trip::query()->find($this->selectedTripId) : null;
    }

    #[Computed]
    public function loyaltySnapshot(): ?LoyaltyProgramSnapshot
    {
        return $this->selectedTrip?->loyaltyProgramSnapshots()
            ->with(['vouchers', 'bonusGrabTrips.legs'])
            ->first();
    }
}; ?>

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
                    <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700" wire:key="bonus-grab-{{ $bonusGrabTrip->id }}">
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
