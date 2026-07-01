<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashWithdrawal extends Model
{
    protected $fillable = [
        'parent_user_id',
        'child_user_id',
        'initiated_by_user_id',
        'amount_cents',
        'status',
        'parent_approved_at',
        'child_approved_at',
        'completed_at',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'parent_approved_at' => 'datetime',
            'child_approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
