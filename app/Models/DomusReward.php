<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DomusReward extends Model
{
    protected $fillable = [
        'parent_user_id',
        'title',
        'description',
        'points_cost',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DomusRewardRedemption::class);
    }
}
