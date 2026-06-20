<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DomusMission;
use App\Models\DomusReward;
use App\Models\DomusRewardRedemption;
use App\Models\FamilyMember;
use App\Models\Task;
use App\Services\DomusAchievementService;
use App\Services\EducationExamRewardService;
use App\Services\DomusLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DomusPointsController extends Controller
{
    public function __construct(
        private readonly DomusAchievementService $achievements,
        private readonly EducationExamRewardService $examRewards,
        private readonly DomusLevelService $levels,
    )
    {
    }

    public function parentIndex(Request $request): JsonResponse
    {
        $parent = $request->user();

        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can view Domus rewards.'], 403);
        }

        $members = FamilyMember::query()
            ->with('user:id,name,role')
            ->where('parent_user_id', $parent->id)
            ->get()
            ->map(function (FamilyMember $member) use ($parent) {
                $child = $member->user;

                if (! $child) {
                    return null;
                }

                $historical = $this->earnedPointsForChild($parent->id, $child->id);
                $spent = $this->spentPointsForChild($child->id);

                return [
                    'user_id' => $child->id,
                    'name' => $child->name,
                    'role' => $child->role,
                    'historical_points' => $historical,
                    'earned_points' => $historical,
                    'spent_points' => $spent,
                    'available_points' => max($historical - $spent, 0),
                    'level' => $this->levels->resolveForPoints($historical),
                ];
            })
            ->filter()
            ->values();

        $rewards = DomusReward::query()
            ->where('parent_user_id', $parent->id)
            ->withCount('redemptions')
            ->latest()
            ->get();

        $redemptions = DomusRewardRedemption::query()
            ->with(['reward:id,title', 'child:id,name'])
            ->whereHas('reward', function ($query) use ($parent) {
                $query->where('parent_user_id', $parent->id);
            })
            ->latest()
            ->get()
            ->map(function (DomusRewardRedemption $redemption) {
                return [
                    'id' => $redemption->id,
                    'reward_title' => $redemption->reward?->title,
                    'child_name' => $redemption->child?->name,
                    'points_spent' => $redemption->points_spent,
                    'status' => $redemption->status,
                    'created_at' => $redemption->created_at,
                ];
            })
            ->values();

        return response()->json([
            'members' => $members,
            'rewards' => $rewards,
            'redemptions' => $redemptions,
        ]);
    }

    public function parentStoreReward(Request $request): JsonResponse
    {
        $parent = $request->user();

        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can create Domus rewards.'], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'points_cost' => ['required', 'integer', 'min:1'],
        ]);

        $reward = DomusReward::create([
            'parent_user_id' => $parent->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'points_cost' => (int) $validated['points_cost'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Reward created.',
            'reward' => $reward,
        ], 201);
    }

    public function childIndex(Request $request): JsonResponse
    {
        $child = $request->user();

        if (! $child || ! in_array($child->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can view Domus rewards.'], 403);
        }

        $familyMember = FamilyMember::query()
            ->where('user_id', $child->id)
            ->first();

        if (! $familyMember) {
            return response()->json([
                'points' => [
                    'historical' => 0,
                    'earned' => 0,
                    'spent' => 0,
                    'available' => 0,
                ],
                'level' => null,
                'missions' => [],
                'rewards' => [],
                'redemptions' => [],
            ]);
        }

        $earned = $this->earnedPointsForChild($familyMember->parent_user_id, $child->id);
        $spent = $this->spentPointsForChild($child->id);
        $available = max($earned - $spent, 0);
        $missions = $this->achievements->missionsForUser($child->id);
        $level = $this->levels->resolveForPoints($earned);

        $rewards = DomusReward::query()
            ->where('parent_user_id', $familyMember->parent_user_id)
            ->where('is_active', true)
            ->latest()
            ->get()
            ->map(function (DomusReward $reward) use ($available) {
                return [
                    'id' => $reward->id,
                    'title' => $reward->title,
                    'description' => $reward->description,
                    'points_cost' => $reward->points_cost,
                    'can_redeem' => $available >= $reward->points_cost,
                ];
            })
            ->values();

        $redemptions = DomusRewardRedemption::query()
            ->with('reward:id,title')
            ->where('child_user_id', $child->id)
            ->latest()
            ->get()
            ->map(function (DomusRewardRedemption $redemption) {
                return [
                    'id' => $redemption->id,
                    'reward_title' => $redemption->reward?->title,
                    'points_spent' => $redemption->points_spent,
                    'status' => $redemption->status,
                    'created_at' => $redemption->created_at,
                ];
            })
            ->values();

        return response()->json([
            'points' => [
                'historical' => $earned,
                'earned' => $earned,
                'spent' => $spent,
                'available' => $available,
            ],
            'level' => $level,
            'missions' => $missions,
            'rewards' => $rewards,
            'redemptions' => $redemptions,
        ]);
    }

    public function levelsIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['parent', 'child', 'member'], true)) {
            return response()->json(['message' => 'Only authenticated family users can view Domus levels.'], 403);
        }

        return response()->json([
            'levels' => $this->levels->all(),
        ]);
    }

    public function childRedeemReward(Request $request, DomusReward $reward): JsonResponse
    {
        $child = $request->user();

        if (! $child || ! in_array($child->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can redeem Domus rewards.'], 403);
        }

        $familyMember = FamilyMember::query()
            ->where('user_id', $child->id)
            ->first();

        if (! $familyMember || $reward->parent_user_id !== $familyMember->parent_user_id || ! $reward->is_active) {
            return response()->json(['message' => 'Reward not available for this child.'], 403);
        }

        $earned = $this->earnedPointsForChild($familyMember->parent_user_id, $child->id);
        $spent = $this->spentPointsForChild($child->id);
        $available = max($earned - $spent, 0);

        if ($available < $reward->points_cost) {
            return response()->json(['message' => 'Not enough Domus points.'], 422);
        }

        $redemption = DB::transaction(function () use ($child, $reward) {
            return DomusRewardRedemption::create([
                'domus_reward_id' => $reward->id,
                'child_user_id' => $child->id,
                'points_spent' => $reward->points_cost,
                'status' => 'redeemed',
            ]);
        });

        return response()->json([
            'message' => 'Reward redeemed.',
            'redemption' => $redemption,
        ], 201);
    }

    public function parentMarkRedemptionPaid(Request $request, DomusRewardRedemption $redemption): JsonResponse
    {
        $parent = $request->user();

        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can update reward redemptions.'], 403);
        }

        $redemption->load(['reward:id,parent_user_id,title', 'child:id,name']);

        if (! $redemption->reward || (int) $redemption->reward->parent_user_id !== (int) $parent->id) {
            return response()->json(['message' => 'This redemption does not belong to your account.'], 403);
        }

        if ($redemption->status === 'paid') {
            return response()->json(['message' => 'This redemption was already marked as paid.'], 422);
        }

        $redemption->status = 'paid';
        $redemption->save();

        return response()->json([
            'message' => 'Reward marked as paid.',
            'redemption' => [
                'id' => $redemption->id,
                'reward_title' => $redemption->reward?->title,
                'child_name' => $redemption->child?->name,
                'points_spent' => $redemption->points_spent,
                'status' => $redemption->status,
                'created_at' => $redemption->created_at,
            ],
        ]);
    }

    private function earnedPointsForChild(int $parentId, int $childId): int
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

    private function spentPointsForChild(int $childId): int
    {
        return (int) DomusRewardRedemption::query()
            ->where('child_user_id', $childId)
            ->sum('points_spent');
    }

}
