<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildGoal extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'target_amount_cents',
        'saved_amount_cents',
        'status',
        'completed_at',
        'canceled_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
