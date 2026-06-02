<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = ['title', 'slug', 'description', 'is_active'];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
