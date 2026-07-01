<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DomusMission extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'image_path',
        'points_reward',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'domus_mission_user')
            ->withPivot(['awarded_points', 'completed_at', 'meta'])
            ->withTimestamps();
    }

    public function completions(): HasMany
    {
        return $this->hasMany(DomusMissionUser::class, 'domus_mission_id');
    }
}
