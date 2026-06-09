<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $appends = [
        'installment_plan',
    ];

    protected $fillable = [
        'parent_user_id',
        'child_user_id',
        'amount',
        'reason',
        'due_date',
        'installments_count',
        'installment_frequency',
        'has_interest',
        'interest_mode',
        'annual_interest_rate',
        'fixed_interest_amount',
        'status',
        'rejection_reason',
        'responded_at',
        'requested_by_user_id',
        'total_amount',
        'installment_amount',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class)->orderBy('installment_number');
    }

    public function getInstallmentPlanAttribute(): array
    {
        return $this->installmentPlan();
    }

    public function installmentPlan(): array
    {
        $count = max(1, (int) $this->installments_count);
        $totalCents = max(0, (int) $this->total_amount * 100);
        $baseCents = intdiv($totalCents, $count);
        $extraPayments = $totalCents % $count;
        $basePayments = $count - $extraPayments;
        $groups = [];

        if ($extraPayments > 0) {
            $groups[] = [
                'count' => $extraPayments,
                'amount_cents' => $baseCents + 1,
                'amount' => ($baseCents + 1) / 100,
                'amount_display' => $this->moneyFromCents($baseCents + 1),
            ];
        }

        if ($basePayments > 0) {
            $groups[] = [
                'count' => $basePayments,
                'amount_cents' => $baseCents,
                'amount' => $baseCents / 100,
                'amount_display' => $this->moneyFromCents($baseCents),
            ];
        }

        return [
            'total_cents' => $totalCents,
            'total_display' => $this->moneyFromCents($totalCents),
            'installments_count' => $count,
            'groups' => $groups,
            'summary' => $this->installmentPlanSummary($groups),
        ];
    }

    public function estimatedPaidCentsForInstallments(int $paidInstallments): int
    {
        $count = max(1, (int) $this->installments_count);
        $paidInstallments = min($count, max(0, $paidInstallments));

        if ($paidInstallments === 0) {
            return 0;
        }

        if ($paidInstallments >= $count) {
            return max(0, (int) $this->total_amount * 100);
        }

        $paidCents = 0;
        $remaining = $paidInstallments;

        foreach ($this->installmentPlan()['groups'] as $group) {
            if ($remaining < 1) {
                break;
            }

            $used = min($remaining, (int) $group['count']);
            $paidCents += $used * (int) $group['amount_cents'];
            $remaining -= $used;
        }

        return $paidCents;
    }

    private function installmentPlanSummary(array $groups): string
    {
        if (count($groups) === 1) {
            $group = $groups[0];
            $label = (int) $group['count'] === 1 ? 'pago' : 'pagos';

            return $group['count'].' '.$label.' de '.$group['amount_display'];
        }

        return collect($groups)
            ->map(function (array $group): string {
                $label = (int) $group['count'] === 1 ? 'pago' : 'pagos';

                return $group['count'].' '.$label.' de '.$group['amount_display'];
            })
            ->implode(' + ');
    }

    private function moneyFromCents(int $cents): string
    {
        return '$'.number_format($cents / 100, 2, '.', ',');
    }
}
