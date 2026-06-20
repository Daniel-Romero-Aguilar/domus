<?php

namespace App\Services;

use App\Models\DomusLevel;
use Illuminate\Support\Collection;

class DomusLevelService
{
    public function all(): Collection
    {
        return DomusLevel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DomusLevel $level) => $this->present($level))
            ->values();
    }

    public function resolveForPoints(int $historicalPoints): ?array
    {
        $level = DomusLevel::query()
            ->where('is_active', true)
            ->where('min_points', '<=', $historicalPoints)
            ->where(function ($query) use ($historicalPoints) {
                $query->whereNull('max_points')
                    ->orWhere('max_points', '>=', $historicalPoints);
            })
            ->orderByDesc('level_number')
            ->first();

        return $level ? $this->present($level) : null;
    }

    private function present(DomusLevel $level): array
    {
        return [
            'id' => $level->id,
            'level_number' => (int) $level->level_number,
            'name' => $level->name,
            'min_points' => (int) $level->min_points,
            'max_points' => $level->max_points === null ? 1200 : (int) $level->max_points,
            'definition' => $level->definition,
        ];
    }
}
