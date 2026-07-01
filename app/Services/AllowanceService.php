<?php

namespace App\Services;

use App\Models\Allowance;
use App\Models\AllowancePayment;
use App\Models\Balance;
use App\Services\DomusNotificationService;
use App\Support\BalanceHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AllowanceService
{
    public function __construct(private readonly DomusNotificationService $notifications)
    {
    }

    public function execute(int|Allowance $allowance, bool $force = false): array
    {
        $allowanceId = $allowance instanceof Allowance ? $allowance->id : $allowance;

        return DB::transaction(function () use ($allowanceId, $force) {
            $allowance = Allowance::query()
                ->with(['parent:id,name', 'child:id,name,username'])
                ->whereKey($allowanceId)
                ->lockForUpdate()
                ->firstOrFail();

            $now = now()->startOfSecond();
            $today = $now->copy()->startOfDay();
            $startAt = Carbon::parse($allowance->start_at)->startOfDay();
            $scheduledFor = Carbon::parse($allowance->next_run_at ?? $allowance->start_at)->startOfSecond();

            if (! $force && $startAt->greaterThan($today)) {
                return [
                    'executed' => false,
                    'message' => 'Aun no llega la fecha de inicio de esta mesada.',
                    'allowance' => $allowance,
                ];
            }

            if (! $force && $scheduledFor->greaterThan($now)) {
                return [
                    'executed' => false,
                    'message' => 'Esta mesada todavia no toca ejecutarse.',
                    'allowance' => $allowance,
                ];
            }

            $payment = AllowancePayment::query()
                ->where('allowance_id', $allowance->id)
                ->where('scheduled_for', $scheduledFor->toDateTimeString())
                ->lockForUpdate()
                ->first();

            if ($payment && $payment->status === 'paid') {
                $nextRun = $this->nextRunDate($allowance->frequency, $scheduledFor);

                $allowance->status = 'active';
                $allowance->last_executed_at = $payment->executed_at ?? $allowance->last_executed_at;
                $allowance->last_failed_at = null;
                $allowance->next_run_at = $nextRun;
                $allowance->save();

                return [
                    'executed' => true,
                    'already_executed' => true,
                    'advanced_schedule' => true,
                    'message' => 'Esta mesada ya fue ejecutada para esa fecha.',
                    'allowance' => $allowance->fresh(['parent:id,name', 'child:id,name,username']),
                    'payment' => $payment,
                    'remaining_parent_balance' => BalanceHelper::parentMoneyUsedCents($allowance->parent),
                    'remaining_parent_balance_display' => BalanceHelper::displayCents(BalanceHelper::parentMoneyUsedCents($allowance->parent)),
                    'child_balance_cents' => $payment->child_balance_after === null ? null : (int) $payment->child_balance_after,
                    'child_balance_display' => $payment->child_balance_after === null ? null : BalanceHelper::displayCents((int) $payment->child_balance_after),
                ];
            }

            $amountCents = (int) $allowance->amount_cents;

            $parentBalance = Balance::query()
                ->where('user_id', $allowance->parent_user_id)
                ->lockForUpdate()
                ->first();

            $payment = $payment ?: new AllowancePayment([
                'allowance_id' => $allowance->id,
                'scheduled_for' => $scheduledFor->toDateTimeString(),
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

            $parentBalanceBefore = BalanceHelper::parentMoneyUsedCents($allowance->parent);
            $parentBalanceAfter = $parentBalanceBefore + $amountCents;
            $childBalanceBefore = (int) $childBalance->amount;

            $parentBalance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'allowance_debit',
                'note' => 'Allowance paid to child',
                'resulting_balance' => $parentBalanceAfter,
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
                'parent_balance_after' => $parentBalanceAfter,
                'child_balance_before' => $childBalanceBefore,
                'child_balance_after' => (int) $childBalance->amount,
                'executed_at' => $now,
                'failed_at' => null,
                'failure_reason' => null,
            ]);
            $payment->save();

            $allowance->status = 'active';
            $allowance->last_executed_at = $now;
            $allowance->last_failed_at = null;
            $allowance->next_run_at = $this->nextRunDate($allowance->frequency, $scheduledFor);
            $allowance->save();

            $amountText = $this->notifications->money($amountCents);
            $childName = $allowance->child?->name ?? 'un integrante';
            $parentName = $allowance->parent?->name ?? 'tu familia';

            $this->notifications->recordForParent(
                $allowance->parent_user_id,
                'pago',
                'mesadas',
                'Pagaste una mesada de '.$amountText.' a '.$childName.'.'
            );
            $this->notifications->recordForMember(
                $allowance->child_user_id,
                'pago',
                'mesadas',
                'Recibiste una mesada de '.$amountText.' de '.$parentName.'.'
            );

            return [
                'executed' => true,
                'message' => 'Mesada ejecutada correctamente.',
                'remaining_parent_balance' => BalanceHelper::parentMoneyUsedCents($allowance->parent),
                'remaining_parent_balance_display' => BalanceHelper::displayCents(BalanceHelper::parentMoneyUsedCents($allowance->parent)),
                'child_balance_cents' => (int) $childBalance->amount,
                'child_balance_display' => BalanceHelper::displayCents((int) $childBalance->amount),
                'allowance' => $allowance->fresh(['parent:id,name', 'child:id,name,username']),
                'payment' => $payment->fresh(),
            ];
        });
    }

    public function duePaymentPreview(Allowance $allowance, ?Carbon $asOf = null, int $cap = 1000): array
    {
        $now = ($asOf ?: now())->copy()->startOfSecond();

        if (! $allowance->next_run_at) {
            return ['count' => 0, 'capped' => false];
        }

        $startAt = Carbon::parse($allowance->start_at)->startOfDay();
        if ($startAt->greaterThan($now->copy()->startOfDay())) {
            return ['count' => 0, 'capped' => false];
        }

        $scheduledFor = Carbon::parse($allowance->next_run_at)->startOfSecond();
        if ($scheduledFor->greaterThan($now)) {
            return ['count' => 0, 'capped' => false];
        }

        if ($allowance->frequency === 'ten_seconds') {
            $secondsLate = max(0, $now->getTimestamp() - $scheduledFor->getTimestamp());
            $count = intdiv($secondsLate, 10) + 1;

            return [
                'count' => min($count, $cap),
                'capped' => $count > $cap,
            ];
        }

        $count = 0;
        $cursor = $scheduledFor->copy();

        while ($cursor->lessThanOrEqualTo($now) && $count < $cap) {
            $count++;
            $cursor = $this->nextRunDate($allowance->frequency, $cursor);
        }

        return [
            'count' => $count,
            'capped' => $cursor->lessThanOrEqualTo($now),
        ];
    }

    public function nextRunDate(string $frequency, Carbon $from): Carbon
    {
        return match ($frequency) {
            'ten_seconds' => $from->copy()->addSeconds(10),
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }
}
