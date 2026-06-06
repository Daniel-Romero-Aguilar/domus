<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsBoxMovement extends Model
{
    protected $fillable = [
        'savings_box_account_id',
        'savings_box_id',
        'user_id',
        'movement_type',
        'amount_cents',
        'principal_before_cents',
        'principal_after_cents',
        'earned_interest_before_cents',
        'earned_interest_after_cents',
        'occurred_at',
        'note',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'principal_before_cents' => 'integer',
        'principal_after_cents' => 'integer',
        'earned_interest_before_cents' => 'integer',
        'earned_interest_after_cents' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SavingsBoxAccount::class, 'savings_box_account_id');
    }

    public function savingsBox(): BelongsTo
    {
        return $this->belongsTo(SavingsBox::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
