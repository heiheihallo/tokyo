<?php

namespace App\Models;

use Database\Factories\LoyaltyVoucherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyVoucher extends Model
{
    /** @use HasFactory<LoyaltyVoucherFactory> */
    use HasFactory;

    protected $fillable = [
        'loyalty_program_snapshot_id',
        'voucher_type',
        'status',
        'quantity',
        'earned_threshold_nok',
        'valid_from',
        'valid_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'earned_threshold_nok' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function loyaltyProgramSnapshot(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgramSnapshot::class);
    }
}
