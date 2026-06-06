<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsBoxAccount extends Model
{
    protected $fillable = [
        'savings_box_id',
        'user_id',
        'principal_cents',
        'principal_pending_cents',
        'earned_interest_cents',
        'interest_remainder_microcents',
        'last_interest_accrued_on',
        'interest_accrued_until_at',
    ];

    protected $casts = [
        'principal_cents' => 'integer',
        'principal_pending_cents' => 'integer',
        'earned_interest_cents' => 'integer',
        'interest_remainder_microcents' => 'integer',
        'last_interest_accrued_on' => 'date',
        'interest_accrued_until_at' => 'datetime',
    ];

    public function savingsBox(): BelongsTo
    {
        return $this->belongsTo(SavingsBox::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SavingsBoxMovement::class);
    }
}
