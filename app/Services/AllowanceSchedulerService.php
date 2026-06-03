<?php

namespace App\Services;

use App\Models\Allowance;
use Illuminate\Support\Facades\Log;

class AllowanceSchedulerService
{
    public function __construct(private readonly AllowanceService $allowanceService)
    {
    }

    public function runDueAllowances(bool $testMode = false): array
    {
        // The real cadence lives on each allowance record:
        // frequency = daily|weekly|monthly, and next_run_at is advanced after each successful payout.
        // The scheduler only wakes up often enough to check which allowances are due.
        // In test mode, we ignore next_run_at so we can verify transfers immediately.
        $today = now()->toDateString();
        $totalAllowances = Allowance::query()->count();
        $query = Allowance::query()
            ->with(['parent:id,name', 'child:id,name,username'])
            ->where('status', '!=', 'paused')
            ->whereDate('start_at', '<=', $today);

        if (! $testMode) {
            $query->whereDate('next_run_at', '<=', $today);
        }

        $dueAllowances = $query->orderBy('next_run_at')->get();

        $summary = [
            'checked' => $dueAllowances->count(),
            'executed' => 0,
            'failed' => 0,
            'items' => [],
        ];

        if ($totalAllowances === 0) {
            Log::warning('Allowance scheduler found no allowances to process.');
        } elseif ($summary['checked'] === 0) {
            Log::info('Allowance scheduler found allowances, but none were due yet.', [
                'total_allowances' => $totalAllowances,
            ]);
        }

        foreach ($dueAllowances as $allowance) {
            try {
                $result = $this->allowanceService->execute($allowance, $testMode, $testMode ? $today : null);

                $summary['executed'] += ! empty($result['executed']) ? 1 : 0;
                $summary['failed'] += empty($result['executed']) ? 1 : 0;
                $summary['items'][] = [
                    'allowance_id' => $allowance->id,
                    'executed' => (bool) ($result['executed'] ?? false),
                    'test_mode' => $testMode,
                    'message' => $result['message'] ?? null,
                ];
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $summary['items'][] = [
                    'allowance_id' => $allowance->id,
                    'executed' => false,
                    'test_mode' => $testMode,
                    'message' => $exception->getMessage(),
                ];

                Log::error('Allowance scheduler failed to process allowance.', [
                    'allowance_id' => $allowance->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $summary;
    }
}
