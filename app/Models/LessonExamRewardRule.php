<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonExamRewardRule extends Model
{
    protected $fillable = [
        'lesson_part_id',
        'approved_points',
        'excellent_points',
    ];

    protected function casts(): array
    {
        return [
            'approved_points' => 'integer',
            'excellent_points' => 'integer',
        ];
    }

    public function lessonPart(): BelongsTo
    {
        return $this->belongsTo(LessonPart::class);
    }
}
