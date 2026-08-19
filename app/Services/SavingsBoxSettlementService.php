<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\SavingsBox;
use App\Models\SavingsBoxAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SavingsBoxSettlementService
{
    public function __construct(
        private readonly SavingsBoxInterestService $interestService,
        private readonly DomusNotificationService $notifications
    ) {}

    /**
     * Settle every active box whose frozen cutoff has arrived.
     *
     * @return array{checked: int, settled: int, skipped: int, failed: int, paid_cents: int}
     */
    public function runDue(?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::now();
        $summary = ['checked' => 0, 'settled' => 0, 'skipped' => 0, 'failed' => 0, 'paid_cents' => 0];

        $boxIds = SavingsBox::query()
            ->where('status', 'active')
            ->whereNotNull('cutoff_at')
            ->where('cutoff_at', '<=', $asOf)
            ->orderBy('cutoff_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($boxIds as $boxId) {
            $summary['checked']++;

            try {
                $result = $this->settleBox((int) $boxId, $asOf);

                if ($result['settled']) {
                    $summary['settled']++;
                    $summary['paid_cents'] += $result['paid_cents'];
                } else {
                    $summary['skipped']++;
                }
            } catch (\Throwable $exception) {
                $summary['failed']++;

                Log::error('Savings box settlement failed.', [
                    'savings_box_id' => (int) $boxId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Public on purpose: tests and maintenance commands can invoke the same
     * transaction used by the scheduler without reproducing settlement logic.
     *
     * @return array{settled: bool, reason: string, paid_cents: int, accounts_settled: int, savings_box: SavingsBox}
     */
    public function settleBox(int|SavingsBox $savingsBox, ?Carbon $asOf = null, bool $force = false): array
    {
        $asOf ??= Carbon::now();
        $boxId = $savingsBox instanceof SavingsBox ? $savingsBox->id : $savingsBox;

        return DB::transaction(function () use ($boxId, $asOf, $force): array {
            $box = SavingsBox::query()
                ->whereKey($boxId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($box->status !== 'active' || $box->settled_at !== null) {
                return [
                    'settled' => false,
                    'reason' => 'already_settled',
                    'paid_cents' => 0,
                    'accounts_settled' => 0,
                    'savings_box' => $box,
                ];
            }

            $cutoffAt = $box->cutoff_at
                ? Carbon::parse($box->cutoff_at)
                : Carbon::parse($box->delivery_date)->endOfDay();

            if (! $force && $cutoffAt->gt($asOf)) {
                return [
                    'settled' => false,
                    'reason' => 'not_due',
                    'paid_cents' => 0,
                    'accounts_settled' => 0,
                    'savings_box' => $box,
                ];
            }

            $accounts = SavingsBoxAccount::query()
                ->where('savings_box_id', $box->id)
                ->with('user:id,name,username,role')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $paidCents = 0;
            $accountsSettled = 0;

            foreach ($accounts as $account) {
                if ($account->settled_at !== null) {
                    continue;
                }

                $result = $this->settleAccount($box, $account, $cutoffAt, $asOf);
                $paidCents += $result['paid_cents'];
                $accountsSettled++;
            }

            $box->forceFill([
                'status' => 'completed',
                'settled_at' => $asOf,
            ])->save();

            $this->notifications->record(
                (int) $box->parent_user_id,
                'liquidacion',
                'cajas_ahorro',
                'La caja '.$box->name.' finalizo y entrego '.$this->notifications->money($paidCents).'.'
            );

            return [
                'settled' => true,
                'reason' => 'settled',
                'paid_cents' => $paidCents,
                'accounts_settled' => $accountsSettled,
                'savings_box' => $box->fresh(['accounts.user:id,name,username']),
            ];
        });
    }

    /** @return array{paid_cents: int} */
    private function settleAccount(
        SavingsBox $box,
        SavingsBoxAccount $account,
        Carbon $cutoffAt,
        Carbon $settledAt
    ): array {
        if ($box->benefit_mode === 'legacy_accrual') {
            $this->interestService->accrueAccountUntil($account, $cutoffAt, $box);
            $account->refresh();
        }

        $principalCents = (int) $account->principal_cents + (int) $account->principal_pending_cents;
        $benefitCents = $box->benefit_mode === 'legacy_accrual'
            ? (int) $account->earned_interest_cents
            : (int) round($principalCents * max(0, (float) $box->benefit_percent) / 100);
        $totalCents = $principalCents + $benefitCents;

        $balance = Balance::query()->firstOrCreate(
            ['user_id' => $account->user_id],
            ['amount' => 0]
        );

        $balance = Balance::query()
            ->whereKey($balance->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($totalCents > 0) {
            $balance->amount = (int) $balance->amount + $totalCents;
            $balance->save();

            $balance->movements()->create([
                'amount_added' => $totalCents,
                'movement_type' => 'savings_box_settlement',
                'note' => 'Savings box settlement: '.$box->name,
                'resulting_balance' => $balance->amount,
            ]);
        }

        $this->interestService->recordMovement(
            $account,
            $box,
            'settlement',
            $totalCents,
            $principalCents,
            0,
            $benefitCents,
            0,
            $settledAt,
            'Savings box principal and benefit settlement'
        );

        $account->forceFill([
            'principal_cents' => 0,
            'principal_pending_cents' => 0,
            'earned_interest_cents' => 0,
            'interest_remainder_microcents' => 0,
            'settled_principal_cents' => $principalCents,
            'settled_benefit_cents' => $benefitCents,
            'settled_total_cents' => $totalCents,
            'settled_at' => $settledAt,
        ])->save();

        if ($account->user) {
            $this->notifications->record(
                (int) $account->user_id,
                'liquidacion',
                'cajas_ahorro',
                'La caja '.$box->name.' finalizo. Recibiste '.$this->notifications->money($totalCents)
                    .' ('.$this->notifications->money($principalCents).' ahorrados y '
                    .$this->notifications->money($benefitCents).' de beneficio).'
            );
        }

        return ['paid_cents' => $totalCents];
    }
}
