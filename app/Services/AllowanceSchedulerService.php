<?php

namespace App\Services;

use App\Models\Allowance;
use Illuminate\Support\Facades\Log;

class AllowanceSchedulerService
{
    private const MAX_PAYMENTS_PER_TICK = 100;

    public function __construct(private readonly AllowanceService $allowanceService)
    {
    }

    public function runDueAllowances(): array
    {
        // The real cadence lives on each allowance record:
        // frequency = daily|weekly|monthly|ten_seconds, and next_run_at is advanced after each successful payout.
        // The scheduler only wakes up often enough to check which allowances are due.
        $now = now()->startOfSecond();
        $totalAllowances = Allowance::query()->count();
        $skippedAllowanceIds = [];

        $summary = [
            'checked' => 0,
            'executed' => 0,
            'failed' => 0,
            'already_executed' => 0,
            'remaining_due_allowances' => 0,
            'items' => [],
        ];

        if ($totalAllowances === 0) {
            Log::warning('Allowance scheduler found no allowances to process.');
        }

        for ($i = 0; $i < self::MAX_PAYMENTS_PER_TICK; $i++) {
            $query = Allowance::query()
                ->with(['parent:id,name', 'child:id,name,username'])
                ->where('status', '!=', 'paused')
                ->whereDate('start_at', '<=', $now->toDateString())
                ->where('next_run_at', '<=', $now)
                ->orderBy('next_run_at')
                ->orderBy('id');

            if ($skippedAllowanceIds !== []) {
                $query->whereNotIn('id', $skippedAllowanceIds);
            }

            $allowance = $query->first();

            if (! $allowance) {
                break;
            }

            $summary['checked']++;

            try {
                $result = $this->allowanceService->execute($allowance);

                $summary['executed'] += ! empty($result['executed']) && empty($result['already_executed']) ? 1 : 0;
                $summary['already_executed'] += ! empty($result['already_executed']) ? 1 : 0;
                $summary['failed'] += empty($result['executed']) ? 1 : 0;
                $summary['items'][] = [
                    'allowance_id' => $allowance->id,
                    'scheduled_for' => optional($allowance->next_run_at)->toDateTimeString(),
                    'executed' => (bool) ($result['executed'] ?? false),
                    'already_executed' => (bool) ($result['already_executed'] ?? false),
                    'message' => $result['message'] ?? null,
                ];
            } catch (\Throwable $exception) {
                $skippedAllowanceIds[] = $allowance->id;
                $summary['failed']++;
                $summary['items'][] = [
                    'allowance_id' => $allowance->id,
                    'scheduled_for' => optional($allowance->next_run_at)->toDateTimeString(),
                    'executed' => false,
                    'message' => $exception->getMessage(),
                ];

                Log::error('Allowance scheduler failed to process allowance.', [
                    'allowance_id' => $allowance->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $summary['remaining_due_allowances'] = Allowance::query()
            ->where('status', '!=', 'paused')
            ->whereDate('start_at', '<=', $now->toDateString())
            ->where('next_run_at', '<=', $now)
            ->count();

        if ($totalAllowances > 0 && $summary['checked'] === 0) {
            Log::info('Allowance scheduler found allowances, but none were due yet.', [
                'total_allowances' => $totalAllowances,
            ]);
        }

        return $summary;
    }
}
