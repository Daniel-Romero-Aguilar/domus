<?php

use App\Models\BillingSubscription;
use App\Services\AllowanceSchedulerService;
use App\Services\BillingSubscriptionService;
use App\Services\SavingsBoxSettlementService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:sync {--subscription= : ID de una compra concreta} {--retry : Reiniciar sus reintentos después de revisar el problema}', function (BillingSubscriptionService $billing) {
    if ($this->option('retry') && ! $this->option('subscription')) {
        $this->error('Indica --subscription=ID para reactivar una compra concreta.');

        return 1;
    }
    if ($id = $this->option('subscription')) {
        $record = BillingSubscription::findOrFail($id);
        if ($this->option('retry')) {
            // An explicit operator action never resets the user's grace period.
            $record->forceFill([
                'sync_attempts' => 0, 'sync_started_at' => now('UTC'),
                'sync_stopped_at' => null, 'next_sync_at' => now('UTC'),
            ])->save();
        }
        $billing->refresh($record, true);
        $this->info('Compra revisada.');
    } else {
        $this->info('Compras revisadas: '.$billing->runDue());
    }
})->purpose('Actualizar compras pendientes con reintentos limitados');

Schedule::command('billing:sync')->everyMinute()->withoutOverlapping(5);

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
