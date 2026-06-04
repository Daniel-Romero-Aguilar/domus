<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Allowance extends Model
{
    protected $fillable = [
        'parent_user_id',
        'child_user_id',
        'amount_cents',
        'frequency',
        'start_at',
        'next_run_at',
        'first_payment_immediate',
        'status',
        'last_executed_at',
        'last_failed_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'start_at' => 'date',
        'next_run_at' => 'datetime',
        'first_payment_immediate' => 'boolean',
        'last_executed_at' => 'datetime',
        'last_failed_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AllowancePayment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(AllowancePayment::class)->latestOfMany();
    }
}
