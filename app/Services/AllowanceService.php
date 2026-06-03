<?php

namespace App\Services;

use App\Models\Allowance;
use App\Models\AllowancePayment;
use App\Models\Balance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AllowanceService
{
    public function execute(int|Allowance $allowance, bool $force = false, ?string $scheduledForOverride = null): array
    {
        $allowanceId = $allowance instanceof Allowance ? $allowance->id : $allowance;

        return DB::transaction(function () use ($allowanceId, $force, $scheduledForOverride) {
            $allowance = Allowance::query()
                ->with(['parent:id,name', 'child:id,name,username'])
                ->whereKey($allowanceId)
                ->lockForUpdate()
                ->firstOrFail();

            $now = now();
            $today = $now->copy()->startOfDay();
            $startAt = Carbon::parse($allowance->start_at)->startOfDay();
            $scheduledFor = Carbon::parse($scheduledForOverride ?? $allowance->next_run_at ?? $allowance->start_at)->startOfDay();

            if (! $force && $startAt->greaterThan($today)) {
                return [
                    'executed' => false,
                    'message' => 'Aun no llega la fecha de inicio de esta mesada.',
                    'allowance' => $allowance,
                ];
            }

            if (! $force && $scheduledFor->greaterThan($today)) {
                return [
                    'executed' => false,
                    'message' => 'Esta mesada todavia no toca ejecutarse.',
                    'allowance' => $allowance,
                ];
            }

            $payment = AllowancePayment::query()
                ->where('allowance_id', $allowance->id)
                ->whereDate('scheduled_for', $scheduledFor->toDateString())
                ->lockForUpdate()
                ->first();

            if ($payment && $payment->status === 'paid') {
                return [
                    'executed' => true,
                    'already_executed' => true,
                    'message' => 'Esta mesada ya fue ejecutada para esa fecha.',
                    'allowance' => $allowance->fresh(['parent:id,name', 'child:id,name,username']),
                    'payment' => $payment,
                ];
            }

            $amountCents = (int) $allowance->amount_cents;

            $parentBalance = Balance::query()
                ->where('user_id', $allowance->parent_user_id)
                ->lockForUpdate()
                ->first();

            $payment = $payment ?: new AllowancePayment([
                'allowance_id' => $allowance->id,
                'scheduled_for' => $scheduledFor->toDateString(),
                'amount_cents' => (int) $allowance->amount_cents,
            ]);

            if (! $parentBalance) {
                $parentBalance = Balance::create([
                    'user_id' => $allowance->parent_user_id,
                    'amount' => 0,
                ]);
                $parentBalance = Balance::query()
                    ->where('user_id', $allowance->parent_user_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $childBalance = Balance::query()
                ->where('user_id', $allowance->child_user_id)
                ->lockForUpdate()
                ->first();

            if (! $childBalance) {
                $childBalance = Balance::create([
                    'user_id' => $allowance->child_user_id,
                    'amount' => 0,
                ]);
                $childBalance = Balance::query()
                    ->where('user_id', $allowance->child_user_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ((int) $parentBalance->amount < $amountCents) {
                $payment->fill([
                    'status' => 'failed',
                    'failed_at' => $now,
                    'failure_reason' => 'Saldo insuficiente',
                    'parent_balance_before' => (int) $parentBalance->amount,
                    'parent_balance_after' => (int) $parentBalance->amount,
                    'child_balance_before' => (int) $childBalance->amount,
                    'child_balance_after' => (int) $childBalance->amount,
                    'executed_at' => null,
                ]);
                $payment->save();

                $allowance->status = 'pending';
                $allowance->last_failed_at = $now;
                $allowance->save();

                return [
                    'executed' => false,
                    'message' => 'No tienes saldo suficiente para ejecutar esta mesada.',
                    'remaining_parent_balance' => (int) $parentBalance->amount,
                    'allowance' => $allowance->fresh(['parent:id,name', 'child:id,name,username']),
                    'payment' => $payment->fresh(),
                ];
            }

            $parentBalanceBefore = (int) $parentBalance->amount;
            $childBalanceBefore = (int) $childBalance->amount;

            $parentBalance->amount = (int) $parentBalance->amount - $amountCents;
            $parentBalance->save();

            $parentBalance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'allowance_debit',
                'note' => 'Allowance paid to child',
                'resulting_balance' => $parentBalance->amount,
            ]);

            $childBalance->amount = (int) $childBalance->amount + $amountCents;
            $childBalance->save();

            $childBalance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'allowance_credit',
                'note' => 'Allowance received from parent',
                'resulting_balance' => $childBalance->amount,
            ]);

            $payment->fill([
                'status' => 'paid',
                'amount_cents' => (int) $allowance->amount_cents,
                'parent_balance_before' => $parentBalanceBefore,
                'parent_balance_after' => (int) $parentBalance->amount,
                'child_balance_before' => $childBalanceBefore,
                'child_balance_after' => (int) $childBalance->amount,
                'executed_at' => $now,
                'failed_at' => null,
                'failure_reason' => null,
            ]);
            $payment->save();

            $nextRun = $this->nextRunDate($allowance->frequency, $scheduledFor);
            while ($nextRun->lessThanOrEqualTo($today)) {
                $nextRun = $this->nextRunDate($allowance->frequency, $nextRun);
            }

            $allowance->status = 'active';
            $allowance->last_executed_at = $now;
            $allowance->last_failed_at = null;
            $allowance->next_run_at = $nextRun;
            $allowance->save();

            return [
                'executed' => true,
                'message' => 'Mesada ejecutada correctamente.',
                'remaining_parent_balance' => (int) $parentBalance->amount,
                'allowance' => $allowance->fresh(['parent:id,name', 'child:id,name,username']),
                'payment' => $payment->fresh(),
            ];
        });
    }

    public function nextRunDate(string $frequency, Carbon $from): Carbon
    {
        return match ($frequency) {
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }
}
