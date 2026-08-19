<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsBox extends Model
{
    protected $fillable = [
        'parent_user_id',
        'name',
        'duration_months',
        'duration_days',
        'cycle_days',
        'cycle_count',
        'plan_version',
        'max_per_cycle_cents',
        'starts_at',
        'cutoff_at',
        'delivery_date',
        'annual_gain_percent',
        'benefit_percent',
        'benefit_mode',
        'max_amount_cents',
        'allow_early_withdrawal',
        'audience',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'duration_days' => 'integer',
        'cycle_days' => 'integer',
        'cycle_count' => 'integer',
        'max_per_cycle_cents' => 'integer',
        'starts_at' => 'datetime',
        'cutoff_at' => 'datetime',
        'delivery_date' => 'date',
        'annual_gain_percent' => 'decimal:2',
        'benefit_percent' => 'decimal:2',
        'max_amount_cents' => 'integer',
        'allow_early_withdrawal' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'savings_box_members')->withTimestamps();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SavingsBoxAccount::class);
    }
}
