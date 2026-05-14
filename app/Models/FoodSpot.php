<?php

namespace App\Models;

use Database\Factories\FoodSpotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FoodSpot extends Model
{
    /** @use HasFactory<FoodSpotFactory> */
    use HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function dayNodes(): BelongsToMany
    {
        return $this->belongsToMany(DayNode::class)
            ->withPivot(['sequence', 'meal_type', 'notes'])
            ->withTimestamps();
    }
}
