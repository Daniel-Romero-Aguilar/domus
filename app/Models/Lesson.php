<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = ['course_id', 'title', 'position'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(LessonPart::class);
    }
}
