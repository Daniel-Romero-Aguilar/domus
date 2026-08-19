<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\SavingsBox;
use App\Models\SavingsBoxAccount;
use App\Services\DomusNotificationService;
use App\Services\SavingsBoxInterestService;
use App\Services\SavingsBoxPlanService;
use App\Support\BalanceHelper;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SavingsBoxController extends Controller
{
    public function __construct(
        private readonly SavingsBoxInterestService $interestService,
        private readonly SavingsBoxPlanService $planService,
        private readonly DomusNotificationService $notifications
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if ($user->role === 'parent') {
            $this->interestService->runDueForParent($user->id);

            $boxes = SavingsBox::query()
                ->with(['members:id,name,username', 'accounts.user:id,name,username'])
                ->where('parent_user_id', $user->id)
                ->latest()
                ->get();

            $this->decoratePlanUsage($boxes, Carbon::now());

            return response()->json(['savings_boxes' => $boxes]);
        }

        if ($user->role === 'child' || $user->role === 'member') {
            $this->interestService->runDueForUser($user->id);

            $boxes = SavingsBox::query()
                ->with([
                    'parent:id,name',
                    'accounts' => fn ($query) => $query
                        ->where('user_id', $user->id)
                        ->with('user:id,name,username'),
                ])
                ->where('status', 'active')
                ->whereHas('accounts', fn ($query) => $query->where('user_id', $user->id))
                ->latest()
                ->get();

            $this->decoratePlanUsage($boxes, Carbon::now());

            return response()->json(['savings_boxes' => $boxes]);
        }

        return response()->json(['message' => 'Only family users can view savings boxes.'], 403);
    }

    public function store(Request $request): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can create savings boxes.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'duration_months' => ['required', 'integer', Rule::in($this->planService->availableMonths())],
            'max_per_cycle' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'benefit_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'allow_early_withdrawal' => ['required', 'boolean'],
            'audience' => ['required', 'in:all,specific'],
            'member_user_ids' => ['required_if:audience,specific', 'array'],
            'member_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $memberIds = collect($validated['member_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $familyMemberIds = FamilyMember::query()
            ->where('parent_user_id', $parent->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($familyMemberIds->isEmpty()) {
            return response()->json(['message' => 'Crea al menos un integrante antes de crear una caja de ahorro.'], 422);
        }

        if ($validated['audience'] === 'specific') {
            if ($memberIds->isEmpty()) {
                return response()->json(['message' => 'Selecciona al menos un integrante.'], 422);
            }

            if ($familyMemberIds->intersect($memberIds)->count() !== $memberIds->count()) {
                return response()->json(['message' => 'Uno o mas integrantes no pertenecen a tu familia.'], 422);
            }
        }

        $enabledMemberIds = $validated['audience'] === 'all' ? $familyMemberIds : $memberIds;
        $maxPerCycleCents = $this->parsePositiveMoney(
            $validated['max_per_cycle'],
            'El maximo por ciclo debe escribirse como dinero normal y ser mayor a cero.'
        );

        try {
            $snapshot = $this->planService->snapshot(
                Carbon::now()->startOfSecond(),
                (int) $validated['duration_months'],
                $maxPerCycleCents
            );
        } catch (\OverflowException $exception) {
            return response()->json(['message' => 'El maximo total de la caja es demasiado grande.'], 422);
        }

        $benefitPercent = round((float) $validated['benefit_percent'], 2);

        $box = DB::transaction(function () use ($parent, $validated, $enabledMemberIds, $snapshot, $benefitPercent): SavingsBox {
            $box = SavingsBox::create([
                'parent_user_id' => $parent->id,
                'name' => $validated['name'],
                'duration_months' => $snapshot['duration_months'],
                'duration_days' => $snapshot['duration_days'],
                'cycle_days' => $snapshot['cycle_days'],
                'cycle_count' => $snapshot['cycle_count'],
                'plan_version' => $snapshot['plan_version'],
                'max_per_cycle_cents' => $snapshot['max_per_cycle_cents'],
                'max_amount_cents' => $snapshot['max_amount_cents'],
                'starts_at' => $snapshot['starts_at'],
                'cutoff_at' => $snapshot['cutoff_at'],
                'delivery_date' => $snapshot['cutoff_at']->toDateString(),
                'annual_gain_percent' => $benefitPercent,
                'benefit_percent' => $benefitPercent,
                'benefit_mode' => 'final_balance_simple',
                'allow_early_withdrawal' => (bool) $validated['allow_early_withdrawal'],
                'audience' => $validated['audience'],
                'status' => 'active',
            ]);

            if ($validated['audience'] === 'specific') {
                $box->members()->sync($enabledMemberIds->all());
            }

            foreach ($enabledMemberIds as $memberId) {
                $box->accounts()->create([
                    'user_id' => $memberId,
                    'principal_cents' => 0,
                    'principal_pending_cents' => 0,
                    'earned_interest_cents' => 0,
                    'interest_remainder_microcents' => 0,
                    'last_interest_accrued_on' => null,
                    'interest_accrued_until_at' => null,
                ]);

                $this->notifications->recordForMember(
                    (int) $memberId,
                    'creacion',
                    'cajas_ahorro',
                    'Te habilitaron la caja de ahorro '.$box->name.'.'
                );
            }

            $this->notifications->recordForParent(
                (int) $parent->id,
                'creacion',
                'cajas_ahorro',
                'Creaste la caja de ahorro '.$box->name.' para '.$enabledMemberIds->count().' integrante(s).'
            );

            return $box;
        });

        return response()->json([
            'message' => 'Caja de ahorro creada correctamente.',
            'savings_box' => $box->load(['members:id,name,username', 'accounts.user:id,name,username']),
        ], 201);
    }

    public function deposit(Request $request, SavingsBox $savingsBox): JsonResponse
    {
        $user = $request->user();
        if (! $user || ($user->role !== 'child' && $user->role !== 'member')) {
            return response()->json(['message' => 'Only member users can deposit into savings boxes.'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);
        $amountCents = $this->parsePositiveMoney(
            $validated['amount'],
            'El abono debe escribirse como dinero normal y ser mayor a cero.'
        );

        $payload = DB::transaction(function () use ($user, $savingsBox, $amountCents): array {
            $box = SavingsBox::query()->whereKey($savingsBox->id)->lockForUpdate()->firstOrFail();
            $now = Carbon::now();

            if ($box->status !== 'active' || $this->cutoffAt($box)->lte($now)) {
                throw new HttpResponseException(response()->json(['message' => 'Esta caja ya no recibe abonos.'], 422));
            }

            $account = SavingsBoxAccount::query()
                ->where('savings_box_id', $box->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw new HttpResponseException(response()->json(['message' => 'No tienes acceso a esta caja de ahorro.'], 403));
            }

            $cycleNumber = null;
            $depositedInCycleCents = 0;

            if ($box->benefit_mode === 'final_balance_simple') {
                $cycleNumber = $this->planService->currentCycleNumber($box, $now);
                if ($cycleNumber === null) {
                    throw new HttpResponseException(response()->json(['message' => 'Esta caja no tiene un ciclo activo.'], 422));
                }

                $depositedInCycleCents = $this->planService->depositedInCycleCents($account, $cycleNumber);
                if ($depositedInCycleCents + $amountCents > (int) $box->max_per_cycle_cents) {
                    $remainingCycleCents = max(0, (int) $box->max_per_cycle_cents - $depositedInCycleCents);

                    throw new HttpResponseException(response()->json([
                        'message' => 'El abono supera el maximo permitido en este ciclo.',
                        'cycle_number' => $cycleNumber,
                        'remaining_cycle_cents' => $remainingCycleCents,
                        'remaining_cycle_display' => BalanceHelper::displayCents($remainingCycleCents),
                    ], 422));
                }
            } else {
                $this->interestService->accrueAccountUntil($account, $now, $box);
                $account->refresh();
            }

            $currentPrincipalCents = (int) $account->principal_cents + (int) $account->principal_pending_cents;
            if ($box->max_amount_cents !== null && $currentPrincipalCents + $amountCents > (int) $box->max_amount_cents) {
                $remainingCapacityCents = max(0, (int) $box->max_amount_cents - $currentPrincipalCents);

                throw new HttpResponseException(response()->json([
                    'message' => 'El abono supera el maximo total de esta caja de ahorro.',
                    'max_amount_cents' => (int) $box->max_amount_cents,
                    'current_amount_cents' => $currentPrincipalCents,
                    'remaining_capacity_cents' => $remainingCapacityCents,
                    'remaining_capacity_display' => BalanceHelper::displayCents($remainingCapacityCents),
                ], 422));
            }

            $balance = Balance::query()->firstOrCreate(['user_id' => $user->id], ['amount' => 0]);
            $balance = Balance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();

            if ((int) $balance->amount < $amountCents) {
                throw new HttpResponseException(response()->json(['message' => 'No tienes saldo suficiente para hacer este abono.'], 422));
            }

            $balance->amount = (int) $balance->amount - $amountCents;
            $balance->save();
            $balance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'debit',
                'note' => 'Savings box deposit: '.$box->name,
                'resulting_balance' => $balance->amount,
            ]);

            $principalBefore = (int) $account->principal_cents;
            $earnedBefore = (int) $account->earned_interest_cents;
            $account->principal_cents = $principalBefore + $amountCents;
            $account->save();

            $this->interestService->recordMovement(
                $account,
                $box,
                'deposit',
                $amountCents,
                $principalBefore,
                (int) $account->principal_cents,
                $earnedBefore,
                $earnedBefore,
                $now,
                'Savings box deposit',
                $cycleNumber
            );

            $amountText = $this->notifications->money($amountCents);
            $this->notifications->recordForMember($user->id, 'abono', 'cajas_ahorro', 'Abonaste '.$amountText.' a la caja '.$box->name.'.');
            $this->notifications->recordForParent($box->parent_user_id, 'abono', 'cajas_ahorro', $user->name.' abono '.$amountText.' a la caja '.$box->name.'.');

            $currentAmountCents = $currentPrincipalCents + $amountCents;
            $remainingCapacityCents = $box->max_amount_cents === null
                ? null
                : max(0, (int) $box->max_amount_cents - $currentAmountCents);
            $depositedAfterCents = $depositedInCycleCents + $amountCents;
            $remainingCycleCents = $box->max_per_cycle_cents === null
                ? null
                : max(0, (int) $box->max_per_cycle_cents - $depositedAfterCents);

            return [
                'remaining_balance' => (int) $balance->amount,
                'remaining_balance_cents' => (int) $balance->amount,
                'remaining_balance_display' => BalanceHelper::displayCents((int) $balance->amount),
                'max_amount_cents' => $box->max_amount_cents === null ? null : (int) $box->max_amount_cents,
                'current_amount_cents' => $currentAmountCents,
                'remaining_capacity_cents' => $remainingCapacityCents,
                'remaining_capacity_display' => $remainingCapacityCents === null ? null : BalanceHelper::displayCents($remainingCapacityCents),
                'cycle_number' => $cycleNumber,
                'deposited_in_cycle_cents' => $cycleNumber === null ? null : $depositedAfterCents,
                'remaining_cycle_cents' => $remainingCycleCents,
                'remaining_cycle_display' => $remainingCycleCents === null ? null : BalanceHelper::displayCents($remainingCycleCents),
                'account' => $account->fresh(['user:id,name,username']),
            ];
        });

        return response()->json([
            'message' => 'Abono guardado dentro del limite de este ciclo.',
            'data' => $payload,
            ...$payload,
        ]);
    }

    public function withdraw(Request $request, SavingsBox $savingsBox): JsonResponse
    {
        $user = $request->user();
        if (! $user || ($user->role !== 'child' && $user->role !== 'member')) {
            return response()->json(['message' => 'Only member users can withdraw from savings boxes.'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);
        $amountCents = $this->parsePositiveMoney(
            $validated['amount'],
            'El retiro debe escribirse como dinero normal y ser mayor a cero.'
        );

        $payload = DB::transaction(function () use ($user, $savingsBox, $amountCents): array {
            $box = SavingsBox::query()->whereKey($savingsBox->id)->lockForUpdate()->firstOrFail();
            $now = Carbon::now();

            if ($box->status !== 'active' || $this->cutoffAt($box)->lte($now)) {
                throw new HttpResponseException(response()->json(['message' => 'Esta caja llego a su corte y esta esperando liquidacion.'], 422));
            }

            if (! $box->allow_early_withdrawal) {
                throw new HttpResponseException(response()->json(['message' => 'Esta caja no permite retiro anticipado.'], 422));
            }

            $account = SavingsBoxAccount::query()
                ->where('savings_box_id', $box->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw new HttpResponseException(response()->json(['message' => 'No tienes acceso a esta caja de ahorro.'], 403));
            }

            if ($box->benefit_mode !== 'final_balance_simple') {
                $this->interestService->accrueAccountUntil($account, $now, $box);
                $account->refresh();
            }

            if ((int) $account->principal_cents < $amountCents) {
                throw new HttpResponseException(response()->json(['message' => 'No tienes suficiente capital disponible en esta caja.'], 422));
            }

            $principalBefore = (int) $account->principal_cents;
            $earnedBefore = (int) $account->earned_interest_cents;
            $account->principal_cents = $principalBefore - $amountCents;
            $account->save();

            $balance = Balance::query()->firstOrCreate(['user_id' => $user->id], ['amount' => 0]);
            $balance = Balance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            $balance->amount = (int) $balance->amount + $amountCents;
            $balance->save();
            $balance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'credit',
                'note' => 'Savings box early withdrawal: '.$box->name,
                'resulting_balance' => $balance->amount,
            ]);

            $cycleNumber = $this->planService->currentCycleNumber($box, $now);
            $this->interestService->recordMovement(
                $account,
                $box,
                'withdrawal',
                $amountCents,
                $principalBefore,
                (int) $account->principal_cents,
                $earnedBefore,
                $earnedBefore,
                $now,
                'Savings box early withdrawal',
                $cycleNumber
            );

            $amountText = $this->notifications->money($amountCents);
            $this->notifications->recordForMember($user->id, 'retiro', 'cajas_ahorro', 'Retiraste '.$amountText.' de la caja '.$box->name.'.');
            $this->notifications->recordForParent($box->parent_user_id, 'retiro', 'cajas_ahorro', $user->name.' retiro '.$amountText.' de la caja '.$box->name.'.');

            $usage = $this->planService->accountUsage($box, $account, $now);

            return [
                'remaining_balance' => (int) $balance->amount,
                'remaining_balance_cents' => (int) $balance->amount,
                'remaining_balance_display' => BalanceHelper::displayCents((int) $balance->amount),
                'max_amount_cents' => $box->max_amount_cents === null ? null : (int) $box->max_amount_cents,
                'current_amount_cents' => (int) $account->principal_cents,
                'remaining_capacity_cents' => $usage['remaining_total_capacity_cents'],
                'remaining_capacity_display' => $usage['remaining_total_capacity_cents'] === null
                    ? null
                    : BalanceHelper::displayCents($usage['remaining_total_capacity_cents']),
                'cycle_number' => $usage['current_cycle_number'],
                'remaining_cycle_cents' => $usage['remaining_in_current_cycle_cents'],
                'remaining_cycle_display' => $usage['remaining_in_current_cycle_cents'] === null
                    ? null
                    : BalanceHelper::displayCents($usage['remaining_in_current_cycle_cents']),
                'account' => $account->fresh(['user:id,name,username']),
            ];
        });

        return response()->json([
            'message' => 'Retiro guardado. El beneficio final se calculara solamente sobre lo que quede en la fecha de corte.',
            'data' => $payload,
            ...$payload,
        ]);
    }

    private function parsePositiveMoney(string|int|float $value, string $message): int
    {
        try {
            $cents = BalanceHelper::parseMoneyToCents($value);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpResponseException(response()->json(['message' => $message], 422));
        }

        if ($cents < 1) {
            throw new HttpResponseException(response()->json(['message' => $message], 422));
        }

        return $cents;
    }

    private function cutoffAt(SavingsBox $box): Carbon
    {
        return $box->cutoff_at
            ? Carbon::parse($box->cutoff_at)
            : Carbon::parse($box->delivery_date)->endOfDay();
    }

    private function decoratePlanUsage(iterable $boxes, Carbon $moment): void
    {
        foreach ($boxes as $box) {
            $box->setAttribute('accepts_deposits', $box->status === 'active' && $this->cutoffAt($box)->gt($moment));

            foreach ($box->accounts as $account) {
                if ($box->benefit_mode !== 'final_balance_simple') {
                    continue;
                }

                foreach ($this->planService->accountUsage($box, $account, $moment) as $key => $value) {
                    $account->setAttribute($key, $value);
                }
            }
        }
    }
}
