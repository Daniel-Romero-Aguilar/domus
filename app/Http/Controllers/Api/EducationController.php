<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\LessonAssessmentResult;
use App\Models\LessonCompletion;
use App\Models\LessonExamRewardGrant;
use App\Models\LessonExamRewardRule;
use App\Models\LessonPart;
use App\Services\EducationExamRewardService;
use App\Services\DomusPointsAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EducationController extends Controller
{
    public function __construct(
        private readonly EducationExamRewardService $examRewards,
        private readonly DomusPointsAccountService $pointsAccount,
    )
    {
    }

    public function categories(): JsonResponse
    {
        $categories = CourseCategory::query()
            ->where('is_active', true)
            ->withCount(['courses' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'description']);

        $maxPointsByCategory = DB::table('lesson_exam_reward_rules')
            ->join('lesson_parts', 'lesson_parts.id', '=', 'lesson_exam_reward_rules.lesson_part_id')
            ->join('lessons', 'lessons.id', '=', 'lesson_parts.lesson_id')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')
            ->whereIn('courses.course_category_id', $categories->pluck('id'))
            ->select('courses.course_category_id', DB::raw('SUM(lesson_exam_reward_rules.excellent_points) as max_domus_points'))
            ->groupBy('courses.course_category_id')
            ->pluck('max_domus_points', 'courses.course_category_id');

        return response()->json([
            'hero' => [
                'title' => 'Aprende finanzas',
                'text' => 'Te servira toda tu vida porque entender el dinero te ayuda a decidir mejor, ahorrar con calma y crecer con confianza.',
            ],
            'categories' => $categories->map(function (CourseCategory $category) use ($maxPointsByCategory) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'courses_count' => $category->courses_count,
                    'max_domus_points' => (int) ($maxPointsByCategory[$category->id] ?? 0),
                ];
            })->values(),
        ]);
    }

    public function categoryCourses(Request $request, CourseCategory $category): JsonResponse
    {
        abort_unless($category->is_active, 404);

        $courses = $category->courses()
            ->where('is_active', true)
            ->withCount('lessons')
            ->with(['lessons.parts' => function ($query) {
                $query->where('type', 'exam')
                    ->orderBy('position');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'course_category_id', 'title', 'slug', 'description', 'image_url', 'sort_order']);

        $examPartIds = $courses
            ->flatMap(fn ($course) => $course->lessons->flatMap(fn ($lesson) => $lesson->parts->pluck('id')))
            ->filter()
            ->values();

        $latestResults = LessonAssessmentResult::query()
            ->where('user_id', $request->user()->id)
            ->where('assessment_type', 'exam')
            ->whereIn('lesson_part_id', $examPartIds)
            ->latest('submitted_at')
            ->get()
            ->unique('lesson_part_id')
            ->keyBy('lesson_part_id');

        $rewardRules = LessonExamRewardRule::query()
            ->whereIn('lesson_part_id', $examPartIds)
            ->get()
            ->keyBy('lesson_part_id');

        $rewardGrants = LessonExamRewardGrant::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('lesson_part_id', $examPartIds)
            ->get()
            ->groupBy('lesson_part_id');

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
            ],
            'courses' => $courses->map(function (Course $course) use ($latestResults, $rewardRules, $rewardGrants) {
                $examPart = $course->lessons
                    ->flatMap(fn ($lesson) => $lesson->parts)
                    ->sortBy('id')
                    ->first();

                return [
                    'id' => $course->id,
                    'course_category_id' => $course->course_category_id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'description' => $course->description,
                    'image_url' => $course->image_url,
                    'sort_order' => $course->sort_order,
                    'lessons_count' => $course->lessons_count,
                    'exam_summary' => $examPart
                        ? $this->examRewards->presentCourseSummary(
                            $rewardRules->get($examPart->id),
                            $rewardGrants->get($examPart->id, collect()),
                            $latestResults->get($examPart->id),
                        )
                        : null,
                ];
            })->values(),
        ]);
    }

    public function showCourse(Request $request, Course $course): JsonResponse
    {
        $course->load('category:id,name,description,is_active');

        abort_unless($course->is_active && $course->category?->is_active, 404);

        $lessons = $course->lessons()
            ->orderBy('position')
            ->with(['parts' => function ($query) {
                $query->orderBy('position');
            }])
            ->get(['id', 'course_id', 'name', 'title', 'slug', 'description', 'position']);

        $partIds = $lessons->flatMap(fn ($lesson) => $lesson->parts->pluck('id'))
            ->filter()
            ->values();

        $latestExamResults = LessonAssessmentResult::query()
            ->where('user_id', $request->user()->id)
            ->where('assessment_type', 'exam')
            ->whereIn('lesson_part_id', $partIds)
            ->latest('submitted_at')
            ->get()
            ->unique('lesson_part_id')
            ->keyBy('lesson_part_id');

        $rewardRules = LessonExamRewardRule::query()
            ->whereIn('lesson_part_id', $partIds)
            ->get()
            ->keyBy('lesson_part_id');

        $rewardGrants = LessonExamRewardGrant::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('lesson_part_id', $partIds)
            ->get()
            ->groupBy('lesson_part_id');

        $completedLessonIds = LessonCompletion::query()
            ->where('user_id', $request->user()->id)
            ->where('is_completed', true)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->pluck('lesson_id');

        $completedLookup = $completedLessonIds->flip();
        $lastCompletedPosition = $lessons
            ->filter(fn ($lesson) => $completedLookup->has($lesson->id))
            ->max('position');

        $selectedLessonId = (int) $request->integer('lesson_id');
        $selectedLesson = $lessons->firstWhere('id', $selectedLessonId);

        if (! $selectedLesson) {
            if ($lastCompletedPosition === null) {
                $selectedLesson = $lessons->first();
            } else {
                $selectedLesson = $lessons->first(fn ($lesson) => $lesson->position > $lastCompletedPosition)
                    ?? $lessons->last();
            }
        }

        $lessonItems = $lessons->map(function ($lesson) use ($completedLookup) {
            return [
                'id' => $lesson->id,
                'name' => $lesson->name,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'position' => $lesson->position,
                'is_completed' => $completedLookup->has($lesson->id),
            ];
        })->values();

        $currentLesson = null;

        if ($selectedLesson) {
            $currentLesson = [
                'id' => $selectedLesson->id,
                'name' => $selectedLesson->name,
                'title' => $selectedLesson->title,
                'description' => $selectedLesson->description,
                'position' => $selectedLesson->position,
                'is_completed' => $completedLookup->has($selectedLesson->id),
                'parts' => $selectedLesson->parts->map(function ($part) use ($latestExamResults, $rewardRules, $rewardGrants) {
                    return [
                        'id' => $part->id,
                        'type' => $part->type,
                        'content' => $part->content,
                        'meta' => $this->partMetaForResponse($part),
                        'reward' => $part->type === 'exam'
                            ? $this->examRewards->presentRule(
                                $rewardRules->get($part->id),
                                $rewardGrants->get($part->id, collect()),
                            )
                            : null,
                        'last_result' => $part->type === 'exam'
                            ? $this->presentAssessmentResult($latestExamResults->get($part->id))
                            : null,
                        'position' => $part->position,
                    ];
                })->values(),
            ];
        }

        return response()->json([
            'category' => [
                'id' => $course->category->id,
                'name' => $course->category->name,
                'description' => $course->category->description,
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'image_url' => $course->image_url,
            ],
            'lessons' => $lessonItems,
            'current_lesson' => $currentLesson,
            'progress' => [
                'completed_lessons' => $completedLessonIds->count(),
                'total_lessons' => $lessons->count(),
            ],
        ]);
    }

    public function completeLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $lesson->load('course.category');

        abort_unless($lesson->course?->is_active && $lesson->course?->category?->is_active, 404);

        $completion = LessonCompletion::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'is_completed' => true,
                'completed_at' => now(),
            ]
        );

        $courseLessonIds = $lesson->course->lessons()->pluck('id');
        $completedLessons = LessonCompletion::query()
            ->where('user_id', $request->user()->id)
            ->where('is_completed', true)
            ->whereIn('lesson_id', $courseLessonIds)
            ->count();

        return response()->json([
            'message' => 'Leccion marcada como completada.',
            'lesson_completion' => [
                'lesson_id' => (int) $lesson->id,
                'is_completed' => true,
                'completed_at' => $completion->completed_at?->toISOString(),
            ],
            'progress' => [
                'completed_lessons' => $completedLessons,
                'total_lessons' => $courseLessonIds->count(),
            ],
        ]);
    }

    public function submitAssessment(Request $request, LessonPart $lessonPart): JsonResponse
    {
        $lessonPart->load('lesson.course.category');

        abort_unless($lessonPart->lesson?->course?->is_active && $lessonPart->lesson?->course?->category?->is_active, 404);

        if (! in_array($lessonPart->type, ['quiz', 'exam'], true)) {
            return response()->json(['message' => 'Esta seccion no admite respuestas.'], 422);
        }

        if ($lessonPart->type === 'exam') {
            $courseLessonIds = $lessonPart->lesson->course->lessons()->pluck('id');
            $completedLessons = LessonCompletion::query()
                ->where('user_id', $request->user()->id)
                ->where('is_completed', true)
                ->whereIn('lesson_id', $courseLessonIds)
                ->count();
            $totalLessons = max(1, $courseLessonIds->count());
            $progressPercentage = round(($completedLessons / $totalLessons) * 100, 2);

            if ($progressPercentage < 50) {
                return response()->json([
                    'message' => 'Necesita un minimo de 50% de avance para presentar este examen.',
                    'required_progress_percentage' => 50,
                    'current_progress_percentage' => $progressPercentage,
                ], 400);
            }
        }

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_uuid' => ['required', 'string', 'max:100'],
            'answers.*.answer' => ['required', 'in:a,b,c,d'],
        ]);

        $meta = $lessonPart->meta ?? [];
        $questions = collect($meta['questions'] ?? []);

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'Esta evaluacion no tiene preguntas configuradas.'], 422);
        }

        $answersByUuid = collect($validated['answers'])
            ->keyBy('question_uuid');

        $score = 0;

        foreach ($questions as $question) {
            $questionUuid = (string) ($question['uuid'] ?? '');
            $correctOption = strtolower((string) ($question['correct_option'] ?? ''));
            $answerRow = $answersByUuid->get($questionUuid);
            $selectedOption = strtolower((string) ($answerRow['answer'] ?? ''));

            if ($questionUuid !== '' && $correctOption !== '' && $selectedOption === $correctOption) {
                $score++;
            }
        }

        $totalQuestions = $questions->count();
        $percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 2) : 0;
        $result = null;
        $achievements = [];
        $rewardGrants = collect();
        $rewardRule = LessonExamRewardRule::query()
            ->where('lesson_part_id', $lessonPart->id)
            ->first();

        if ($lessonPart->type === 'exam') {
            $result = LessonAssessmentResult::create([
                'user_id' => $request->user()->id,
                'course_id' => $lessonPart->lesson->course->id,
                'lesson_id' => $lessonPart->lesson->id,
                'lesson_part_id' => $lessonPart->id,
                'assessment_uuid' => (string) ($meta['uuid'] ?? $lessonPart->id),
                'assessment_type' => 'exam',
                'score' => $score,
                'total_questions' => $totalQuestions,
                'percentage' => $percentage,
                'submitted_answers' => $validated['answers'],
                'submitted_at' => now(),
            ]);

            $rewardOutcome = $this->examRewards->awardFirstPassingAttempt(
                $request->user()->id,
                $lessonPart,
                $result
            );

            $rewardGrants = $rewardOutcome['grants'];
            $achievements = $rewardOutcome['achievements'];
        }

        return response()->json([
            'message' => $lessonPart->type === 'exam' ? 'Examen calificado.' : 'Quiz revisado.',
            'assessment_uuid' => (string) ($meta['uuid'] ?? $lessonPart->id),
            'assessment_type' => $lessonPart->type,
            'score' => $score,
            'total_questions' => $totalQuestions,
            'percentage' => $percentage,
            'saved' => $lessonPart->type === 'exam',
            'last_result' => $this->presentAssessmentResult($result),
            'reward' => $lessonPart->type === 'exam'
                ? $this->examRewards->presentRule(
                    $rewardRule,
                    $rewardGrants->isNotEmpty() ? $rewardGrants : LessonExamRewardGrant::query()
                        ->where('user_id', $request->user()->id)
                        ->where('lesson_part_id', $lessonPart->id)
                        ->get(),
                )
                : null,
            'achievements' => $achievements,
            'domus_points' => $lessonPart->type === 'exam'
                ? $this->pointsAccount->snapshotForChild((int) $request->user()->id)
                : null,
        ]);
    }

    private function partMetaForResponse(LessonPart $part): ?array
    {
        if (! in_array($part->type, ['quiz', 'exam'], true)) {
            return $part->meta;
        }

        $meta = $part->meta ?? [];
        $questions = collect($meta['questions'] ?? [])
            ->map(function (array $question) {
                return [
                    'uuid' => $question['uuid'] ?? null,
                    'question' => $question['question'] ?? '',
                    'option_a' => $question['option_a'] ?? '',
                    'option_b' => $question['option_b'] ?? '',
                    'option_c' => $question['option_c'] ?? '',
                    'option_d' => $question['option_d'] ?? '',
                ];
            })
            ->values();

        return [
            'uuid' => $meta['uuid'] ?? null,
            'questions' => $questions,
        ];
    }

    private function presentAssessmentResult(?LessonAssessmentResult $result): ?array
    {
        if (! $result) {
            return null;
        }

        return [
            'score' => (int) $result->score,
            'total_questions' => (int) $result->total_questions,
            'percentage' => (float) $result->percentage,
            'submitted_at' => $result->submitted_at?->toISOString(),
        ];
    }
}
