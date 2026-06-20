<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\Loan;
use App\Models\DomusRewardRedemption;
use App\Models\Task;
use App\Models\User;
use App\Services\DomusAchievementService;
use App\Services\DomusLevelService;
use App\Services\DomusNotificationService;
use App\Services\EducationExamRewardService;
use App\Services\LoanPaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FamilyMemberController extends Controller
{
    public function __construct(
        private readonly DomusNotificationService $notifications,
        private readonly DomusAchievementService $achievements,
        private readonly EducationExamRewardService $examRewards,
        private readonly DomusLevelService $levels,
        private readonly LoanPaymentService $loanPayments,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $parent = $request->user();

        if ($parent->role !== 'parent') {
            return response()->json([
                'message' => 'Only parent users can view family members.',
            ], 403);
        }

        $members = FamilyMember::query()
            ->with('user:id,name,username,role')
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->get();

        $loanTargets = $members
            ->filter(fn (FamilyMember $member) => (bool) $member->user)
            ->map(function (FamilyMember $member) {
                return [
                    'family_member_id' => $member->id,
                    'user_id' => $member->user->id,
                    'name' => $member->user->name,
                    'username' => $member->user->username,
                    'role' => $member->user->role,
                    'is_minor' => (bool) $member->is_minor,
                ];
            })
            ->values();

        return response()->json([
            'members' => $members,
            'loan_targets' => $loanTargets,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $parent = $request->user();

        if (! $parent || $parent->role !== 'parent') {
            return response()->json([
                'message' => 'Only parent users can view family summary.',
            ], 403);
        }

        $members = FamilyMember::query()
            ->with(['user:id,name,username,role', 'user.balance'])
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->get()
            ->map(fn (FamilyMember $member) => $this->presentFamilyMemberSummary($parent->id, $member))
            ->filter()
            ->values();

        return response()->json([
            'members' => $members,
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $parent = $request->user();

        if (! $parent || $parent->role !== 'parent') {
            return response()->json([
                'message' => 'Only parent users can view family member detail.',
            ], 403);
        }

        $familyMember = FamilyMember::query()
            ->with(['user.balance'])
            ->where('parent_user_id', $parent->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $familyMember || ! $familyMember->user) {
            return response()->json([
                'message' => 'This user does not belong to your family.',
            ], 404);
        }

        $summary = $this->presentFamilyMemberSummary($parent->id, $familyMember);
        $child = $familyMember->user;
        $historical = $this->earnedPointsForChild($parent->id, $child->id);
        $spent = $this->spentPointsForChild($child->id);
        $available = max($historical - $spent, 0);

        $loans = Loan::query()
            ->with(['payments' => fn ($query) => $query->orderBy('installment_number')])
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->latest()
            ->get();

        $this->loanPayments->syncStatusesForLoans($loans);

        foreach ($loans as $loan) {
            $this->loanPayments->refreshLoanStatus($loan);
        }

        return response()->json([
            'member' => [
                'user_id' => $child->id,
                'name' => $child->name,
                'username' => $child->username,
                'role' => $child->role,
                'is_minor' => (bool) $familyMember->is_minor,
                'balance_cents' => (int) ($child->balance?->amount ?? 0),
                'balance_display' => $child->balance_display,
                'points' => [
                    'historical' => $historical,
                    'spent' => $spent,
                    'available' => $available,
                ],
                'level' => $this->levels->resolveForPoints($historical),
                'loan_health' => $summary['loan_health'],
                'debt_principal_cents' => $summary['debt_principal_cents'],
                'debt_principal_display' => $summary['debt_principal_display'],
                'active_loans_count' => $summary['active_loans_count'],
            ],
            'loans' => $loans->map(function (Loan $loan) {
                $summary = $this->loanPayments->buildSummary($loan);

                return [
                    'id' => (int) $loan->id,
                    'status' => $loan->status,
                    'amount' => (int) $loan->amount,
                    'total_amount' => (int) $loan->total_amount,
                    'reason' => $loan->reason,
                    'due_date' => $loan->due_date,
                    'installments_count' => (int) $loan->installments_count,
                    'installment_frequency' => $loan->installment_frequency,
                    'has_interest' => (bool) $loan->has_interest,
                    'annual_interest_rate' => (float) $loan->annual_interest_rate,
                    'payment_summary' => $summary,
                    'created_at' => $loan->created_at?->toISOString(),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $parent = $request->user();

        if ($parent->role !== 'parent') {
            return response()->json([
                'message' => 'Only parent users can create family members.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:255', 'alpha_num', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_minor' => ['nullable', 'boolean'],
            'guardian_declaration_accepted' => ['nullable', 'boolean'],
        ]);

        $isMinor = (bool) ($validated['is_minor'] ?? false);
        $guardianDeclarationAccepted = (bool) ($validated['guardian_declaration_accepted'] ?? false);

        if ($isMinor && ! $guardianDeclarationAccepted) {
            return response()->json([
                'message' => 'Guardian declaration is required when registering a minor.',
            ], 422);
        }

        $member = DB::transaction(function () use ($parent, $validated, $isMinor, $guardianDeclarationAccepted) {
            $familyRole = $isMinor ? 'child' : 'member';
            $childUser = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'role' => $familyRole,
                'password' => Hash::make($validated['password']),
            ]);

            Balance::create([
                'user_id' => $childUser->id,
                'amount' => 0,
            ]);

            $member = FamilyMember::create([
                'parent_user_id' => $parent->id,
                'user_id' => $childUser->id,
                'is_minor' => $isMinor,
                'guardian_declaration_accepted' => $guardianDeclarationAccepted,
            ])->load('user:id,name,username,role');

            $this->notifications->recordForParent(
                $parent->id,
                'integrantes',
                'usuarios',
                'Creaste el integrante '.$childUser->name.' (@'.$childUser->username.').'
            );
            $this->notifications->recordForMember(
                $childUser->id,
                'creacion',
                'usuarios',
                'Tu cuenta familiar fue creada.'
            );

            return $member;
        });

        return response()->json([
            'message' => 'Family member created.',
            'member' => $member,
        ], 201);
    }

    private function presentFamilyMemberSummary(int $parentId, FamilyMember $member): ?array
    {
        $child = $member->user;

        if (! $child) {
            return null;
        }

        $historical = $this->earnedPointsForChild($parentId, $child->id);
        $level = $this->levels->resolveForPoints($historical);
        $loanState = $this->resolveLoanHealthForChild($parentId, $child->id);

        return [
            'user_id' => $child->id,
            'name' => $child->name,
            'username' => $child->username,
            'role' => $child->role,
            'balance_cents' => (int) ($child->balance?->amount ?? 0),
            'balance_display' => $child->balance_display,
            'level' => $level,
            'level_label' => $level ? 'Nivel '.(int) $level['level_number'] : 'Sin nivel',
            'debt_principal_cents' => $loanState['debt_principal_cents'],
            'debt_principal_display' => $this->moneyFromCents($loanState['debt_principal_cents']),
            'loan_health' => $loanState['loan_health'],
            'active_loans_count' => $loanState['active_loans_count'],
        ];
    }

    private function resolveLoanHealthForChild(int $parentId, int $childId): array
    {
        $loans = Loan::query()
            ->with(['payments' => fn ($query) => $query->orderBy('installment_number')])
            ->where('parent_user_id', $parentId)
            ->where('child_user_id', $childId)
            ->whereIn('status', ['approved', 'paid'])
            ->get();

        if ($loans->isEmpty()) {
            return [
                'debt_principal_cents' => 0,
                'active_loans_count' => 0,
                'loan_health' => [
                    'key' => 'current',
                    'label' => 'Al dia',
                    'next_due_date' => null,
                ],
            ];
        }

        $this->loanPayments->syncStatusesForLoans($loans);

        $remainingPrincipalCents = 0;
        $activeLoansCount = 0;
        $hasOverduePayments = false;
        $nearestDueDate = null;

        foreach ($loans as $loan) {
            $this->loanPayments->refreshLoanStatus($loan);
            $summary = $this->loanPayments->buildSummary($loan);
            $remainingPrincipalCents += (int) ($summary['remaining_principal_cents'] ?? 0);

            if ((int) ($summary['remaining_principal_cents'] ?? 0) > 0) {
                $activeLoansCount++;
            }

            if ((int) ($summary['overdue_installments'] ?? 0) > 0) {
                $hasOverduePayments = true;
            }

            $nextDueDate = $summary['next_payment']['due_date'] ?? null;
            if ($nextDueDate && ($nearestDueDate === null || $nextDueDate < $nearestDueDate)) {
                $nearestDueDate = $nextDueDate;
            }
        }

        $loanHealth = [
            'key' => 'current',
            'label' => 'Al dia',
            'next_due_date' => $nearestDueDate,
        ];

        if ($hasOverduePayments) {
            $loanHealth = [
                'key' => 'overdue',
                'label' => 'Vencido',
                'next_due_date' => $nearestDueDate,
            ];
        } elseif ($nearestDueDate) {
            $today = now()->startOfDay();
            $daysUntilDue = $today->diffInDays(Carbon::parse($nearestDueDate)->startOfDay(), false);

            if ($daysUntilDue >= 0 && $daysUntilDue <= 3) {
                $loanHealth = [
                    'key' => 'due-soon',
                    'label' => 'Por vencer',
                    'next_due_date' => $nearestDueDate,
                ];
            }
        }

        return [
            'debt_principal_cents' => $remainingPrincipalCents,
            'active_loans_count' => $activeLoansCount,
            'loan_health' => $loanHealth,
        ];
    }

    private function earnedPointsForChild(int $parentId, int $childId): int
    {
        $taskPoints = (int) Task::query()
            ->where('parent_user_id', $parentId)
            ->whereIn('status', ['closed', 'ended', 'completed'])
            ->where(function ($query) use ($childId) {
                $query->where('completed_by_user_id', $childId)
                    ->orWhere(function ($legacyQuery) use ($childId) {
                        $legacyQuery->whereNull('completed_by_user_id')
                            ->where('accepted_by_user_id', $childId);
                    });
            })
            ->sum('reward_points');

        return $taskPoints
            + $this->achievements->totalPointsForUser($childId)
            + $this->examRewards->totalAwardedPointsForUser($childId);
    }

    private function spentPointsForChild(int $childId): int
    {
        return (int) DomusRewardRedemption::query()
            ->where('child_user_id', $childId)
            ->sum('points_spent');
    }

    private function moneyFromCents(int $cents): string
    {
        return '$'.number_format($cents / 100, 2, '.', ',');
    }
}
