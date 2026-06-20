<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonExamRewardGrant extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'lesson_part_id',
        'lesson_assessment_result_id',
        'reward_tier',
        'awarded_points',
        'percentage_achieved',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'awarded_points' => 'integer',
            'percentage_achieved' => 'float',
            'awarded_at' => 'datetime',
        ];
    }

    public function lessonPart(): BelongsTo
    {
        return $this->belongsTo(LessonPart::class);
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(LessonAssessmentResult::class, 'lesson_assessment_result_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
