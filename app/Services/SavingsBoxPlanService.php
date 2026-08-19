<?php

namespace App\Services;

use App\Models\SavingsBox;
use App\Models\SavingsBoxAccount;
use Illuminate\Support\Carbon;

class SavingsBoxPlanService
{
    private const SECONDS_PER_DAY = 86400;

    private const PLAN_VERSION = 'fixed_days_ceil_v1';

    /** @return array<int, int> */
    public function availableMonths(): array
    {
        return collect(config('savings_box.months_available', [1, 2, 3]))
            ->map(fn ($months): int => (int) $months)
            ->filter(fn (int $months): bool => $months > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function configuredCycleDays(): int
    {
        return max(1, (int) config('savings_box.days_per_cycle', 14));
    }

    /**
     * Values for a new-box form. These are only a preview; store() persists
     * the authoritative snapshot using the exact creation timestamp.
     *
     * @return array<string, mixed>
     */
    public function creationOptions(?Carbon $startsAt = null): array
    {
        $startsAt = ($startsAt ?? Carbon::now())->copy()->startOfSecond();
        $cycleDays = $this->configuredCycleDays();
        $availableMonths = $this->availableMonths();

        $durationOptions = collect($availableMonths)
            ->map(function (int $months) use ($startsAt, $cycleDays): array {
                $snapshot = $this->snapshot($startsAt, $months, 1, $cycleDays);

                return [
                    'duration_months' => $months,
                    'label' => $months === 1 ? '1 mes' : $months.' meses',
                    'duration_days_preview' => $snapshot['duration_days'],
                    'cycle_count_preview' => $snapshot['cycle_count'],
                    'max_total_multiplier_preview' => $snapshot['cycle_count'],
                    'cutoff_at_preview' => $snapshot['cutoff_at']->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return [
            'plan_version' => self::PLAN_VERSION,
            'is_preview' => true,
            'preview_starts_at' => $startsAt->toIso8601String(),
            'cycle_days' => $cycleDays,
            'cycle_label' => $cycleDays === 1 ? 'Cada día' : 'Cada '.$cycleDays.' días',
            'months_available' => $availableMonths,
            'duration_options' => $durationOptions,
        ];
    }

    /**
     * @return array{
     *     duration_months: int,
     *     duration_days: int,
     *     cycle_days: int,
     *     cycle_count: int,
     *     plan_version: string,
     *     max_per_cycle_cents: int,
     *     max_amount_cents: int,
     *     starts_at: Carbon,
     *     cutoff_at: Carbon
     * }
     */
    public function snapshot(
        Carbon $startsAt,
        int $durationMonths,
        int $maxPerCycleCents,
        ?int $cycleDays = null
    ): array {
        $cycleDays ??= $this->configuredCycleDays();

        if ($durationMonths < 1 || $cycleDays < 1 || $maxPerCycleCents < 1) {
            throw new \InvalidArgumentException('Invalid savings box plan values.');
        }

        $cutoffAt = $startsAt->copy()->addMonthsNoOverflow($durationMonths);
        $durationSeconds = max(1, (int) ceil($startsAt->diffInSeconds($cutoffAt)));
        $durationDays = max(1, (int) ceil($durationSeconds / self::SECONDS_PER_DAY));
        $cycleCount = max(1, (int) ceil($durationDays / $cycleDays));

        if ($maxPerCycleCents > intdiv(PHP_INT_MAX, $cycleCount)) {
            throw new \OverflowException('Savings box maximum is too large.');
        }

        return [
            'duration_months' => $durationMonths,
            'duration_days' => $durationDays,
            'cycle_days' => $cycleDays,
            'cycle_count' => $cycleCount,
            'plan_version' => self::PLAN_VERSION,
            'max_per_cycle_cents' => $maxPerCycleCents,
            'max_amount_cents' => $maxPerCycleCents * $cycleCount,
            'starts_at' => $startsAt->copy(),
            'cutoff_at' => $cutoffAt,
        ];
    }

    public function currentCycleNumber(SavingsBox $box, ?Carbon $moment = null): ?int
    {
        $moment ??= Carbon::now();

        if (! $box->starts_at || ! $box->cutoff_at || ! $box->cycle_days || ! $box->cycle_count) {
            return null;
        }

        $startsAt = Carbon::parse($box->starts_at);
        $cutoffAt = Carbon::parse($box->cutoff_at);

        if ($moment->lt($startsAt) || $moment->gte($cutoffAt)) {
            return null;
        }

        $elapsedSeconds = max(0, (int) floor($startsAt->diffInSeconds($moment)));
        $cycleSeconds = max(1, (int) $box->cycle_days) * self::SECONDS_PER_DAY;
        $cycleNumber = intdiv($elapsedSeconds, $cycleSeconds) + 1;

        return min(max(1, $cycleNumber), (int) $box->cycle_count);
    }

    public function depositedInCycleCents(SavingsBoxAccount $account, int $cycleNumber): int
    {
        return (int) $account->movements()
            ->where('movement_type', 'deposit')
            ->where('cycle_number', $cycleNumber)
            ->sum('amount_cents');
    }

    /** @return array<string, int|null> */
    public function accountUsage(SavingsBox $box, SavingsBoxAccount $account, ?Carbon $moment = null): array
    {
        $cycleNumber = $this->currentCycleNumber($box, $moment);
        $depositedInCycleCents = $cycleNumber === null
            ? 0
            : $this->depositedInCycleCents($account, $cycleNumber);
        $principalCents = (int) $account->principal_cents + (int) $account->principal_pending_cents;

        return [
            'current_cycle_number' => $cycleNumber,
            'deposited_in_current_cycle_cents' => $depositedInCycleCents,
            'remaining_in_current_cycle_cents' => $box->max_per_cycle_cents === null
                ? null
                : max(0, (int) $box->max_per_cycle_cents - $depositedInCycleCents),
            'remaining_total_capacity_cents' => $box->max_amount_cents === null
                ? null
                : max(0, (int) $box->max_amount_cents - $principalCents),
        ];
    }
}
