<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllowancePayment extends Model
{
    protected $fillable = [
        'allowance_id',
        'scheduled_for',
        'status',
        'amount_cents',
        'parent_balance_before',
        'parent_balance_after',
        'child_balance_before',
        'child_balance_after',
        'executed_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'amount_cents' => 'integer',
        'parent_balance_before' => 'integer',
        'parent_balance_after' => 'integer',
        'child_balance_before' => 'integer',
        'child_balance_after' => 'integer',
        'executed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function allowance(): BelongsTo
    {
        return $this->belongsTo(Allowance::class);
    }
}
