<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomusMissionUser extends Model
{
    protected $table = 'domus_mission_user';

    protected $fillable = [
        'domus_mission_id',
        'user_id',
        'awarded_points',
        'completed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(DomusMission::class, 'domus_mission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
