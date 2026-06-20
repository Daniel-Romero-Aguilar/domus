<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomusLevel extends Model
{
    protected $fillable = [
        'level_number',
        'name',
        'min_points',
        'max_points',
        'definition',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'min_points' => 'integer',
            'max_points' => 'integer',
            'level_number' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
