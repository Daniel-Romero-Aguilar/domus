<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LessonPart extends Model
{
    protected $fillable = ['lesson_id', 'type', 'content', 'meta', 'position'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function examRewardRule(): HasOne
    {
        return $this->hasOne(LessonExamRewardRule::class);
    }
}
