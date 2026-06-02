<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    protected $fillable = [
        'parent_user_id',
        'child_user_id',
        'amount',
        'reason',
        'due_date',
        'installments_count',
        'installment_frequency',
        'has_interest',
        'interest_mode',
        'annual_interest_rate',
        'fixed_interest_amount',
        'status',
        'rejection_reason',
        'responded_at',
        'requested_by_user_id',
        'total_amount',
        'installment_amount',
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
