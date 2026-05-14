<?php

namespace App\Models;

use Database\Factories\DayNodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayNode extends Model
{
    /** @use HasFactory<DayNodeFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'trip_variant_id',
        'stable_key',
        'day_number',
        'starts_on',
        'ends_on',
        'location',
        'title',
        'summary',
        'node_types',
        'booking_priority',
        'booking_status',
        'weather_class',
        'kid_energy_level',
        'luggage_complexity',
        'transport_method',
        'duration_label',
        'cost_value_min_nok',
        'cost_value_max_nok',
        'cost_premium_min_nok',
        'cost_premium_max_nok',
        'cost_value_min_jpy',
        'cost_value_max_jpy',
        'cost_premium_min_jpy',
        'cost_premium_max_jpy',
        'reservation_url',
        'cancellation_window_at',
        'ics_exportable',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'node_types' => 'array',
            'cancellation_window_at' => 'datetime',
            'ics_exportable' => 'boolean',
            'details' => 'array',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(TripVariant::class, 'trip_variant_id');
    }

    public function accommodations(): BelongsToMany
    {
        return $this->belongsToMany(Accommodation::class, 'day_node_accommodation')
            ->withPivot(['role', 'confirmation_status', 'reservation_url', 'notes'])
            ->withTimestamps();
    }

    public function transportLegs(): BelongsToMany
    {
        return $this->belongsToMany(TransportLeg::class)
            ->withPivot(['sequence', 'booking_status', 'reservation_url', 'notes'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class)
            ->withPivot(['sequence', 'time_block', 'booking_status', 'reservation_url', 'notes'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    public function foodSpots(): BelongsToMany
    {
        return $this->belongsToMany(FoodSpot::class)
            ->withPivot(['sequence', 'meal_type', 'notes'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class)->withTimestamps();
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(DayItineraryItem::class)
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    public function publicItineraryItems(): HasMany
    {
        return $this->itineraryItems()->where('is_public', true);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DayTask::class)
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderBy('created_at');
    }

    public function valueCostRange(): string
    {
        return $this->formatCostRange($this->cost_value_min_nok, $this->cost_value_max_nok);
    }

    public function premiumCostRange(): string
    {
        return $this->formatCostRange($this->cost_premium_min_nok, $this->cost_premium_max_nok);
    }

    private function formatCostRange(?int $minimum, ?int $maximum): string
    {
        if ($minimum === null || $maximum === null) {
            return 'TBD';
        }

        return number_format($minimum, 0, '.', ' ').' - '.number_format($maximum, 0, '.', ' ').' NOK';
    }
}
