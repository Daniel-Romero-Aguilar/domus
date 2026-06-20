<?php

namespace App\Services;

use App\Models\DomusMission;
use App\Models\DomusMissionUser;
use Illuminate\Support\Collection;

class DomusAchievementService
{
    public function unlockFirstDeposit(int $userId, array $meta = []): array
    {
        return $this->unlockBySlug($userId, 'primer-abono', $meta);
    }

    public function unlockFirstLoanPayment(int $userId, array $meta = []): array
    {
        return $this->unlockBySlug($userId, 'primer-pago-prestamo', $meta);
    }

    public function unlockFirstTaskCompletion(int $userId, array $meta = []): array
    {
        return $this->unlockBySlug($userId, 'primera-tarea-completada', $meta);
    }

    public function unlockBySlug(int $userId, string $slug, array $meta = []): array
    {
        $mission = DomusMission::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $mission) {
            return [];
        }

        $existing = DomusMissionUser::query()
            ->where('user_id', $userId)
            ->where('domus_mission_id', $mission->id)
            ->first();

        if ($existing) {
            return [];
        }

        DomusMissionUser::create([
            'domus_mission_id' => $mission->id,
            'user_id' => $userId,
            'awarded_points' => (int) $mission->points_reward,
            'completed_at' => now(),
            'meta' => $meta,
        ]);

        return [[
            'id' => $mission->id,
            'slug' => $mission->slug,
            'title' => $mission->title,
            'text' => $mission->description ?: $mission->title,
            'points_reward' => (int) $mission->points_reward,
        ]];
    }

    public function totalPointsForUser(int $userId): int
    {
        return (int) DomusMissionUser::query()
            ->where('user_id', $userId)
            ->sum('awarded_points');
    }

    public function missionsForUser(int $userId): Collection
    {
        $completed = DomusMissionUser::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy('domus_mission_id');

        return DomusMission::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (DomusMission $mission) use ($completed) {
                $completion = $completed->get($mission->id);

                return [
                    'id' => $mission->id,
                    'slug' => $mission->slug,
                    'title' => $mission->title,
                    'description' => $mission->description,
                    'points_reward' => (int) $mission->points_reward,
                    'is_completed' => (bool) $completion,
                    'completed_at' => $completion?->completed_at,
                ];
            })
            ->values();
    }
}
