<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomusRewardRedemption extends Model
{
    protected $fillable = [
        'domus_reward_id',
        'child_user_id',
        'points_spent',
        'status',
    ];

    public function reward(): BelongsTo
    {
        return $this->belongsTo(DomusReward::class, 'domus_reward_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }
}
