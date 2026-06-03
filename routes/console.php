<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use App\Services\AllowanceSchedulerService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Production shape:
// Schedule::call(fn (): void => app(AllowanceSchedulerService::class)->runDueAllowances())
//     ->everyMinute()
//     ->name('domus-allowances-dispatch');
//
// For testing, we wake up every 10 seconds so you can see payouts happen quickly.
Schedule::call(function (): void {
    $result = app(AllowanceSchedulerService::class)->runDueAllowances();

    Log::info('Allowance scheduler tick', $result);
})
    ->everyTenSeconds()
    ->name('domus-allowances-dispatch-test');
