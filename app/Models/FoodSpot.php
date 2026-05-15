<?php

namespace App\Models;

use App\Models\Concerns\HasTripAssetMedia;
use Database\Factories\FoodSpotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;

class FoodSpot extends Model implements HasMedia
{
    /** @use HasFactory<FoodSpotFactory> */
    use HasFactory;

    use HasTripAssetMedia;

    protected $fillable = [
        'stable_key',
        'name',
        'area',
        'city',
        'country',
        'default_meal_type',
        'fallback_type',
        'latitude',
        'longitude',
        'notes',
        'price_min_nok',
        'price_max_nok',
        'price_min_jpy',
        'price_max_jpy',
        'price_basis',
        'price_notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_min_nok' => 'integer',
            'price_max_nok' => 'integer',
            'price_min_jpy' => 'integer',
            'price_max_jpy' => 'integer',
        ];
    }

    public function dayNodes(): BelongsToMany
    {
        return $this->belongsToMany(DayNode::class)
            ->withPivot(['sequence', 'meal_type', 'notes'])
            ->withTimestamps();
    }
}
