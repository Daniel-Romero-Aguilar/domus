<?php

namespace App\Services;

use App\Models\SavingsBox;
use App\Models\SavingsBoxAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SavingsBoxInterestService
{
    private const MICROCENTS_PER_CENT = 1000000;
    private const SECONDS_PER_YEAR = 31536000;

    public function runDueForParent(int $parentUserId, ?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::now();

        $boxes = SavingsBox::query()
            ->with('accounts')
            ->where('parent_user_id', $parentUserId)
            ->where('status', 'active')
            ->whereDate('delivery_date', '>=', $asOf->toDateString())
            ->get();

        return $this->accrueForBoxes($boxes, $asOf);
    }

    public function runDueSavingsBoxes(?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::now();

        $boxes = SavingsBox::query()
            ->with('accounts')
            ->where('status', 'active')
            ->whereDate('delivery_date', '>=', $asOf->toDateString())
            ->get();

        return $this->accrueForBoxes($boxes, $asOf);
    }

    public function runDueForUser(int $userId, ?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::now();
        $summary = ['boxes_checked' => 0, 'accounts_checked' => 0, 'interest_cents_added' => 0];

        $accounts = SavingsBoxAccount::query()
            ->with('savingsBox')
            ->where('user_id', $userId)
            ->whereHas('savingsBox', function ($query) use ($asOf): void {
                $query->where('status', 'active')
                    ->whereDate('delivery_date', '>=', $asOf->toDateString());
            })
            ->get();

        DB::transaction(function () use ($accounts, $asOf, &$summary): void {
            foreach ($accounts as $account) {
                $summary['accounts_checked']++;
                $summary['boxes_checked']++;
                $summary['interest_cents_added'] += $this->accrueAccountUntil($account, $asOf, $account->savingsBox);
            }
        });

        return $summary;
    }

    private function accrueForBoxes(iterable $boxes, Carbon $asOf): array
    {
        $summary = ['boxes_checked' => 0, 'accounts_checked' => 0, 'interest_cents_added' => 0];

        DB::transaction(function () use ($boxes, $asOf, &$summary): void {
            foreach ($boxes as $box) {
                $summary['boxes_checked']++;

                foreach ($box->accounts as $account) {
                    $summary['accounts_checked']++;
                    $summary['interest_cents_added'] += $this->accrueAccountUntil($account, $asOf, $box);
                }
            }
        });

        return $summary;
    }

    public function accrueAccountUntil(SavingsBoxAccount $account, ?Carbon $moment = null, ?SavingsBox $box = null): int
    {
        $moment ??= Carbon::now();
        $box ??= $account->relationLoaded('savingsBox') ? $account->savingsBox : $account->savingsBox()->first();

        if (! $box) {
            return 0;
        }

        $lastAccruedAt = $account->interest_accrued_until_at
            ? Carbon::parse($account->interest_accrued_until_at)
            : $moment->copy();

        if ($lastAccruedAt->greaterThanOrEqualTo($moment)) {
            if ($account->interest_accrued_until_at === null) {
                $account->forceFill([
                    'interest_accrued_until_at' => $moment,
                    'last_interest_accrued_on' => $moment->toDateString(),
                ])->save();
            }
            return 0;
        }

        $this->activateLegacyPendingPrincipal($account);

        $seconds = max(0, $lastAccruedAt->diffInSeconds($moment));
        $baseCents = (int) $account->principal_cents + (int) $account->earned_interest_cents;
        $interestCents = 0;

        if ($seconds > 0 && $baseCents > 0 && (float) $box->annual_gain_percent > 0) {
            $annualRate = max(0, (float) $box->annual_gain_percent) / 100;
            $growthFactor = pow(1 + $annualRate, $seconds / self::SECONDS_PER_YEAR) - 1;
            $newInterestMicrocents = (int) floor($baseCents * $growthFactor * self::MICROCENTS_PER_CENT);
            $totalMicrocents = (int) $account->interest_remainder_microcents + $newInterestMicrocents;
            $interestCents = intdiv($totalMicrocents, self::MICROCENTS_PER_CENT);

            $account->interest_remainder_microcents = $totalMicrocents % self::MICROCENTS_PER_CENT;
            if ($interestCents > 0) {
                $principalBefore = (int) $account->principal_cents;
                $earnedBefore = (int) $account->earned_interest_cents;
                $account->earned_interest_cents = $earnedBefore + $interestCents;
                $this->recordMovement($account, $box, 'interest', $interestCents, $principalBefore, $principalBefore, $earnedBefore, $account->earned_interest_cents, $moment);
            }
        }

        $account->interest_accrued_until_at = $moment;
        $account->last_interest_accrued_on = $moment->toDateString();
        $account->save();

        return $interestCents;
    }

    public function recordMovement(
        SavingsBoxAccount $account,
        SavingsBox $box,
        string $type,
        int $amountCents,
        int $principalBefore,
        int $principalAfter,
        int $earnedBefore,
        int $earnedAfter,
        Carbon $occurredAt,
        ?string $note = null
    ): void {
        $account->movements()->create([
            'savings_box_id' => $box->id,
            'user_id' => $account->user_id,
            'movement_type' => $type,
            'amount_cents' => $amountCents,
            'principal_before_cents' => $principalBefore,
            'principal_after_cents' => $principalAfter,
            'earned_interest_before_cents' => $earnedBefore,
            'earned_interest_after_cents' => $earnedAfter,
            'occurred_at' => $occurredAt,
            'note' => $note,
        ]);
    }

    private function activateLegacyPendingPrincipal(SavingsBoxAccount $account): void
    {
        $pendingCents = (int) ($account->principal_pending_cents ?? 0);
        if ($pendingCents > 0) {
            $account->principal_cents = (int) $account->principal_cents + $pendingCents;
            $account->principal_pending_cents = 0;
        }
    }
}
