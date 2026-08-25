<?php

use App\Models\Trip;
use App\Models\TripVariant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $selectedTripId = null;
    public ?int $selectedVariantId = null;

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

    public function setTripFrontendAccess(string $access): void
    {
        if (! $this->selectedTrip || ! in_array($access, ['private', 'authenticated', 'public'], true)) {
            return;
        }

        $this->selectedTrip->setFrontendAccess($access);

        Flux::toast(variant: 'success', text: __('Traveler access updated.'));
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
    public function variants(): EloquentCollection
    {
        return $this->selectedTrip?->variants()->orderBy('sort_order')->get() ?? new EloquentCollection();
    }

    public function publicTripUrl(): ?string
    {
        return $this->selectedTrip ? route('trips.public', $this->selectedTrip) : null;
    }
}; ?>

<flux:card>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <flux:heading>{{ __('Publishing') }}</flux:heading>
            <flux:text>{{ __('Control who can view the planning frontend and which timelines are visible.') }}</flux:text>
        </div>
        @if ($this->selectedTrip)
            <flux:button size="sm" wire:click="toggleTripPublication">
                {{ $this->selectedTrip->visibility === 'public' || $this->selectedTrip->is_public ? __('Unpublish trip') : __('Publish trip') }}
            </flux:button>
        @endif
    </div>

    @if ($this->selectedTrip)
        <div class="mt-5 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="font-medium text-zinc-950 dark:text-white">{{ __('Frontend access') }}</div>
                    <div class="mt-1 text-zinc-500">{{ $this->selectedTrip->travelerAccessLabel() }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach (['private' => __('Private'), 'authenticated' => __('Planning login'), 'public' => __('Public launch')] as $access => $label)
                        <flux:button size="sm" :variant="$this->selectedTrip->travelerAccessMode() === $access ? 'primary' : 'outline'" wire:click="setTripFrontendAccess('{{ $access }}')">
                            {{ $label }}
                        </flux:button>
                    @endforeach
                </div>
            </div>

            @if ($this->publicTripUrl())
                <flux:link class="mt-4 block truncate" :href="$this->publicTripUrl()" target="_blank">
                    {{ $this->publicTripUrl() }}
                </flux:link>
            @endif
        </div>

        <div class="mt-5 space-y-3">
            @foreach ($this->variants as $variant)
                <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700 lg:flex-row lg:items-center lg:justify-between" wire:key="publish-variant-{{ $variant->id }}">
                    <div class="min-w-0">
                        <div class="truncate font-medium text-zinc-950 dark:text-white">{{ $variant->name }}</div>
                        <div class="mt-1 text-zinc-500">{{ $variant->travelerAccessLabel() }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['private' => __('Private'), 'authenticated' => __('Login'), 'public' => __('Public')] as $access => $label)
                            <flux:button size="sm" :variant="$variant->travelerAccessMode() === $access ? 'primary' : 'outline'" wire:click="setVariantFrontendAccess({{ $variant->id }}, '{{ $access }}')">
                                {{ $label }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="mt-5 rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700">
            {{ __('Select a trip to manage publishing.') }}
        </div>
    @endif
</flux:card>
