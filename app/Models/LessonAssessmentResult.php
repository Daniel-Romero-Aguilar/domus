<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonAssessmentResult extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'lesson_part_id',
        'assessment_uuid',
        'assessment_type',
        'score',
        'total_questions',
        'percentage',
        'submitted_answers',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'total_questions' => 'integer',
            'percentage' => 'float',
            'submitted_answers' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function lessonPart(): BelongsTo
    {
        return $this->belongsTo(LessonPart::class);
    }
}
