<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\LoanAmountGuard;
use App\Support\BalanceHelper;

class Loan extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Loan $loan): void {
            // Every persisted loan amount must remain positive.
            $fields = ['amount', 'total_amount', 'installment_amount'];
            if ($loan->exists && ! collect($fields)->contains(fn (string $field): bool => $loan->isDirty($field))) {
                return;
            }

            LoanAmountGuard::assertPositive(
                BalanceHelper::parseMoneyToCents((string) $loan->getAttribute('amount')),
                BalanceHelper::parseMoneyToCents((string) $loan->getAttribute('total_amount')),
                BalanceHelper::parseMoneyToCents((string) $loan->getAttribute('installment_amount')),
            );
        });
    }

    protected $appends = [
        'installment_plan',
    ];

    protected $casts = [
        'parent_user_id' => 'integer',
        'child_user_id' => 'integer',
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'installments_count' => 'integer',
        'has_interest' => 'boolean',
        'annual_interest_rate' => 'float',
        'fixed_interest_amount' => 'decimal:2',
        'responded_at' => 'datetime',
        'requested_by_user_id' => 'integer',
        'total_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'requested_amount' => 'decimal:2',
        'requested_reason' => 'string',
        'requested_months' => 'integer',
        'initial_due_date' => 'date',
        'initial_installments_count' => 'integer',
        'initial_installment_frequency' => 'string',
        'initial_has_interest' => 'boolean',
        'initial_interest_mode' => 'string',
        'initial_annual_interest_rate' => 'float',
        'initial_fixed_interest_amount' => 'decimal:2',
        'initial_total_amount' => 'decimal:2',
        'initial_installment_amount' => 'decimal:2',
        'proposed_due_date' => 'date',
        'parent_reviewed_at' => 'datetime',
        'reserved_amount_cents' => 'integer',
        'funds_reserved_at' => 'datetime',
        'funds_released_at' => 'datetime',
        'funds_disbursed_at' => 'datetime',
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
        'requested_amount',
        'requested_reason',
        'requested_months',
        'initial_due_date',
        'initial_installments_count',
        'initial_installment_frequency',
        'initial_has_interest',
        'initial_interest_mode',
        'initial_annual_interest_rate',
        'initial_fixed_interest_amount',
        'initial_total_amount',
        'initial_installment_amount',
        'proposed_due_date',
        'parent_reviewed_at',
        'reserved_amount_cents',
        'funds_reserved_at',
        'funds_released_at',
        'funds_disbursed_at',
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

    public function getInstallmentPlanAttribute(): ?array
    {
        return in_array($this->status, ['approved', 'paid'], true) ? $this->installmentPlan() : null;
    }

    public function installmentPlan(): array
    {
        $count = max(1, (int) $this->installments_count);
        $totalCents = max(0, BalanceHelper::parseMoneyToCents((string) $this->total_amount));
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
            return max(0, BalanceHelper::parseMoneyToCents((string) $this->total_amount));
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
