<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'parent_user_id',
        'accepted_by_user_id',
        'completed_by_user_id',
        'member_completion_requested_at',
        'name',
        'description',
        'reward_amount',
        'reward_points',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'member_completion_requested_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
