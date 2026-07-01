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
        $badge = DomusMission::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $badge) {
            return [];
        }

        $existing = DomusMissionUser::query()
            ->where('user_id', $userId)
            ->where('domus_mission_id', $badge->id)
            ->first();

        if ($existing) {
            return [];
        }

        DomusMissionUser::create([
            'domus_mission_id' => $badge->id,
            'user_id' => $userId,
            'awarded_points' => (int) $badge->points_reward,
            'completed_at' => now(),
            'meta' => $meta,
        ]);

        return [[
            'id' => $badge->id,
            'slug' => $badge->slug,
            'title' => $badge->title,
            'description' => $badge->description,
            'text' => $badge->description ?: $badge->title,
            'image_url' => $this->badgeImageUrl($badge),
            'points_reward' => (int) $badge->points_reward,
        ]];
    }

    public function totalPointsForUser(int $userId): int
    {
        return (int) DomusMissionUser::query()
            ->where('user_id', $userId)
            ->sum('awarded_points');
    }

    public function badgesForUser(int $userId): Collection
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
            ->map(function (DomusMission $badge) use ($completed) {
                $completion = $completed->get($badge->id);

                return [
                    'id' => $badge->id,
                    'slug' => $badge->slug,
                    'title' => $badge->title,
                    'description' => $badge->description,
                    'image_url' => $this->badgeImageUrl($badge),
                    'points_reward' => (int) $badge->points_reward,
                    'is_completed' => (bool) $completion,
                    'completed_at' => $completion?->completed_at,
                ];
            })
            ->values();
    }

    private function badgeImageUrl(DomusMission $badge): ?string
    {
        if (! $badge->image_path) {
            return null;
        }

        return route('badges.image', ['badge' => $badge->slug]);
    }
}
