<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyTransfer extends Model
{
    protected $fillable = [
        'parent_user_id',
        'child_user_id',
        'amount_cents',
        'idempotency_key',
        'status',
        'parent_balance_before',
        'parent_balance_after',
        'child_balance_before',
        'child_balance_after',
        'failure_reason',
        'executed_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'parent_balance_before' => 'integer',
        'parent_balance_after' => 'integer',
        'child_balance_before' => 'integer',
        'child_balance_after' => 'integer',
        'executed_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }
}
