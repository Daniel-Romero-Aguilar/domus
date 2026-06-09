<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Loan;
use App\Models\LoanPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoanPaymentService
{
    public function ensurePaymentsForLoan(Loan $loan): void
    {
        if ($loan->payments()->exists()) {
            return;
        }

        $count = max(1, (int) $loan->installments_count);
        $principalParts = $this->distributeCents((int) $loan->amount * 100, $count);
        $interestTotalCents = max(0, ((int) $loan->total_amount - (int) $loan->amount) * 100);
        $interestParts = $this->distributeCents($interestTotalCents, $count);
        $dueDates = $this->buildDueDates($loan);
        $today = now()->startOfDay();

        foreach (range(1, $count) as $index) {
            $position = $index - 1;
            $dueDate = $dueDates[$position];
            $status = $loan->status === 'approved' && $dueDate->lt($today) ? 'overdue' : 'pending';

            $loan->payments()->create([
                'installment_number' => $index,
                'due_date' => $dueDate->toDateString(),
                'status' => $status,
                'principal_amount_cents' => $principalParts[$position],
                'interest_amount_cents' => $interestParts[$position],
                'total_amount_cents' => $principalParts[$position] + $interestParts[$position],
            ]);
        }
    }

    public function syncStatusesForLoan(Loan $loan, ?Carbon $today = null): void
    {
        $today = ($today ?: now())->copy()->startOfDay();
        if ($loan->status === 'rejected') {
            return;
        }

        $loan->payments()
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'paid')
            ->update(['status' => 'paid']);

        $loan->payments()
            ->whereNull('paid_at')
            ->whereDate('due_date', '<', $today->toDateString())
            ->where('status', '!=', 'overdue')
            ->update(['status' => 'overdue']);

        $loan->payments()
            ->whereNull('paid_at')
            ->whereDate('due_date', '>=', $today->toDateString())
            ->where('status', '!=', 'pending')
            ->update(['status' => 'pending']);
    }

    public function syncStatusesForLoans(Collection $loans, ?Carbon $today = null): void
    {
        $today = ($today ?: now())->copy()->startOfDay();

        foreach ($loans as $loan) {
            $this->ensurePaymentsForLoan($loan);
            $this->syncStatusesForLoan($loan, $today);
        }
    }

    public function refreshLoanStatus(Loan $loan): void
    {
        $paidCount = $loan->payments()->where('status', 'paid')->count();
        $totalCount = $loan->payments()->count();

        if ($totalCount > 0 && $paidCount === $totalCount && $loan->status === 'approved') {
            $loan->status = 'paid';
            $loan->save();

            return;
        }

        if ($loan->status === 'paid' && $paidCount < $totalCount) {
            $loan->status = 'approved';
            $loan->save();
        }
    }

    public function buildSummary(Loan $loan, ?Carbon $today = null): array
    {
        $today = ($today ?: now())->copy()->startOfDay();
        $payments = $loan->relationLoaded('payments')
            ? $loan->payments->sortBy('installment_number')->values()
            : $loan->payments()->orderBy('installment_number')->get();

        $paidPayments = $payments->where('status', 'paid')->values();
        $pendingPayments = $payments->where('status', 'pending')->values();
        $overduePayments = $payments->where('status', 'overdue')->values();
        $unpaidPayments = $payments->reject(fn (LoanPayment $payment) => $payment->status === 'paid')->values();
        $payablePayments = $unpaidPayments
            ->filter(fn (LoanPayment $payment) => $payment->due_date?->copy()->startOfDay()->lessThanOrEqualTo($today))
            ->values();
        $nextPayment = $unpaidPayments->sortBy([
            ['due_date', 'asc'],
            ['installment_number', 'asc'],
        ])->first();
        $nextUpcomingPayment = $unpaidPayments
            ->filter(fn (LoanPayment $payment) => $payment->due_date?->copy()->startOfDay()->isAfter($today))
            ->sortBy([
                ['due_date', 'asc'],
                ['installment_number', 'asc'],
            ])->first();

        return [
            'total_installments' => $payments->count(),
            'paid_installments' => $paidPayments->count(),
            'pending_installments' => $pendingPayments->count(),
            'overdue_installments' => $overduePayments->count(),
            'remaining_installments' => $unpaidPayments->count(),
            'paid_total_cents' => $paidPayments->sum('total_amount_cents'),
            'paid_principal_cents' => $paidPayments->sum('principal_amount_cents'),
            'paid_interest_cents' => $paidPayments->sum('interest_amount_cents'),
            'overdue_total_cents' => $overduePayments->sum('total_amount_cents'),
            'overdue_principal_cents' => $overduePayments->sum('principal_amount_cents'),
            'overdue_interest_cents' => $overduePayments->sum('interest_amount_cents'),
            'remaining_total_cents' => $unpaidPayments->sum('total_amount_cents'),
            'remaining_principal_cents' => $unpaidPayments->sum('principal_amount_cents'),
            'remaining_interest_cents' => $unpaidPayments->sum('interest_amount_cents'),
            'payable_total_cents' => $payablePayments->sum('total_amount_cents'),
            'payable_principal_cents' => $payablePayments->sum('principal_amount_cents'),
            'payable_interest_cents' => $payablePayments->sum('interest_amount_cents'),
            'payable_payments' => $payablePayments->map(fn (LoanPayment $payment) => $this->serializePayment($payment))->all(),
            'next_payment' => $nextPayment ? $this->serializePayment($nextPayment) : null,
            'next_upcoming_payment' => $nextUpcomingPayment ? $this->serializePayment($nextUpcomingPayment) : null,
            'is_fully_paid' => $unpaidPayments->isEmpty() && $payments->isNotEmpty(),
        ];
    }

    public function payInstallment(LoanPayment $payment, int $memberUserId): array
    {
        return DB::transaction(function () use ($payment, $memberUserId): array {
            $payment = LoanPayment::query()
                ->with('loan')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $loan = Loan::query()
                ->with(['child:id,name,username', 'parent:id,name,email'])
                ->lockForUpdate()
                ->findOrFail($payment->loan_id);

            $this->ensurePaymentsForLoan($loan);
            $this->syncStatusesForLoan($loan);
            $payment->refresh();

            if ($loan->child_user_id !== $memberUserId) {
                throw new \RuntimeException('PAYMENT_FORBIDDEN');
            }

            if ($loan->status !== 'approved') {
                throw new \RuntimeException('LOAN_NOT_ACTIVE');
            }

            if ($payment->status === 'paid') {
                throw new \RuntimeException('PAYMENT_ALREADY_PAID');
            }

            if ($payment->due_date?->copy()->startOfDay()->isAfter(now()->startOfDay())) {
                throw new \RuntimeException('PAYMENT_NOT_DUE');
            }

            $memberBalance = $this->lockBalance($memberUserId);
            $parentBalance = $this->lockBalance((int) $loan->parent_user_id);
            $paymentCents = (int) $payment->total_amount_cents;

            if ((int) $memberBalance->amount < $paymentCents) {
                throw new \RuntimeException('INSUFFICIENT_BALANCE');
            }

            $memberBalance->amount = (int) $memberBalance->amount - $paymentCents;
            $memberBalance->save();
            $memberBalance->movements()->create([
                'amount_added' => $paymentCents,
                'movement_type' => 'loan_payment_debit',
                'note' => 'Pago de prestamo #'.$payment->installment_number,
                'resulting_balance' => $memberBalance->amount,
            ]);

            $parentBalance->amount = (int) $parentBalance->amount + $paymentCents;
            $parentBalance->save();
            $parentBalance->movements()->create([
                'amount_added' => $paymentCents,
                'movement_type' => 'loan_payment_credit',
                'note' => 'Pago recibido del prestamo #'.$payment->installment_number,
                'resulting_balance' => $parentBalance->amount,
            ]);

            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();

            $this->syncStatusesForLoan($loan);
            $this->refreshLoanStatus($loan);

            $loan->load(['payments' => fn ($query) => $query->orderBy('installment_number')]);
            $summary = $this->buildSummary($loan);

            return [
                'loan' => $loan,
                'payment' => $payment->fresh(),
                'member_balance_cents' => (int) $memberBalance->amount,
                'parent_balance_cents' => (int) $parentBalance->amount,
                'summary' => $summary,
            ];
        });
    }

    private function distributeCents(int $totalCents, int $count): array
    {
        $count = max(1, $count);
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;
        $parts = [];

        foreach (range(1, $count) as $index) {
            $parts[] = $base + ($index <= $remainder ? 1 : 0);
        }

        return $parts;
    }

    private function buildDueDates(Loan $loan): array
    {
        $count = max(1, (int) $loan->installments_count);
        $cursor = Carbon::parse($loan->due_date)->startOfDay();
        $dates = [];

        for ($index = 1; $index <= $count; $index++) {
            $dates[] = $cursor->copy();
            $cursor = $this->addFrequency($loan->installment_frequency, $cursor);
        }

        return $dates;
    }

    private function addFrequency(string $frequency, Carbon $date): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->copy()->addWeek(),
            'biweekly' => $date->copy()->addDays(14),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }

    private function serializePayment(LoanPayment $payment): array
    {
        return [
            'id' => (int) $payment->id,
            'installment_number' => (int) $payment->installment_number,
            'due_date' => $payment->due_date?->toDateString(),
            'status' => $payment->status,
            'status_label' => $payment->status_label,
            'is_payable_today' => $payment->is_payable_today,
            'total_amount_cents' => (int) $payment->total_amount_cents,
            'principal_amount_cents' => (int) $payment->principal_amount_cents,
            'interest_amount_cents' => (int) $payment->interest_amount_cents,
            'total_amount' => $payment->total_amount,
            'principal_amount' => $payment->principal_amount,
            'interest_amount' => $payment->interest_amount,
            'total_amount_display' => $payment->total_amount_display,
            'principal_amount_display' => $payment->principal_amount_display,
            'interest_amount_display' => $payment->interest_amount_display,
            'paid_at' => $payment->paid_at?->toISOString(),
        ];
    }

    private function lockBalance(int $userId): Balance
    {
        $balance = Balance::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        Balance::create([
            'user_id' => $userId,
            'amount' => 0,
        ]);

        return Balance::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
