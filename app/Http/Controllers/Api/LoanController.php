<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Services\DomusAchievementService;
use App\Services\DomusNotificationService;
use App\Services\DomusPointsAccountService;
use App\Services\LoanPaymentService;
use App\Support\BalanceHelper;
use App\Support\LoanAmountGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
        $this->markLoanReadDiagnosticStage($request, 'index.resolve_authenticated_user');
        $user = $request->user();
        if (! $user) {
            Log::error('LOANS_INDEX_FAILED: No authenticated user from token.', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthenticated context in /loans.'], 401);
        }

        $this->markLoanReadDiagnosticStage($request, 'index.validate_role', [
            'user_id' => $user->id,
            'user_role' => $user->role,
        ]);
        if (! in_array($user->role, ['parent', 'child', 'member'], true)) {
            Log::error('LOANS_INDEX_FAILED: Invalid role.', [
                'user_id' => $user->id,
                'role' => $user->role,
            ]);
            return response()->json(['message' => 'Invalid role for loans listing.'], 422);
        }

        $this->markLoanReadDiagnosticStage($request, 'index.query_loans');
        if ($user->role === 'parent') {
            $loans = Loan::query()
                ->with(['child:id,name,username'])
                ->where('parent_user_id', $user->id)
                ->latest()
                ->get();
        } else {
            $loans = Loan::query()
                ->with(['parent:id,name,email,role'])
                ->where('child_user_id', $user->id)
                ->latest()
                ->get();
        }

        $this->markLoanReadDiagnosticStage($request, 'index.query_loans.completed', [
            'loan_count' => $loans->count(),
            'loan_ids_sample' => array_slice($loans->modelKeys(), 0, 50),
            'loan_id' => null,
        ]);

        $statusSyncDate = now()->startOfDay();
        foreach ($loans as $loan) {
            $this->markLoanReadDiagnosticStage($request, 'index.sync_payment_status', [
                'loan_id' => $loan->id,
            ]);
            $this->loanPayments->syncStatusesForLoan($loan, $statusSyncDate);
        }

        $this->markLoanReadDiagnosticStage($request, 'index.load_payments', ['loan_id' => null]);
        $loans->load(['payments' => fn ($query) => $query->orderBy('installment_number')]);

        foreach ($loans as $loan) {
            $this->markLoanReadDiagnosticStage($request, 'index.refresh_loan_status', [
                'loan_id' => $loan->id,
            ]);
            $this->loanPayments->refreshLoanStatus($loan);
        }

        $presentedLoans = $loans->map(function (Loan $loan) use ($request): Loan {
            $this->markLoanReadDiagnosticStage($request, 'index.serialize_loan', [
                'loan_id' => $loan->id,
                'loan_status' => $loan->status,
                'loan_requested_months' => $loan->requested_months,
                'loan_due_date' => $loan->getRawOriginal('due_date'),
                'loan_proposed_due_date' => $loan->getRawOriginal('proposed_due_date'),
                'loan_initial_due_date' => $loan->getRawOriginal('initial_due_date'),
            ]);

            return $this->presentLoan($loan);
        })->values();

        $this->markLoanReadDiagnosticStage($request, 'index.build_response', ['loan_id' => null]);

        return response()->json([
            'loans' => $presentedLoans,
        ]);
    }

    public function waiting(Request $request): JsonResponse
    {
        $this->markLoanReadDiagnosticStage($request, 'waiting.resolve_authenticated_user');
        $user = $request->user();
        if (! $user) {
            Log::error('LOANS_WAITING_FAILED: No authenticated user from token.', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthenticated context in /loans/waiting.'], 401);
        }

        $this->markLoanReadDiagnosticStage($request, 'waiting.validate_role', [
            'user_id' => $user->id,
            'user_role' => $user->role,
        ]);
        if (! in_array($user->role, ['parent', 'child', 'member'], true)) {
            Log::error('LOANS_WAITING_FAILED: Invalid role.', [
                'user_id' => $user->id,
                'role' => $user->role,
            ]);
            return response()->json(['message' => 'Invalid role for waiting loans listing.'], 422);
        }

        $this->markLoanReadDiagnosticStage($request, 'waiting.query_loans');
        $loans = Loan::query()
            ->with($user->role === 'parent'
                ? ['child:id,name,username']
                : ['parent:id,name,email,role'])
            ->when(
                $user->role === 'parent',
                fn ($query) => $query->where('parent_user_id', $user->id)->where('status', 'pending'),
                fn ($query) => $query->where('child_user_id', $user->id)->whereIn('status', ['pending', 'offered'])
            )
            ->latest()
            ->get();

        $this->markLoanReadDiagnosticStage($request, 'waiting.query_loans.completed', [
            'loan_count' => $loans->count(),
            'loan_ids_sample' => array_slice($loans->modelKeys(), 0, 50),
            'loan_id' => null,
        ]);

        $statusSyncDate = now()->startOfDay();
        foreach ($loans as $loan) {
            $this->markLoanReadDiagnosticStage($request, 'waiting.sync_payment_status', [
                'loan_id' => $loan->id,
            ]);
            $this->loanPayments->syncStatusesForLoan($loan, $statusSyncDate);
        }

        $this->markLoanReadDiagnosticStage($request, 'waiting.load_payments', ['loan_id' => null]);
        $loans->load(['payments' => fn ($query) => $query->orderBy('installment_number')]);

        $presentedLoans = $loans->map(function (Loan $loan) use ($request): Loan {
            $this->markLoanReadDiagnosticStage($request, 'waiting.serialize_loan', [
                'loan_id' => $loan->id,
                'loan_status' => $loan->status,
                'loan_requested_months' => $loan->requested_months,
                'loan_due_date' => $loan->getRawOriginal('due_date'),
                'loan_proposed_due_date' => $loan->getRawOriginal('proposed_due_date'),
                'loan_initial_due_date' => $loan->getRawOriginal('initial_due_date'),
            ]);

            return $this->presentLoan($loan);
        })->values();

        $this->markLoanReadDiagnosticStage($request, 'waiting.build_response', ['loan_id' => null]);

        return response()->json([
            'loans' => $presentedLoans,
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
        if (! $actor || ! in_array($actor->role, ['parent', 'child', 'member'], true)) {
            return response()->json(['message' => 'Rol sin permiso para crear prestamos.'], 403);
        }

        if (in_array($actor->role, ['child', 'member'], true)) {
            $unexpected = array_diff(array_keys($request->all()), ['amount', 'reason', 'months']);
            if ($unexpected !== []) {
                return response()->json([
                    'message' => 'Una solicitud de hijo solo puede incluir monto, motivo y meses.',
                    'errors' => ['request' => ['No envies campos de condiciones financieras.']],
                ], 422);
            }

            $validated = $request->validate([
                'amount' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,2})?$/', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
                'reason' => [
                    'required',
                    'string',
                    'max:120',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! is_string($value) || trim($value) === '' || strip_tags($value) !== $value || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
                            $fail('El motivo debe ser texto limpio de hasta 120 caracteres.');
                        }
                    },
                ],
                'months' => ['required', 'integer', 'in:1,3,6,12'],
            ], [
                'months.in' => 'Los meses permitidos son 1, 3, 6 y 12.',
            ]);

            $parentId = FamilyMember::query()
                ->where('user_id', $actor->id)
                ->whereHas('parent', fn ($query) => $query->where('role', 'parent'))
                ->value('parent_user_id');
            if (! $parentId) {
                return response()->json(['message' => 'No existe un padre asociado a esta cuenta.'], 422);
            }

            $amountCents = $this->moneyInputToCents($validated['amount']);
            $months = (int) $validated['months'];
            $dueDate = now()->startOfDay()->addMonthNoOverflow()->toDateString();
            $terms = $this->buildLoanTerms($amountCents, $months, 'monthly', $dueDate, false, 'percent', 0.0, 0);

            $loan = DB::transaction(function () use ($actor, $parentId, $validated, $months, $terms): array {
                $reason = trim($validated['reason']);
                $loan = Loan::create([
                    'parent_user_id' => $parentId,
                    'child_user_id' => $actor->id,
                    ...$terms,
                    'reason' => $reason,
                    'status' => 'pending',
                    'requested_by_user_id' => $actor->id,
                    'requested_amount' => $terms['amount'],
                    'requested_reason' => $reason,
                    'requested_months' => $months,
                    'initial_due_date' => $terms['due_date'],
                    'initial_installments_count' => $terms['installments_count'],
                    'initial_installment_frequency' => $terms['installment_frequency'],
                    'initial_has_interest' => false,
                    'initial_interest_mode' => 'percent',
                    'initial_annual_interest_rate' => 0,
                    'initial_fixed_interest_amount' => 0,
                    'initial_total_amount' => $terms['total_amount'],
                    'initial_installment_amount' => $terms['installment_amount'],
                ]);

                $loan = $this->reloadAndVerifyLoanAmounts($loan, $terms)
                    ->load('child:id,name,username');
                $amountText = $this->notifications->money($terms['amount_cents']);
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

                return $this->presentLoan($loan)->toArray();
            });

            return response()->json([
                'message' => 'Solicitud de prestamo enviada y pendiente de revision.',
                'loan' => $loan,
            ], 201);
        }

        // Parent-created offers keep the existing endpoint and request shape.
        $validated = $request->validate([
            'child_user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,2})?$/', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'reason' => ['nullable', 'string', 'max:120'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_frequency' => ['required', 'in:weekly,biweekly,monthly'],
            'has_interest' => ['required', 'boolean'],
            'interest_mode' => ['required', 'in:percent,fixed'],
            'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'fixed_interest_amount' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,2})?$/', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
        ]);

        $childId = (int) $validated['child_user_id'];
        if (! FamilyMember::query()
            ->where('parent_user_id', $actor->id)
            ->where('user_id', $childId)
            ->whereHas('user', fn ($query) => $query->whereIn('role', ['child', 'member']))
            ->exists()) {
            return response()->json(['message' => 'El usuario seleccionado no pertenece a tu familia.'], 422);
        }

        try {
            $terms = $this->buildLoanTermsFromParentInput($validated);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['interest_mode' => [$exception->getMessage()]],
            ], 422);
        }

        $loanData = [
            ...$terms,
            'child_user_id' => $childId,
            'reason' => isset($validated['reason']) ? trim($validated['reason']) : null,
        ];

        $payload = DB::transaction(function () use ($actor, $loanData): array {
            $balance = $this->lockOrCreateBalance((int) $actor->id);
            $loan = Loan::create([
                'parent_user_id' => $actor->id,
                ...$loanData,
                'status' => 'offered',
                'requested_by_user_id' => $actor->id,
                'proposed_due_date' => $loanData['due_date'],
                'reserved_amount_cents' => $loanData['amount_cents'],
                'funds_reserved_at' => now(),
            ]);
            $loan = $this->reloadAndVerifyLoanAmounts($loan, $loanData);
            $parentMoneyUsedAfter = BalanceHelper::parentMoneyUsedCents($actor) + $loanData['amount_cents'];
            $balance->movements()->create([
                'amount_added' => $loanData['amount_cents'],
                'movement_type' => 'loan_reserve',
                'note' => 'Loan offered while waiting for child response',
                'resulting_balance' => $parentMoneyUsedAfter,
            ]);

            $loan = $loan->fresh(['child:id,name,username']);
            $amountText = $this->notifications->money($loanData['amount_cents']);
            $childName = $loan->child?->name ?? 'un integrante';
            $this->notifications->recordForParent(
                $actor->id,
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
                'loan' => $this->presentLoan($loan)->toArray(),
                'remaining_balance' => $parentMoneyUsedAfter,
            ];
        });

        return response()->json([
            'message' => 'Oferta de prestamo creada correctamente.',
            'loan' => $payload['loan'],
            'remaining_balance' => $payload['remaining_balance'],
            'remaining_balance_display' => BalanceHelper::displayCents((int) $payload['remaining_balance']),
        ], 201);
    }

    public function approve(Request $request, Loan $loan): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Solo el padre puede aprobar solicitudes.'], 403);
        }
        if ($loan->parent_user_id !== $parent->id) {
            return response()->json(['message' => 'Esta solicitud no pertenece a tu familia.'], 403);
        }
        if ($request->all() !== []) {
            return response()->json(['message' => 'La aprobacion directa no recibe condiciones; usa la revision para proponer cambios.'], 422);
        }

        try {
            $result = DB::transaction(function () use ($parent, $loan): array {
                $lockedLoan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
                if ((int) $lockedLoan->parent_user_id !== (int) $parent->id) {
                    throw new \RuntimeException('LOAN_FORBIDDEN');
                }

                if (in_array($lockedLoan->status, ['approved', 'paid'], true) && $lockedLoan->funds_disbursed_at) {
                    return $this->approvedLoanResponseData($lockedLoan);
                }
                if ($lockedLoan->status !== 'pending') {
                    throw new \RuntimeException('LOAN_NOT_PENDING');
                }

                return $this->disbursePendingLoan($parent, $lockedLoan);
            });
        } catch (\RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'LOAN_FORBIDDEN' => response()->json(['message' => 'Esta solicitud no pertenece a tu familia.'], 403),
                'LOAN_NOT_PENDING' => response()->json(['message' => 'La solicitud ya no esta pendiente.'], 422),
                default => throw $exception,
            };
        }

        return $this->loanActionResponse($result, 'Solicitud aprobada y prestamo depositado.');
    }

    public function review(Request $request, Loan $loan): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Solo el padre puede revisar solicitudes.'], 403);
        }
        if ($loan->parent_user_id !== $parent->id) {
            return response()->json(['message' => 'Esta solicitud no pertenece a tu familia.'], 403);
        }

        $unexpected = array_diff(array_keys($request->all()), [
            'amount',
            'due_date',
            'installments_count',
            'installment_frequency',
            'has_interest',
            'interest_mode',
            'annual_interest_rate',
            'fixed_interest_amount',
        ]);
        if ($unexpected !== []) {
            return response()->json(['message' => 'La revision contiene campos no permitidos.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,2})?$/', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_frequency' => ['required', 'in:weekly,biweekly,monthly'],
            'has_interest' => ['required', 'boolean'],
            'interest_mode' => ['required', 'in:percent,fixed'],
            'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'fixed_interest_amount' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,2})?$/', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
        ]);

        try {
            $terms = $this->buildLoanTermsFromParentInput($validated);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['interest_mode' => [$exception->getMessage()]],
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($parent, $loan, $terms): array {
                $lockedLoan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
                if ((int) $lockedLoan->parent_user_id !== (int) $parent->id) {
                    throw new \RuntimeException('LOAN_FORBIDDEN');
                }

                if ($lockedLoan->status === 'offered' && $this->loanHasSameTerms($lockedLoan, $terms)) {
                    return $this->proposalResponseData($lockedLoan);
                }
                if (in_array($lockedLoan->status, ['approved', 'paid'], true)
                    && $lockedLoan->funds_disbursed_at
                    && $this->loanHasSameTerms($lockedLoan, $terms)) {
                    return $this->approvedLoanResponseData($lockedLoan);
                }
                if ($lockedLoan->status !== 'pending') {
                    throw new \RuntimeException('LOAN_NOT_PENDING');
                }
                if ($this->loanHasSameTerms($lockedLoan, $terms)) {
                    return $this->disbursePendingLoan($parent, $lockedLoan);
                }

                $balance = $this->lockOrCreateBalance((int) $parent->id);
                $reservedCents = $terms['amount_cents'];
                $lockedLoan->fill($this->persistedLoanTerms($terms));
                $lockedLoan->proposed_due_date = $terms['due_date'];
                $lockedLoan->status = 'offered';
                $lockedLoan->parent_reviewed_at = now();
                $lockedLoan->reserved_amount_cents = $reservedCents;
                $lockedLoan->funds_reserved_at = now();
                $lockedLoan->funds_released_at = null;
                $lockedLoan->funds_disbursed_at = null;
                $lockedLoan->responded_at = null;
                $lockedLoan->rejection_reason = null;
                $lockedLoan->save();

                $parentMoneyUsedAfter = BalanceHelper::parentMoneyUsedCents($parent) + $reservedCents;
                $balance->movements()->create([
                    'amount_added' => $reservedCents,
                    'movement_type' => 'loan_reserve',
                    'note' => 'Loan terms proposed for child request',
                    'resulting_balance' => $parentMoneyUsedAfter,
                ]);

                $lockedLoan->load(['parent:id,name,email,role', 'child:id,name,username']);
                $amountText = $this->notifications->money($reservedCents);
                $this->notifications->recordForParent(
                    $parent->id,
                    'oferta',
                    'prestamos',
                    'Propusiste condiciones para un prestamo por '.$amountText.' a '.($lockedLoan->child?->name ?? 'un integrante').'.'
                );
                $this->notifications->recordForMember(
                    $lockedLoan->child_user_id,
                    'oferta',
                    'prestamos',
                    'Tu padre propuso condiciones para tu solicitud de prestamo.'
                );

                return $this->proposalResponseData($lockedLoan, $parentMoneyUsedAfter);
            });
        } catch (\RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'LOAN_FORBIDDEN' => response()->json(['message' => 'Esta solicitud no pertenece a tu familia.'], 403),
                'LOAN_NOT_PENDING' => response()->json(['message' => 'La solicitud ya no esta pendiente.'], 422),
                default => throw $exception,
            };
        }

        if (in_array($result['loan']['status'] ?? null, ['approved', 'paid'], true)) {
            return $this->loanActionResponse($result, 'Solicitud aprobada y prestamo depositado.');
        }

        return response()->json([
            'message' => 'Propuesta enviada al hijo para su confirmacion.',
            'loan' => $result['loan'],
            'remaining_balance' => $result['remaining_balance'],
            'remaining_balance_display' => BalanceHelper::displayCents((int) $result['remaining_balance']),
        ]);
    }

    public function reject(Request $request, Loan $loan): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Solo el padre puede rechazar solicitudes.'], 403);
        }
        if ($loan->parent_user_id !== $parent->id) {
            return response()->json(['message' => 'Esta solicitud no pertenece a tu familia.'], 403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        if (array_diff(array_keys($request->all()), ['reason']) !== []) {
            return response()->json(['message' => 'El rechazo solo acepta un motivo opcional.'], 422);
        }

        try {
            $result = DB::transaction(function () use ($parent, $loan, $validated): array {
                $lockedLoan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
                if ((int) $lockedLoan->parent_user_id !== (int) $parent->id) {
                    throw new \RuntimeException('LOAN_FORBIDDEN');
                }
                if ($lockedLoan->status === 'rejected' && ! $lockedLoan->funds_reserved_at) {
                    return $this->approvedLoanResponseData($lockedLoan);
                }
                if ($lockedLoan->status !== 'pending') {
                    throw new \RuntimeException('LOAN_NOT_PENDING');
                }

                $lockedLoan->status = 'rejected';
                $lockedLoan->rejection_reason = isset($validated['reason']) ? trim($validated['reason']) : null;
                $lockedLoan->parent_reviewed_at = now();
                $lockedLoan->responded_at = now();
                $lockedLoan->save();

                $lockedLoan->load(['parent:id,name,email,role', 'child:id,name,username']);
                $this->notifications->recordForParent(
                    $parent->id,
                    'rechazo',
                    'prestamos',
                    'Rechazaste la solicitud de prestamo de '.($lockedLoan->child?->name ?? 'un integrante').'.'
                );
                $this->notifications->recordForMember(
                    $lockedLoan->child_user_id,
                    'rechazo',
                    'prestamos',
                    'Tu solicitud de prestamo fue rechazada por tu padre.'
                );

                return $this->approvedLoanResponseData($lockedLoan);
            });
        } catch (\RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'LOAN_FORBIDDEN' => response()->json(['message' => 'Esta solicitud no pertenece a tu familia.'], 403),
                'LOAN_NOT_PENDING' => response()->json(['message' => 'La solicitud ya no esta pendiente.'], 422),
                default => throw $exception,
            };
        }

        return response()->json([
            'message' => 'Solicitud rechazada.',
            'loan' => $result['loan'],
        ]);
    }

    public function respondToOffer(Request $request, Loan $loan): JsonResponse
    {
        $child = $request->user();
        if (! $child || ! in_array($child->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Solo el hijo puede responder a una oferta.'], 403);
        }
        if ($loan->child_user_id !== $child->id) {
            return response()->json(['message' => 'Esta oferta no pertenece a tu cuenta.'], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:accept,reject'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        if (array_diff(array_keys($request->all()), ['action', 'reason']) !== []) {
            return response()->json(['message' => 'La respuesta solo acepta action y un motivo de rechazo opcional.'], 422);
        }
        if ($validated['action'] === 'reject' && empty(trim((string) ($validated['reason'] ?? '')))) {
            return response()->json(['message' => 'Indica el motivo del rechazo.'], 422);
        }

        try {
            $result = DB::transaction(function () use ($child, $loan, $validated): array {
                $lockedLoan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
                if ((int) $lockedLoan->child_user_id !== (int) $child->id) {
                    throw new \RuntimeException('LOAN_FORBIDDEN');
                }

                if ($validated['action'] === 'accept'
                    && in_array($lockedLoan->status, ['approved', 'paid'], true)
                    && $lockedLoan->funds_disbursed_at) {
                    return $this->approvedLoanResponseData($lockedLoan);
                }
                if ($validated['action'] === 'reject'
                    && $lockedLoan->status === 'rejected'
                    && $lockedLoan->funds_released_at) {
                    return $this->approvedLoanResponseData($lockedLoan);
                }
                if ($lockedLoan->status !== 'offered') {
                    throw new \RuntimeException('LOAN_NOT_OFFERED');
                }

                if (! $lockedLoan->funds_reserved_at || ! $lockedLoan->reserved_amount_cents) {
                    throw new \RuntimeException('RESERVATION_NOT_FOUND');
                }
                $parentBalance = $this->lockOrCreateBalance((int) $lockedLoan->parent_user_id);
                $childBalance = $this->lockOrCreateBalance((int) $child->id);
                $reservedCents = (int) $lockedLoan->reserved_amount_cents;

                if ($validated['action'] === 'accept') {
                    $loanAmountCents = BalanceHelper::parseMoneyToCents((string) $lockedLoan->amount);
                    if ($loanAmountCents !== $reservedCents) {
                        throw new \RuntimeException('RESERVATION_AMOUNT_MISMATCH');
                    }

                    $lockedLoan->due_date = $this->effectiveDueDateForActivation($lockedLoan);

                    $childBalance->amount = (int) $childBalance->amount + $loanAmountCents;
                    $childBalance->save();
                    $childBalance->movements()->create([
                        'amount_added' => $loanAmountCents,
                        'movement_type' => 'loan_credit',
                        'note' => 'Loan accepted by child',
                        'resulting_balance' => $childBalance->amount,
                    ]);

                    $lockedLoan->status = 'approved';
                    $lockedLoan->rejection_reason = null;
                    $lockedLoan->funds_disbursed_at = now();
                    $lockedLoan->responded_at = now();
                    $lockedLoan->save();
                    $this->loanPayments->ensurePaymentsForLoan($lockedLoan);
                    $this->loanPayments->syncStatusesForLoan($lockedLoan);
                } else {
                    if (! $lockedLoan->funds_released_at && ! $lockedLoan->funds_disbursed_at) {
                        $parentUser = $lockedLoan->parent()->firstOrFail();
                        $parentBalance->movements()->create([
                            'amount_added' => $reservedCents,
                            'movement_type' => 'loan_refund',
                            'note' => 'Loan rejected by child',
                            'resulting_balance' => max(BalanceHelper::parentMoneyUsedCents($parentUser) - $reservedCents, 0),
                        ]);
                        $lockedLoan->funds_released_at = now();
                    }

                    $lockedLoan->status = 'rejected';
                    $lockedLoan->rejection_reason = trim((string) $validated['reason']);
                    $lockedLoan->responded_at = now();
                    $lockedLoan->save();
                }

                $lockedLoan->load(['parent:id,name,email,role', 'child:id,name,username']);
                $amountText = $this->notifications->money($reservedCents);
                if ($validated['action'] === 'accept') {
                    $this->notifications->recordForMember(
                        $child->id,
                        'aceptacion',
                        'prestamos',
                        'Aceptaste un prestamo por '.$amountText.'.'
                    );
                    $this->notifications->recordForParent(
                        $lockedLoan->parent_user_id,
                        'aceptacion',
                        'prestamos',
                        $child->name.' acepto un prestamo por '.$amountText.'.'
                    );
                } else {
                    $this->notifications->recordForMember(
                        $child->id,
                        'rechazo',
                        'prestamos',
                        'Rechazaste una oferta de prestamo por '.$amountText.'.'
                    );
                    $this->notifications->recordForParent(
                        $lockedLoan->parent_user_id,
                        'rechazo',
                        'prestamos',
                        $child->name.' rechazo una oferta de prestamo por '.$amountText.'.'
                    );
                }

                return [
                    'loan' => $this->presentLoan($lockedLoan->loadMissing(['payments' => fn ($query) => $query->orderBy('installment_number')]))->toArray(),
                    'member_balance_cents' => (int) $childBalance->amount,
                    'remaining_balance' => BalanceHelper::parentMoneyUsedCents($lockedLoan->parent),
                ];
            });
        } catch (\RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'LOAN_FORBIDDEN' => response()->json(['message' => 'Esta oferta no pertenece a tu cuenta.'], 403),
                'LOAN_NOT_OFFERED' => response()->json(['message' => 'El prestamo ya no esta pendiente de respuesta.'], 422),
                'RESERVATION_NOT_FOUND', 'RESERVATION_AMOUNT_MISMATCH' => response()->json(['message' => 'La reserva del prestamo no coincide con sus condiciones.'], 422),
                default => throw $exception,
            };
        }

        return response()->json([
            'message' => $validated['action'] === 'accept' ? 'Prestamo aceptado.' : 'Oferta rechazada.',
            'loan' => $result['loan'],
            'member_balance_cents' => $result['member_balance_cents'],
            'member_balance_display' => BalanceHelper::displayCents((int) $result['member_balance_cents']),
            'remaining_balance' => $result['remaining_balance'],
            'remaining_balance_display' => BalanceHelper::displayCents((int) $result['remaining_balance']),
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

    private function buildLoanTermsFromParentInput(array $validated): array
    {
        $amountCents = $this->moneyInputToCents($validated['amount']);
        $hasInterest = filter_var($validated['has_interest'], FILTER_VALIDATE_BOOLEAN);
        $interestMode = (string) $validated['interest_mode'];
        $dueDate = \Carbon\Carbon::parse($validated['due_date'])->toDateString();
        $rate = (float) $validated['annual_interest_rate'];
        $fixedInterestCents = $this->moneyInputToCents($validated['fixed_interest_amount']);

        if (! $hasInterest) {
            if ($rate != 0.0 || $fixedInterestCents !== 0) {
                throw new \InvalidArgumentException('Sin interes, annual_interest_rate y fixed_interest_amount deben ser cero.');
            }

            return $this->buildLoanTerms(
                $amountCents,
                (int) $validated['installments_count'],
                (string) $validated['installment_frequency'],
                $dueDate,
                false,
                'percent',
                0.0,
                0
            );
        }

        if ($interestMode === 'percent') {
            if ($rate <= 0 || $fixedInterestCents !== 0) {
                throw new \InvalidArgumentException('Para interes porcentual, indica una tasa mayor que cero y fixed_interest_amount en cero.');
            }

            return $this->buildLoanTerms(
                $amountCents,
                (int) $validated['installments_count'],
                (string) $validated['installment_frequency'],
                $dueDate,
                true,
                'percent',
                $rate,
                0
            );
        }

        if ($fixedInterestCents <= 0 || $rate != 0.0) {
            throw new \InvalidArgumentException('Para interes fijo, indica fixed_interest_amount mayor que cero y annual_interest_rate en cero.');
        }

        return $this->buildLoanTerms(
            $amountCents,
            (int) $validated['installments_count'],
            (string) $validated['installment_frequency'],
            $dueDate,
            true,
            'fixed',
            0.0,
            $fixedInterestCents
        );
    }

    private function buildLoanTerms(
        int $amountCents,
        int $installmentsCount,
        string $frequency,
        string $dueDate,
        bool $hasInterest,
        string $interestMode,
        float $rate,
        int $fixedInterestCents
    ): array {
        if (! LoanAmountGuard::isPositive($amountCents, 1, 1)) {
            throw new \InvalidArgumentException('El monto del prestamo debe ser mayor que cero.');
        }

        if (! $hasInterest) {
            $rate = 0.0;
            $fixedInterestCents = 0;
            $interestMode = 'percent';
            $interestCents = 0;
        } elseif ($interestMode === 'fixed') {
            $interestCents = $fixedInterestCents;
        } else {
            $rateBasisPoints = (int) round($rate * 100);
            $interestCents = intdiv(($amountCents * $rateBasisPoints) + 5000, 10000);
        }

        $totalCents = $amountCents + $interestCents;
        if ($totalCents > 99_999_999_999_999) {
            throw new \InvalidArgumentException('El total del prestamo supera el limite permitido.');
        }
        if ($totalCents < $installmentsCount) {
            throw ValidationException::withMessages([
                'amount' => ['El monto debe permitir cuotas de al menos un centavo.'],
            ]);
        }
        $installmentCents = intdiv($totalCents + $installmentsCount - 1, $installmentsCount);
        LoanAmountGuard::assertPositive($amountCents, $totalCents, $installmentCents);

        return [
            'amount' => $this->moneyFromCents($amountCents),
            'reason' => null,
            'due_date' => $dueDate,
            'installments_count' => $installmentsCount,
            'installment_frequency' => $frequency,
            'has_interest' => $hasInterest,
            'interest_mode' => $interestMode,
            'annual_interest_rate' => $rate,
            'fixed_interest_amount' => $this->moneyFromCents($fixedInterestCents),
            'total_amount' => $this->moneyFromCents($totalCents),
            'installment_amount' => $this->moneyFromCents($installmentCents),
            'amount_cents' => $amountCents,
            'total_amount_cents' => $totalCents,
            'installment_amount_cents' => $installmentCents,
        ];
    }

    private function persistedLoanTerms(array $terms): array
    {
        return [
            'amount' => $terms['amount'],
            'due_date' => $terms['due_date'],
            'installments_count' => $terms['installments_count'],
            'installment_frequency' => $terms['installment_frequency'],
            'has_interest' => $terms['has_interest'],
            'interest_mode' => $terms['interest_mode'],
            'annual_interest_rate' => $terms['annual_interest_rate'],
            'fixed_interest_amount' => $terms['fixed_interest_amount'],
            'total_amount' => $terms['total_amount'],
            'installment_amount' => $terms['installment_amount'],
        ];
    }

    private function loanHasSameTerms(Loan $loan, array $terms): bool
    {
        return BalanceHelper::parseMoneyToCents((string) $loan->amount) === $terms['amount_cents']
            && ($loan->proposed_due_date ?? $loan->initial_due_date)?->toDateString() === $terms['due_date']
            && (int) $loan->installments_count === (int) $terms['installments_count']
            && $loan->installment_frequency === $terms['installment_frequency']
            && (bool) $loan->has_interest === (bool) $terms['has_interest']
            && $loan->interest_mode === $terms['interest_mode']
            && (int) round((float) $loan->annual_interest_rate * 100) === (int) round((float) $terms['annual_interest_rate'] * 100)
            && BalanceHelper::parseMoneyToCents((string) $loan->fixed_interest_amount)
                === BalanceHelper::parseMoneyToCents((string) $terms['fixed_interest_amount'])
            && BalanceHelper::parseMoneyToCents((string) $loan->total_amount) === $terms['total_amount_cents']
            && BalanceHelper::parseMoneyToCents((string) $loan->installment_amount) === $terms['installment_amount_cents'];
    }

    private function lockOrCreateBalance(int $userId): Balance
    {
        $balance = Balance::query()->where('user_id', $userId)->lockForUpdate()->first();
        if ($balance) {
            return $balance;
        }

        Balance::query()->firstOrCreate(['user_id' => $userId], ['amount' => 0]);

        return Balance::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    private function disbursePendingLoan(\App\Models\User $parent, Loan $loan): array
    {
        $parentBalance = $this->lockOrCreateBalance((int) $parent->id);
        $childBalance = $this->lockOrCreateBalance((int) $loan->child_user_id);
        $amountCents = BalanceHelper::parseMoneyToCents((string) $loan->amount);
        $parentMoneyUsedAfter = BalanceHelper::parentMoneyUsedCents($parent) + $amountCents;

        // A child request does not start its repayment clock until the parent approves it.
        if ($loan->requested_months !== null) {
            $loan->due_date = now()->startOfDay()->addMonthNoOverflow()->toDateString();
        }

        $parentBalance->movements()->create([
            'amount_added' => $amountCents,
            'movement_type' => 'loan_debit',
            'note' => 'Loan approved by parent',
            'resulting_balance' => $parentMoneyUsedAfter,
        ]);
        $childBalance->amount = (int) $childBalance->amount + $amountCents;
        $childBalance->save();
        $childBalance->movements()->create([
            'amount_added' => $amountCents,
            'movement_type' => 'loan_credit',
            'note' => 'Loan approved by parent',
            'resulting_balance' => $childBalance->amount,
        ]);

        $loan->status = 'approved';
        $loan->rejection_reason = null;
        $loan->responded_at = now();
        $loan->parent_reviewed_at = $loan->parent_reviewed_at ?? now();
        $loan->funds_disbursed_at = now();
        $loan->save();
        $this->loanPayments->ensurePaymentsForLoan($loan);
        $this->loanPayments->syncStatusesForLoan($loan);

        $loan->load(['parent:id,name,email,role', 'child:id,name,username']);
        $amountText = $this->notifications->money($amountCents);
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
            'loan' => $this->presentLoan($loan->load(['payments' => fn ($query) => $query->orderBy('installment_number')]))->toArray(),
            'remaining_balance' => $parentMoneyUsedAfter,
            'child_balance_cents' => (int) $childBalance->amount,
        ];
    }

    private function approvedLoanResponseData(Loan $loan): array
    {
        $loan->loadMissing(['parent:id,name,email,role', 'child:id,name,username']);
        $loan->loadMissing(['payments' => fn ($query) => $query->orderBy('installment_number')]);
        $childBalanceCents = (int) ($loan->child?->balance()->value('amount') ?? 0);

        return [
            'loan' => $this->presentLoan($loan)->toArray(),
            'remaining_balance' => BalanceHelper::parentMoneyUsedCents($loan->parent),
            'child_balance_cents' => $childBalanceCents,
            'member_balance_cents' => $childBalanceCents,
        ];
    }

    private function proposalResponseData(Loan $loan, ?int $parentMoneyUsed = null): array
    {
        $loan->loadMissing(['parent:id,name,email,role', 'child:id,name,username']);
        $loan->loadMissing(['payments' => fn ($query) => $query->orderBy('installment_number')]);

        return [
            'loan' => $this->presentLoan($loan)->toArray(),
            'remaining_balance' => $parentMoneyUsed ?? BalanceHelper::parentMoneyUsedCents($loan->parent),
            'child_balance_cents' => (int) ($loan->child?->balance()->value('amount') ?? 0),
        ];
    }

    private function loanActionResponse(array $result, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'loan' => $result['loan'],
            'remaining_balance' => $result['remaining_balance'],
            'remaining_balance_display' => BalanceHelper::displayCents((int) $result['remaining_balance']),
            'child_balance_cents' => $result['child_balance_cents'],
            'child_balance_display' => BalanceHelper::displayCents((int) $result['child_balance_cents']),
        ]);
    }

    private function moneyFromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function moneyInputToCents(string|int|float $value): int
    {
        return BalanceHelper::parseMoneyToCents(is_float($value) ? number_format($value, 2, '.', '') : $value);
    }

    private function effectiveDueDateForActivation(Loan $loan): string
    {
        $proposedDueDate = $loan->proposed_due_date ?? $loan->due_date;
        $initialDueDate = $loan->initial_due_date;

        if ($loan->requested_months !== null
            && $initialDueDate !== null
            && $proposedDueDate->toDateString() === $initialDueDate->toDateString()) {
            $activation = now()->startOfDay();

            return match ($loan->installment_frequency) {
                'weekly' => $activation->addWeek()->toDateString(),
                'biweekly' => $activation->addDays(14)->toDateString(),
                default => $activation->addMonthNoOverflow()->toDateString(),
            };
        }

        $dueDate = $proposedDueDate->copy()->startOfDay();
        $offerStartedAt = $loan->parent_reviewed_at ?? $loan->created_at;

        $pendingDays = max(
            0,
            (int) $offerStartedAt->copy()->startOfDay()->diffInDays(now()->startOfDay())
        );

        return $dueDate->addDays($pendingDays)->toDateString();
    }

    private function presentLoan(Loan $loan): Loan
    {
        if ($loan->status === 'offered' && ! $loan->funds_disbursed_at) {
            $loan->due_date = $this->effectiveDueDateForActivation($loan);
        }

        $summary = $this->loanPayments->buildSummary($loan);
        $loan->setAttribute('payment_summary', $summary);

        return $loan;
    }

    private function markLoanReadDiagnosticStage(Request $request, string $stage, array $context = []): void
    {
        if (! $request->attributes->has('loans_read_diagnostic_id')) {
            return;
        }

        $request->attributes->set('loans_read_diagnostic_stage', $stage);

        foreach ([
            'user_id',
            'user_role',
            'loan_id',
            'loan_count',
            'loan_ids_sample',
            'loan_status',
            'loan_requested_months',
            'loan_due_date',
            'loan_proposed_due_date',
            'loan_initial_due_date',
        ] as $key) {
            if (array_key_exists($key, $context)) {
                $request->attributes->set('loans_read_'.$key, $context[$key]);
            }
        }
    }

    private function reloadAndVerifyLoanAmounts(Loan $loan, array $expectedData): Loan
    {
        $persistedLoan = Loan::query()
            ->whereKey($loan->id)
            ->lockForUpdate()
            ->firstOrFail();

        LoanAmountGuard::assertMatches(
            [
                'amount' => $expectedData['amount'] ?? 0,
                'total_amount' => $expectedData['total_amount'] ?? 0,
                'installment_amount' => $expectedData['installment_amount'] ?? 0,
            ],
            [
                'amount' => $persistedLoan->getRawOriginal('amount'),
                'total_amount' => $persistedLoan->getRawOriginal('total_amount'),
                'installment_amount' => $persistedLoan->getRawOriginal('installment_amount'),
            ],
            'after_loan_insert_reload'
        );

        return $persistedLoan;
    }

}
