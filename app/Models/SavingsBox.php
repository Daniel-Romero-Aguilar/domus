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
        'delivery_date',
        'annual_gain_percent',
        'allow_early_withdrawal',
        'audience',
        'status',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'annual_gain_percent' => 'decimal:2',
        'allow_early_withdrawal' => 'boolean',
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
