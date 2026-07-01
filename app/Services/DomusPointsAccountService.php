<?php

namespace App\Services;

use App\Models\DomusRewardRedemption;
use App\Models\FamilyMember;
use App\Models\Task;

class DomusPointsAccountService
{
    public function __construct(
        private readonly DomusAchievementService $achievements,
        private readonly EducationExamRewardService $examRewards,
        private readonly DomusLevelService $levels,
    )
    {
    }

    public function snapshotForChild(int $childId): array
    {
        $familyMember = FamilyMember::query()
            ->where('user_id', $childId)
            ->first();

        if (! $familyMember) {
            return [
                'points' => [
                    'historical' => 0,
                    'earned' => 0,
                    'spent' => 0,
                    'available' => 0,
                ],
                'level' => null,
            ];
        }

        $earned = $this->earnedPointsForChild((int) $familyMember->parent_user_id, $childId);
        $spent = $this->spentPointsForChild($childId);
        $available = max($earned - $spent, 0);

        return [
            'points' => [
                'historical' => $earned,
                'earned' => $earned,
                'spent' => $spent,
                'available' => $available,
            ],
            'level' => $this->levels->resolveForPoints($earned),
        ];
    }

    public function earnedPointsForChild(int $parentId, int $childId): int
    {
        $taskPoints = (int) Task::query()
            ->where('parent_user_id', $parentId)
            ->whereIn('status', ['closed', 'ended', 'completed'])
            ->where(function ($query) use ($childId) {
                $query->where('completed_by_user_id', $childId)
                    ->orWhere(function ($legacyQuery) use ($childId) {
                        $legacyQuery->whereNull('completed_by_user_id')
                            ->where('accepted_by_user_id', $childId);
                    });
            })
            ->sum('reward_points');

        return $taskPoints
            + $this->achievements->totalPointsForUser($childId)
            + $this->examRewards->totalAwardedPointsForUser($childId);
    }

    public function spentPointsForChild(int $childId): int
    {
        return (int) DomusRewardRedemption::query()
            ->where('child_user_id', $childId)
            ->sum('points_spent');
    }
}
