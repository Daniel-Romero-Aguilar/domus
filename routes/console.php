<?php

use App\Services\AllowanceSchedulerService;
use App\Services\SavingsBoxSettlementService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Production shape:
// Schedule::call(fn (): void => app(AllowanceSchedulerService::class)->runDueAllowances())
//     ->everyMinute()
//     ->name('domus-allowances-dispatch');
//
// For local testing, we wake up every 10 seconds so a ten_seconds allowance behaves like the real scheduler.
Schedule::call(function (): void {
    $result = app(AllowanceSchedulerService::class)->runDueAllowances();

    Log::info('Allowance scheduler tick', $result);
})
    ->everyTenSeconds()
    ->name('domus-allowances-dispatch-test');

Schedule::call(function (): void {
    $result = app(SavingsBoxSettlementService::class)->runDue();

    Log::info('Savings box settlement tick', $result);
})
    ->dailyAt((string) config('savings_box.settlement_time', '23:59'))
    ->name('domus-savings-box-settlement');
