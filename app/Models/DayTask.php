<?php

namespace App\Models;

use Database\Factories\DayTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayTask extends Model
{
    /** @use HasFactory<DayTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'trip_variant_id',
        'day_node_id',
        'stable_key',
        'task_type',
        'title',
        'notes',
        'status',
        'priority',
        'due_on',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
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

    public function dayNode(): BelongsTo
    {
        return $this->belongsTo(DayNode::class);
    }
}
