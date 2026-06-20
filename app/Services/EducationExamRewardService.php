<?php

namespace App\Services;

use App\Models\LessonAssessmentResult;
use App\Models\LessonExamRewardGrant;
use App\Models\LessonExamRewardRule;
use App\Models\LessonPart;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

class EducationExamRewardService
{
    public function thresholds(): array
    {
        $approved = max(0, (int) config('education.exams.approved_percentage', 50));
        $excellent = max($approved, (int) config('education.exams.excellent_percentage', 80));

        return [
            'approved_percentage' => $approved,
            'excellent_percentage' => $excellent,
        ];
    }

    public function determineTier(float $percentage): ?string
    {
        $thresholds = $this->thresholds();

        if ($percentage < $thresholds['approved_percentage']) {
            return null;
        }

        if ($percentage >= $thresholds['excellent_percentage']) {
            return 'excellent';
        }

        return 'approved';
    }

    public function qualifyingTiers(float $percentage): array
    {
        $highestTier = $this->determineTier($percentage);

        if (! $highestTier) {
            return [];
        }

        if ($highestTier === 'excellent') {
            return ['approved', 'excellent'];
        }

        return ['approved'];
    }

    public function presentRule(?LessonExamRewardRule $rule, Collection|array|null $grants = null): ?array
    {
        if (! $rule) {
            return null;
        }

        $thresholds = $this->thresholds();
        $grantCollection = $grants instanceof Collection
            ? $grants
            : collect(is_array($grants) ? $grants : []);
        $grantsByTier = $grantCollection
            ->filter()
            ->keyBy('reward_tier');
        $approvedGrant = $grantsByTier->get('approved');
        $excellentGrant = $grantsByTier->get('excellent');

        return [
            'approved_percentage' => $thresholds['approved_percentage'],
            'excellent_percentage' => $thresholds['excellent_percentage'],
            'approved_points' => (int) $rule->approved_points,
            'excellent_points' => (int) $rule->excellent_points,
            'has_approved_reward' => (bool) $approvedGrant,
            'has_excellent_reward' => (bool) $excellentGrant,
            'awarded_points_total' => $grantCollection->sum('awarded_points'),
            'approved_awarded_points' => $approvedGrant ? (int) $approvedGrant->awarded_points : 0,
            'excellent_awarded_points' => $excellentGrant ? (int) $excellentGrant->awarded_points : 0,
            'approved_awarded_at' => $approvedGrant?->awarded_at?->toISOString(),
            'excellent_awarded_at' => $excellentGrant?->awarded_at?->toISOString(),
            'message' => $this->rewardMessage($rule, $grantsByTier),
        ];
    }

    public function presentCourseSummary(
        ?LessonExamRewardRule $rule,
        Collection|array|null $grants,
        ?LessonAssessmentResult $lastResult
    ): ?array {
        if (! $rule && ! $lastResult) {
            return null;
        }

        $summary = $this->presentRule($rule, $grants) ?? [];

        if ($lastResult) {
            $summary['last_result'] = [
                'score' => (int) $lastResult->score,
                'total_questions' => (int) $lastResult->total_questions,
                'percentage' => (float) $lastResult->percentage,
                'submitted_at' => $lastResult->submitted_at?->toISOString(),
            ];
        } else {
            $summary['last_result'] = null;
        }

        return $summary;
    }

    public function awardFirstPassingAttempt(
        int $userId,
        LessonPart $lessonPart,
        LessonAssessmentResult $result
    ): array {
        $rule = LessonExamRewardRule::query()
            ->where('lesson_part_id', $lessonPart->id)
            ->first();

        if (! $rule) {
            return [
                'grant' => null,
                'achievements' => [],
            ];
        }

        $tiers = $this->qualifyingTiers((float) $result->percentage);

        if ($tiers === []) {
            return [
                'grants' => collect(),
                'achievements' => [],
            ];
        }

        $existingByTier = LessonExamRewardGrant::query()
            ->where('user_id', $userId)
            ->where('lesson_part_id', $lessonPart->id)
            ->get()
            ->keyBy('reward_tier');

        $newAchievements = [];

        foreach ($tiers as $tier) {
            if ($existingByTier->has($tier)) {
                continue;
            }

            $points = $tier === 'excellent'
                ? (int) $rule->excellent_points
                : (int) $rule->approved_points;

            if ($points < 1) {
                continue;
            }

            try {
                $grant = LessonExamRewardGrant::query()->create([
                    'user_id' => $userId,
                    'course_id' => $lessonPart->lesson->course_id,
                    'lesson_id' => $lessonPart->lesson_id,
                    'lesson_part_id' => $lessonPart->id,
                    'lesson_assessment_result_id' => $result->id,
                    'reward_tier' => $tier,
                    'awarded_points' => $points,
                    'percentage_achieved' => (float) $result->percentage,
                    'awarded_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $grant = LessonExamRewardGrant::query()
                    ->where('user_id', $userId)
                    ->where('lesson_part_id', $lessonPart->id)
                    ->where('reward_tier', $tier)
                    ->first();
            }

            if ($grant) {
                $existingByTier->put($tier, $grant);
            }

            $examTitle = $lessonPart->lesson->title ?: $lessonPart->lesson->name ?: 'tu examen';
            $tierText = $tier === 'excellent'
                ? 'lograste un resultado excelente en '.$examTitle
                : 'aprobaste '.$examTitle.' por primera vez';

            $newAchievements[] = [
                'title' => 'Premio de examen',
                'text' => $tierText.' y ganaste '.$points.' puntos Domus',
                'points_reward' => $points,
            ];
        }

        return [
            'grants' => $existingByTier,
            'achievements' => $newAchievements,
        ];
    }

    public function totalAwardedPointsForUser(int $userId): int
    {
        return (int) LessonExamRewardGrant::query()
            ->where('user_id', $userId)
            ->sum('awarded_points');
    }

    private function rewardMessage(LessonExamRewardRule $rule, Collection $grantsByTier): string
    {
        $approvedPoints = (int) $rule->approved_points;
        $excellentPoints = (int) $rule->excellent_points;
        $hasApproved = $grantsByTier->has('approved');
        $hasExcellent = $grantsByTier->has('excellent');

        if (! $hasApproved && ! $hasExcellent) {
            return 'Si apruebas este examen por primera vez, ganas '.$approvedPoints.' puntos Domus. Y si despues consigues un resultado excelente, ganaras '.$excellentPoints.' puntos Domus mas.';
        }

        if ($hasApproved && ! $hasExcellent) {
            return 'Muy bien. Ya ganaste '.$approvedPoints.' puntos Domus por aprobar este examen. Si ahora consigues un resultado excelente, ganaras '.$excellentPoints.' puntos Domus mas.';
        }

        return 'Felicidades, hiciste este examen de forma excelente. Ya reuniste todos sus puntos Domus y puedes repetirlo cuando quieras para seguir practicando.';
    }
}
