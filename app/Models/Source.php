<?php

namespace App\Models;

use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    protected $fillable = [
        'source_key',
        'title',
        'source_type',
        'authority',
        'url',
        'notes',
    ];

    public function dayNodes(): BelongsToMany
    {
        return $this->belongsToMany(DayNode::class)->withTimestamps();
    }
}
