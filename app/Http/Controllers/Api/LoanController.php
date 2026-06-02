<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\BalanceMovement;
use App\Models\FamilyMember;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class LoanController extends Controller
{
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
                ->with('child:id,name,username')
                ->where('parent_user_id', $user->id)
                ->latest()
                ->get();
        } else {
            $loans = Loan::query()
                ->with('parent:id,name,email')
                ->where('child_user_id', $user->id)
                ->latest()
                ->get();
        }

        return response()->json(['loans' => $loans]);
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

        return response()->json(['loans' => $loans]);
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
            ->where('parent_user_id', $parent->id)
            ->whereIn('status', ['approved', 'paid'])
            ->get();

        $estimatedPaidTotal = 0;

        foreach ($loans as $loan) {
            $totalAmount = (int) $loan->total_amount;
            $installmentsCount = max(1, (int) $loan->installments_count);

            if ($installmentsCount === 1) {
                $estimatedPaidTotal += $loan->status === 'paid' ? $totalAmount : 0;
                continue;
            }

            if ($loan->status === 'paid') {
                $estimatedPaidTotal += $totalAmount;
                continue;
            }

            $startDate = $loan->responded_at ? Carbon::parse($loan->responded_at) : $loan->created_at;
            $now = now();

            if ($startDate->greaterThan($now)) {
                continue;
            }

            $elapsedInstallments = match ($loan->installment_frequency) {
                'weekly' => intdiv($startDate->diffInDays($now), 7),
                'biweekly' => intdiv($startDate->diffInDays($now), 14),
                default => $startDate->diffInMonths($now),
            };

            $paidInstallments = min($installmentsCount, max(0, $elapsedInstallments));
            $estimatedPaid = min($totalAmount, $paidInstallments * (int) $loan->installment_amount);
            $estimatedPaidTotal += $estimatedPaid;
        }

        return response()->json([
            'total_active_loans' => $loans->count(),
            'estimated_total_paid' => $estimatedPaidTotal,
            'currency' => 'MXN',
            'meta' => [
                'is_estimated' => true,
                'estimation_note' => 'Deferred loans are estimated by elapsed installments because no payment ledger exists yet.',
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
            $loan = Loan::create([
                'parent_user_id' => $parentId,
                ...$loanData,
                'status' => 'pending',
                'requested_by_user_id' => $actor->id,
            ])->load('child:id,name,username');

            return response()->json([
                'message' => 'Loan request submitted and pending approval.',
                'loan' => $loan,
            ], 201);
        }

        if ($actor->role === 'parent') {
            try {
                $payload = DB::transaction(function () use ($parentId, $loanData) {
                $loan = Loan::create([
                    'parent_user_id' => $parentId,
                    ...$loanData,
                    'status' => 'offered',
                    'requested_by_user_id' => $parentId,
                ]);

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

                if ((int) $balance->amount < (int) $loan->amount) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                $balance->amount = (int) $balance->amount - (int) $loan->amount;
                $balance->save();

                $balance->movements()->create([
                    'amount_added' => (int) $loan->amount,
                    'movement_type' => 'loan_reserve',
                    'note' => 'Loan reserved while waiting for child response',
                    'resulting_balance' => $balance->amount,
                ]);

                return [
                    'loan' => $loan->fresh(['child:id,name,username']),
                    'remaining_balance' => (int) $balance->amount,
                ];
                });
            } catch (\RuntimeException $exception) {
                if ($exception->getMessage() === 'INSUFFICIENT_BALANCE') {
                    return response()->json(['message' => 'No tienes fondos suficientes para crear este prestamo.'], 422);
                }

                throw $exception;
            }

            return response()->json([
                'message' => 'Loan offer created and balance reserved successfully.',
                'loan' => $payload['loan'],
                'remaining_balance' => $payload['remaining_balance'],
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
            'loan' => $loan,
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

                if ((int) $balance->amount < (int) $loan->amount) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                $balance->amount = (int) $balance->amount - (int) $loan->amount;
                $balance->save();

                $balance->movements()->create([
                    'amount_added' => (int) $loan->amount,
                    'movement_type' => 'loan_debit',
                    'note' => 'Loan approval',
                    'resulting_balance' => $balance->amount,
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

                $childBalance->amount = (int) $childBalance->amount + (int) $loan->amount;
                $childBalance->save();

                $childBalance->movements()->create([
                    'amount_added' => (int) $loan->amount,
                    'movement_type' => 'loan_credit',
                    'note' => 'Loan approved by parent',
                    'resulting_balance' => $childBalance->amount,
                ]);

                $loan->status = 'approved';
                $loan->rejection_reason = null;
                $loan->responded_at = now();
                $loan->save();

                return [
                    'loan' => $loan->fresh(['child:id,name,username']),
                    'remaining_balance' => (int) $balance->amount,
                ];
            });
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['message' => 'No tienes fondos suficientes para aprobar este prestamo.'], 422);
            }

            throw $exception;
        }

        return response()->json(['message' => 'Loan approved.', 'loan' => $payload['loan'], 'remaining_balance' => $payload['remaining_balance']]);
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

                $childBalance->amount = (int) $childBalance->amount + (int) $loan->amount;
                $childBalance->save();

                $childBalance->movements()->create([
                    'amount_added' => (int) $loan->amount,
                    'movement_type' => 'loan_credit',
                    'note' => 'Loan accepted by child',
                    'resulting_balance' => $childBalance->amount,
                ]);

                $loan->status = 'approved';
                $loan->rejection_reason = null;
            } else {
                if ($parentBalance) {
                    $parentBalance->amount = (int) $parentBalance->amount + (int) $loan->amount;
                    $parentBalance->save();

                    $parentBalance->movements()->create([
                        'amount_added' => (int) $loan->amount,
                        'movement_type' => 'loan_refund',
                        'note' => 'Loan rejected by child',
                        'resulting_balance' => $parentBalance->amount,
                    ]);
                }

                $loan->status = 'rejected';
                $loan->rejection_reason = trim((string) $validated['reason']);
            }

            $loan->responded_at = now();
            $loan->save();

            return $loan->fresh(['parent:id,name,email', 'child:id,name,username']);
        });

        return response()->json([
            'message' => $validated['action'] === 'accept' ? 'Loan accepted.' : 'Loan rejected.',
            'loan' => $result,
        ]);
    }
}
