<?php

namespace App\Services;

use App\Models\Allowance;
use Illuminate\Support\Facades\Log;

class AllowanceSchedulerService
{
    public function __construct(private readonly AllowanceService $allowanceService)
    {
    }

    public function runDueAllowances(): array
    {
        // The real cadence lives on each allowance record:
        // frequency = daily|weekly|monthly, and next_run_at is advanced after each successful payout.
        // The scheduler only wakes up often enough to check which allowances are due.
        $dueAllowances = Allowance::query()
            ->with(['parent:id,name', 'child:id,name,username'])
            ->whereDate('next_run_at', '<=', now()->toDateString())
            ->where('status', '!=', 'paused')
            ->orderBy('next_run_at')
            ->get();

        $summary = [
            'checked' => $dueAllowances->count(),
            'executed' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($dueAllowances as $allowance) {
            try {
                $result = $this->allowanceService->execute($allowance);

                $summary['executed'] += ! empty($result['executed']) ? 1 : 0;
                $summary['failed'] += empty($result['executed']) ? 1 : 0;
                $summary['items'][] = [
                    'allowance_id' => $allowance->id,
                    'executed' => (bool) ($result['executed'] ?? false),
                    'message' => $result['message'] ?? null,
                ];
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $summary['items'][] = [
                    'allowance_id' => $allowance->id,
                    'executed' => false,
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
