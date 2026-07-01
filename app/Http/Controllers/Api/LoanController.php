<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\BalanceMovement;
use App\Models\FamilyMember;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Services\DomusAchievementService;
use App\Services\DomusNotificationService;
use App\Services\DomusPointsAccountService;
use App\Services\LoanPaymentService;
use App\Support\BalanceHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class LoanController extends Controller
{
    public function __construct(
        private readonly DomusNotificationService $notifications,
        private readonly DomusAchievementService $achievements,
        private readonly LoanPaymentService $loanPayments,
        private readonly DomusPointsAccountService $pointsAccount,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            Log::error('LOANS_INDEX_FAILED: No authenticated user from token.', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthenticated context in /loans.'], 401);
        }

        if (! in_array($user->role, ['parent', 'child', 'member'], true)) {
            Log::error('LOANS_INDEX_FAILED: Invalid role.', [
                'user_id' => $user->id,
                'role' => $user->role,
            ]);
            return response()->json(['message' => 'Invalid role for loans listing.'], 422);
        }

        if ($user->role === 'parent') {
            $loans = Loan::query()
                ->with(['child:id,name,username'])
                ->where('parent_user_id', $user->id)
                ->latest()
                ->get();
        } else {
            $loans = Loan::query()
                ->with(['parent:id,name,email'])
                ->where('child_user_id', $user->id)
                ->latest()
                ->get();
        }

        $this->loanPayments->syncStatusesForLoans($loans);
        $loans->load(['payments' => fn ($query) => $query->orderBy('installment_number')]);

        foreach ($loans as $loan) {
            $this->loanPayments->refreshLoanStatus($loan);
        }

        return response()->json([
            'loans' => $loans->map(fn (Loan $loan) => $this->presentLoan($loan))->values(),
        ]);
    }

    public function waiting(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            Log::error('LOANS_WAITING_FAILED: No authenticated user from token.', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthenticated context in /loans/waiting.'], 401);
        }

        if (! in_array($user->role, ['parent', 'child', 'member'], true)) {
            Log::error('LOANS_WAITING_FAILED: Invalid role.', [
                'user_id' => $user->id,
                'role' => $user->role,
            ]);
            return response()->json(['message' => 'Invalid role for waiting loans listing.'], 422);
        }

        $loans = Loan::query()
            ->with($user->role === 'parent'
                ? ['child:id,name,username']
                : ['parent:id,name,email'])
            ->when(
                $user->role === 'parent',
                fn ($query) => $query->where('parent_user_id', $user->id)->where('status', 'pending'),
                fn ($query) => $query->where('child_user_id', $user->id)->where('status', 'pending')
            )
            ->latest()
            ->get();

        $this->loanPayments->syncStatusesForLoans($loans);
        $loans->load(['payments' => fn ($query) => $query->orderBy('installment_number')]);

        return response()->json([
            'loans' => $loans->map(fn (Loan $loan) => $this->presentLoan($loan))->values(),
        ]);
    }

    public function activeTotal(Request $request): JsonResponse
    {
        $parent = $request->user();
        if (! $parent) {
            return response()->json(['message' => 'Unauthenticated context in /loans/active-total.'], 401);
        }

        if ($parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can view active loan totals.'], 403);
        }

        $loans = Loan::query()
            ->with(['payments' => fn ($query) => $query->orderBy('installment_number')])
            ->where('parent_user_id', $parent->id)
            ->whereIn('status', ['approved', 'paid'])
            ->get();

        $this->loanPayments->syncStatusesForLoans($loans);
        $paidTotalCents = 0;
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        foreach ($loans as $loan) {
            $summary = $this->loanPayments->buildSummary($loan);
            $paidTotalCents += (int) $summary['paid_total_cents'];
        }

        $interestGeneratedThisMonthCents = (int) LoanPayment::query()
            ->selectRaw('COALESCE(SUM(loan_payments.interest_amount_cents), 0) as total')
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->where('loans.parent_user_id', $parent->id)
            ->where('loan_payments.status', 'paid')
            ->whereBetween('loan_payments.paid_at', [$monthStart, $monthEnd])
            ->value('total');

        $interestGeneratedHistoricalCents = (int) LoanPayment::query()
            ->selectRaw('COALESCE(SUM(loan_payments.interest_amount_cents), 0) as total')
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->where('loans.parent_user_id', $parent->id)
            ->where('loan_payments.status', 'paid')
            ->value('total');

        return response()->json([
            'total_active_loans' => $loans->count(),
            'estimated_total_paid' => $paidTotalCents / 100,
            'estimated_total_paid_cents' => $paidTotalCents,
            'estimated_total_paid_display' => BalanceHelper::displayCents($paidTotalCents),
            'interest_generated_this_month' => $interestGeneratedThisMonthCents / 100,
            'interest_generated_this_month_cents' => $interestGeneratedThisMonthCents,
            'interest_generated_this_month_display' => BalanceHelper::displayCents($interestGeneratedThisMonthCents),
            'interest_generated_historical' => $interestGeneratedHistoricalCents / 100,
            'interest_generated_historical_cents' => $interestGeneratedHistoricalCents,
            'interest_generated_historical_display' => BalanceHelper::displayCents($interestGeneratedHistoricalCents),
            'currency' => 'MXN',
            'meta' => [
                'is_estimated' => false,
                'estimation_note' => 'The total now comes from the actual payment ledger.',
                'interest_source' => 'paid loan payments only',
                'interest_period' => [
                    'from' => $monthStart->toDateString(),
                    'to' => $monthEnd->toDateString(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'child_user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:120'],
            'due_date' => ['required', 'date'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_frequency' => ['required', 'in:weekly,biweekly,monthly'],
            'has_interest' => ['required', 'boolean'],
            'interest_mode' => ['nullable', 'in:percent,fixed'],
            'annual_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_interest_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $parentId = null;
        $childId = null;

        if ($actor->role === 'parent') {
            $parentId = $actor->id;
            $childId = (int) $validated['child_user_id'];
            $isChildOfParent = FamilyMember::query()
                ->where('parent_user_id', $parentId)
                ->where('user_id', $childId)
                ->exists();
        } elseif (in_array($actor->role, ['child', 'member'], true)) {
            $childId = $actor->id;
            $parentId = FamilyMember::query()
                ->where('user_id', $actor->id)
                ->value('parent_user_id');

            if (! $parentId) {
                return response()->json(['message' => 'Borrower does not belong to a parent account.'], 422);
            }

            if ((int) $validated['child_user_id'] !== $actor->id) {
                return response()->json(['message' => 'Borrower can only request a loan for own account.'], 403);
            }

            $isChildOfParent = true;
        } else {
            Log::error('LOANS_STORE_FORBIDDEN: Invalid role for loan creation.', [
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
            ]);
            return response()->json(['message' => 'Invalid role for loan creation.'], 403);
        }
        if (! $isChildOfParent) {
            return response()->json(['message' => 'Selected user does not belong to your family members.'], 422);
        }

        $amount = (int) $validated['amount'];
        $interestMode = $validated['has_interest'] ? ($validated['interest_mode'] ?? 'percent') : 'percent';
        $fixedInterestAmount = $validated['has_interest'] && $interestMode === 'fixed'
            ? (int) ($validated['fixed_interest_amount'] ?? 0)
            : 0;
        $rate = $validated['has_interest']
            ? (float) ($validated['annual_interest_rate'] ?? 0)
            : 0.0;

        if ($interestMode === 'fixed' && $amount > 0) {
            $rate = ($fixedInterestAmount / $amount) * 100;
        }

        $total = (int) round($amount * (1 + ($rate / 100)));
        $installmentAmount = (int) ceil($total / (int) $validated['installments_count']);
        $loanData = [
            'child_user_id' => $childId,
            'amount' => $amount,
            'reason' => $validated['reason'] ?? null,
            'due_date' => $validated['due_date'],
            'installments_count' => (int) $validated['installments_count'],
            'installment_frequency' => $validated['installment_frequency'],
            'has_interest' => (bool) $validated['has_interest'],
            'interest_mode' => $interestMode,
            'annual_interest_rate' => $rate,
            'fixed_interest_amount' => $fixedInterestAmount,
            'total_amount' => $total,
            'installment_amount' => $installmentAmount,
        ];

        if (in_array($actor->role, ['child', 'member'], true)) {
            $loan = DB::transaction(function () use ($actor, $parentId, $loanData): Loan {
                $loan = Loan::create([
                    'parent_user_id' => $parentId,
                    ...$loanData,
                    'status' => 'pending',
                    'requested_by_user_id' => $actor->id,
                ])->load('child:id,name,username');
                $this->loanPayments->ensurePaymentsForLoan($loan);

                $amountText = $this->notifications->money((int) $loan->amount * 100);
                $this->notifications->recordForMember(
                    $actor->id,
                    'solicitud',
                    'prestamos',
                    'Solicitaste un prestamo por '.$amountText.'.'
                );
                $this->notifications->recordForParent(
                    $parentId,
                    'solicitud',
                    'prestamos',
                    $actor->name.' solicito un prestamo por '.$amountText.'.'
                );

                return $loan;
            });

            return response()->json([
                'message' => 'Loan request submitted and pending approval.',
                'loan' => $this->presentLoan($loan),
            ], 201);
        }

        if ($actor->role === 'parent') {
            try {
                $payload = DB::transaction(function () use ($actor, $parentId, $loanData) {
                $loan = Loan::create([
                    'parent_user_id' => $parentId,
                    ...$loanData,
                    'status' => 'offered',
                    'requested_by_user_id' => $parentId,
                ]);
                $this->loanPayments->ensurePaymentsForLoan($loan);
                $loanAmountCents = (int) $loan->amount * 100;

                $balance = Balance::query()
                    ->where('user_id', $parentId)
                    ->lockForUpdate()
                    ->first();

                if (! $balance) {
                    $balance = Balance::create([
                        'user_id' => $parentId,
                        'amount' => 0,
                    ]);
                    $balance = Balance::query()
                        ->where('user_id', $parentId)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $parentMoneyUsedBefore = BalanceHelper::parentMoneyUsedCents($actor);
                $parentMoneyUsedAfter = $parentMoneyUsedBefore + $loanAmountCents;

                $balance->movements()->create([
                    'amount_added' => $loanAmountCents,
                    'movement_type' => 'loan_reserve',
                    'note' => 'Loan offered while waiting for child response',
                    'resulting_balance' => $parentMoneyUsedAfter,
                ]);

                $loan = $loan->fresh(['child:id,name,username']);
                $amountText = $this->notifications->money((int) $loan->amount * 100);
                $childName = $loan->child?->name ?? 'un integrante';

                $this->notifications->recordForParent(
                    $parentId,
                    'oferta',
                    'prestamos',
                    'Ofreciste un prestamo por '.$amountText.' a '.$childName.'.'
                );
                $this->notifications->recordForMember(
                    $loan->child_user_id,
                    'oferta',
                    'prestamos',
                    'Recibiste una oferta de prestamo por '.$amountText.'.'
                );

                return [
                    'loan' => $loan,
                    'remaining_balance' => $parentMoneyUsedAfter,
                ];
                });
            } catch (\RuntimeException $exception) {
                if ($exception->getMessage() === 'INSUFFICIENT_BALANCE') {
                    return response()->json(['message' => 'No se pudo crear este prestamo.'], 422);
                }

                throw $exception;
            }

            return response()->json([
                'message' => 'Loan offer created successfully.',
                'loan' => $this->presentLoan($payload['loan']),
                'remaining_balance' => $payload['remaining_balance'],
                'remaining_balance_display' => BalanceHelper::displayCents((int) $payload['remaining_balance']),
            ], 201);
        }

        $loan = Loan::create([
            'parent_user_id' => $parentId,
            'child_user_id' => $childId,
            'amount' => $amount,
            'reason' => $validated['reason'] ?? null,
            'due_date' => $validated['due_date'],
            'installments_count' => (int) $validated['installments_count'],
            'installment_frequency' => $validated['installment_frequency'],
            'has_interest' => (bool) $validated['has_interest'],
            'interest_mode' => $interestMode,
            'annual_interest_rate' => $rate,
            'fixed_interest_amount' => $fixedInterestAmount,
            'status' => 'pending',
            'requested_by_user_id' => $actor->id,
            'total_amount' => $total,
            'installment_amount' => $installmentAmount,
        ])->load('child:id,name,username');

        return response()->json([
            'message' => 'Loan request submitted and pending approval.',
            'loan' => $this->presentLoan($loan),
        ], 201);
    }

    public function approve(Request $request, Loan $loan): JsonResponse
    {
        $parent = $request->user();
        if ($parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent can approve loans.'], 403);
        }
        if ($loan->parent_user_id !== $parent->id) {
            return response()->json(['message' => 'Loan does not belong to your account.'], 403);
        }
        if ($loan->status !== 'pending') {
            return response()->json(['message' => 'Loan is not pending.'], 422);
        }

        try {
            $payload = DB::transaction(function () use ($parent, $loan) {
                $balance = Balance::query()
                    ->where('user_id', $parent->id)
                    ->lockForUpdate()
                    ->first();

                if (! $balance) {
                    $balance = Balance::create([
                        'user_id' => $parent->id,
                        'amount' => 0,
                    ]);
                    $balance = Balance::query()
                        ->where('user_id', $parent->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $loanAmountCents = (int) $loan->amount * 100;

                $parentMoneyUsedBefore = BalanceHelper::parentMoneyUsedCents($parent);
                $parentMoneyUsedAfter = $parentMoneyUsedBefore + $loanAmountCents;

                $balance->movements()->create([
                    'amount_added' => $loanAmountCents,
                    'movement_type' => 'loan_debit',
                    'note' => 'Loan approval',
                    'resulting_balance' => $parentMoneyUsedAfter,
                ]);

                $childBalance = Balance::query()
                    ->where('user_id', $loan->child_user_id)
                    ->lockForUpdate()
                    ->first();

                if (! $childBalance) {
                    $childBalance = Balance::create([
                        'user_id' => $loan->child_user_id,
                        'amount' => 0,
                    ]);
                    $childBalance = Balance::query()
                        ->where('user_id', $loan->child_user_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $childBalance->amount = (int) $childBalance->amount + $loanAmountCents;
                $childBalance->save();

                $childBalance->movements()->create([
                    'amount_added' => $loanAmountCents,
                    'movement_type' => 'loan_credit',
                    'note' => 'Loan approved by parent',
                    'resulting_balance' => $childBalance->amount,
                ]);

                $loan->status = 'approved';
                $loan->rejection_reason = null;
                $loan->responded_at = now();
                $loan->save();
                $this->loanPayments->ensurePaymentsForLoan($loan);
                $this->loanPayments->syncStatusesForLoan($loan);

                $loan = $loan->fresh(['child:id,name,username']);
                $amountText = $this->notifications->money((int) $loan->amount * 100);
                $childName = $loan->child?->name ?? 'un integrante';

                $this->notifications->recordForParent(
                    $parent->id,
                    'aprobacion',
                    'prestamos',
                    'Aprobaste un prestamo por '.$amountText.' para '.$childName.'.'
                );
                $this->notifications->recordForMember(
                    $loan->child_user_id,
                    'aprobacion',
                    'prestamos',
                    'Tu prestamo por '.$amountText.' fue aprobado.'
                );

                return [
                    'loan' => $loan,
                    'remaining_balance' => $parentMoneyUsedAfter,
                    'child_balance_cents' => (int) $childBalance->amount,
                ];
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['message' => 'No se pudo aprobar este prestamo.'], 422);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Loan approved.',
            'loan' => $this->presentLoan($payload['loan']),
            'remaining_balance' => $payload['remaining_balance'],
            'remaining_balance_display' => BalanceHelper::displayCents((int) $payload['remaining_balance']),
            'child_balance_cents' => $payload['child_balance_cents'],
            'child_balance_display' => BalanceHelper::displayCents((int) $payload['child_balance_cents']),
        ]);
    }

    public function respondToOffer(Request $request, Loan $loan): JsonResponse
    {
        $child = $request->user();
        if (! in_array($child->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member can respond to an offer.'], 403);
        }
        if ($loan->child_user_id !== $child->id) {
            return response()->json(['message' => 'Loan does not belong to your account.'], 403);
        }
        if ($loan->status !== 'offered') {
            return response()->json(['message' => 'Loan is not in offered status.'], 422);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:accept,reject'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['action'] === 'reject' && empty(trim((string) ($validated['reason'] ?? '')))) {
            return response()->json(['message' => 'Rejection reason is required.'], 422);
        }

        $result = DB::transaction(function () use ($child, $loan, $validated) {
            $parentBalance = Balance::query()
                ->where('user_id', $loan->parent_user_id)
                ->lockForUpdate()
                ->first();

            $childBalance = Balance::query()
                ->where('user_id', $child->id)
                ->lockForUpdate()
                ->first();

            if (! $childBalance) {
                $childBalance = Balance::create([
                    'user_id' => $child->id,
                    'amount' => 0,
                ]);
                $childBalance = Balance::query()
                    ->where('user_id', $child->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($validated['action'] === 'accept') {
                if (! $parentBalance) {
                    throw new \RuntimeException('PARENT_BALANCE_NOT_RESERVED');
                }

                $loanAmountCents = (int) $loan->amount * 100;

                $childBalance->amount = (int) $childBalance->amount + $loanAmountCents;
                $childBalance->save();

                $childBalance->movements()->create([
                    'amount_added' => $loanAmountCents,
                    'movement_type' => 'loan_credit',
                    'note' => 'Loan accepted by child',
                    'resulting_balance' => $childBalance->amount,
                ]);

                $loan->status = 'approved';
                $loan->rejection_reason = null;
                $this->loanPayments->ensurePaymentsForLoan($loan);
                $this->loanPayments->syncStatusesForLoan($loan);
            } else {
                if ($parentBalance) {
                    $loanAmountCents = (int) $loan->amount * 100;
                    $parentUser = $loan->parent()->firstOrFail();
                    $parentMoneyUsedBefore = BalanceHelper::parentMoneyUsedCents($parentUser);
                    $parentMoneyUsedAfter = max($parentMoneyUsedBefore - $loanAmountCents, 0);

                    $parentBalance->movements()->create([
                        'amount_added' => $loanAmountCents,
                        'movement_type' => 'loan_refund',
                        'note' => 'Loan rejected by child',
                        'resulting_balance' => $parentMoneyUsedAfter,
                    ]);
                }

                $loan->status = 'rejected';
                $loan->rejection_reason = trim((string) $validated['reason']);
            }

            $loan->responded_at = now();
            $loan->save();

            $loan = $loan->fresh(['parent:id,name,email', 'child:id,name,username']);
            $amountText = $this->notifications->money((int) $loan->amount * 100);

            if ($validated['action'] === 'accept') {
                $this->notifications->recordForMember(
                    $child->id,
                    'aceptacion',
                    'prestamos',
                    'Aceptaste un prestamo por '.$amountText.'.'
                );
                $this->notifications->recordForParent(
                    $loan->parent_user_id,
                    'aceptacion',
                    'prestamos',
                    $child->name.' acepto un prestamo por '.$amountText.'.'
                );
            } else {
                $this->notifications->recordForMember(
                    $child->id,
                    'rechazo',
                    'prestamos',
                    'Rechazaste un prestamo por '.$amountText.'.'
                );
                $this->notifications->recordForParent(
                    $loan->parent_user_id,
                    'rechazo',
                    'prestamos',
                    $child->name.' rechazo un prestamo por '.$amountText.'.'
                );
            }

            return [
                'loan' => $loan,
                'member_balance_cents' => (int) $childBalance->amount,
            ];
        });

        return response()->json([
            'message' => $validated['action'] === 'accept' ? 'Loan accepted.' : 'Loan rejected.',
            'loan' => $this->presentLoan($result['loan']->loadMissing(['payments' => fn ($query) => $query->orderBy('installment_number')])),
            'member_balance_cents' => $result['member_balance_cents'],
            'member_balance_display' => BalanceHelper::displayCents((int) $result['member_balance_cents']),
        ]);
    }

    public function pay(Request $request, LoanPayment $loanPayment): JsonResponse
    {
        $member = $request->user();

        if (! $member || ! in_array($member->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Solo los integrantes pueden pagar prestamos.'], 403);
        }

        try {
            $result = $this->loanPayments->payInstallment($loanPayment, $member->id);
        } catch (\RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'PAYMENT_FORBIDDEN' => response()->json(['message' => 'Este pago no pertenece a tu prestamo.'], 403),
                'LOAN_NOT_ACTIVE' => response()->json(['message' => 'Este prestamo no esta activo para recibir pagos.'], 422),
                'PAYMENT_ALREADY_PAID' => response()->json(['message' => 'Este pago ya fue cubierto.'], 422),
                'PAYMENT_NOT_DUE' => response()->json(['message' => 'Este pago aun no se puede adelantar.'], 422),
                'INSUFFICIENT_BALANCE' => response()->json(['message' => 'No tienes saldo suficiente para cubrir este pago.'], 422),
                default => throw $exception,
            };
        }

        /** @var Loan $loan */
        $loan = $result['loan'];
        /** @var LoanPayment $payment */
        $payment = $result['payment'];
        $amountText = $this->notifications->money((int) $payment->total_amount_cents);
        $childName = $loan->child?->name ?? 'Tu integrante';

        $this->notifications->recordForMember(
            $member->id,
            'pago',
            'prestamos',
            'Pagaste la cuota '.$payment->installment_number.' por '.$amountText.'.'
        );
        $this->notifications->recordForParent(
            (int) $loan->parent_user_id,
            'pago',
            'prestamos',
            $childName.' pago la cuota '.$payment->installment_number.' por '.$amountText.'.'
        );

        if ($loan->status === 'paid') {
            $this->notifications->recordForMember(
                $member->id,
                'cierre',
                'prestamos',
                'Terminaste de pagar este prestamo.'
            );
            $this->notifications->recordForParent(
                (int) $loan->parent_user_id,
                'cierre',
                'prestamos',
                $childName.' termino de pagar su prestamo.'
            );
        }

        $unlockedBadges = $this->achievements->unlockFirstLoanPayment($member->id, [
            'loan_id' => $loan->id,
            'loan_payment_id' => $payment->id,
            'installment_number' => $payment->installment_number,
        ]);

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'payment' => $payment,
            'loan' => $this->presentLoan($loan),
            'member_balance_cents' => $result['member_balance_cents'],
            'new_balance_cents' => $result['member_balance_cents'],
            'member_balance_display' => BalanceHelper::displayCents((int) $result['member_balance_cents']),
            'new_balance_display' => BalanceHelper::displayCents((int) $result['member_balance_cents']),
            'parent_balance_cents' => $result['parent_balance_cents'],
            'parent_balance_display' => BalanceHelper::displayCents((int) $result['parent_balance_cents']),
            'unlocked_badges' => $unlockedBadges,
            'domus_points' => $this->pointsAccount->snapshotForChild((int) $member->id),
        ]);
    }

    private function presentLoan(Loan $loan): Loan
    {
        $summary = $this->loanPayments->buildSummary($loan);
        $loan->setAttribute('payment_summary', $summary);

        return $loan;
    }
}
