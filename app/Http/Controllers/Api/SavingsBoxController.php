<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\SavingsBox;
use App\Models\SavingsBoxAccount;
use App\Services\DomusNotificationService;
use App\Services\SavingsBoxInterestService;
use App\Support\BalanceHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SavingsBoxController extends Controller
{
    public function __construct(
        private readonly SavingsBoxInterestService $interestService,
        private readonly DomusNotificationService $notifications
    )
    {
    }

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

            return response()->json(['savings_boxes' => $boxes]);
        }

        if ($user->role === 'child' || $user->role === 'member') {
            $this->interestService->runDueForUser($user->id);

            $boxes = SavingsBox::query()
                ->with([
                    'parent:id,name',
                    'accounts' => fn ($query) => $query->where('user_id', $user->id)->with('user:id,name,username'),
                ])
                ->where('status', 'active')
                ->whereHas('accounts', fn ($query) => $query->where('user_id', $user->id))
                ->latest()
                ->get();

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
            'delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'annual_gain_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
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

            $familyMemberCount = $familyMemberIds->intersect($memberIds)->count();

            if ($familyMemberCount !== $memberIds->count()) {
                return response()->json(['message' => 'One or more selected members do not belong to your family.'], 422);
            }
        }

        $enabledMemberIds = $validated['audience'] === 'all' ? $familyMemberIds : $memberIds;

        $box = DB::transaction(function () use ($parent, $validated, $enabledMemberIds): SavingsBox {
            $box = SavingsBox::create([
                'parent_user_id' => $parent->id,
                'name' => $validated['name'],
                'delivery_date' => $validated['delivery_date'],
                'annual_gain_percent' => round((float) $validated['annual_gain_percent'], 2),
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
                    'last_interest_accrued_on' => now()->toDateString(),
                    'interest_accrued_until_at' => now(),
                ]);

                $this->notifications->recordForMember(
                    (int) $memberId,
                    'creacion',
                    'cajas_ahorro',
                    'Te habilitaron la caja de ahorro '.$box->name.'.'
                );
            }

            $this->notifications->recordForParent(
                $parent->id,
                'creacion',
                'cajas_ahorro',
                'Creaste la caja de ahorro '.$box->name.' para '.$enabledMemberIds->count().' integrante(s).'
            );

            return $box;
        });

        return response()->json([
            'message' => 'Savings box created successfully.',
            'savings_box' => $box->load(['members:id,name,username', 'accounts.user:id,name,username']),
        ], 201);
    }

    public function deposit(Request $request, SavingsBox $savingsBox): JsonResponse
    {
        $user = $request->user();
        if (! $user || ($user->role !== 'child' && $user->role !== 'member')) {
            return response()->json(['message' => 'Only member users can deposit into savings boxes.'], 403);
        }

        if ($savingsBox->status !== 'active' || Carbon::parse($savingsBox->delivery_date)->startOfDay()->lt(Carbon::today())) {
            return response()->json(['message' => 'Esta caja ya no recibe abonos.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        try {
            $amountCents = BalanceHelper::parseMoneyToCents($validated['amount']);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpResponseException(response()->json([
                'message' => 'El monto debe escribirse como dinero normal, con hasta 2 decimales.',
            ], 422));
        }

        if ($amountCents < 1) {
            return response()->json(['message' => 'El abono debe ser mayor a cero.'], 422);
        }

        $payload = DB::transaction(function () use ($user, $savingsBox, $amountCents) {
            $account = SavingsBoxAccount::query()
                ->where('savings_box_id', $savingsBox->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw new HttpResponseException(response()->json([
                    'message' => 'No tienes acceso a esta caja de ahorro.',
                ], 403));
            }

            $now = now();
            $this->interestService->accrueAccountUntil($account, $now, $savingsBox);
            $account->refresh();

            $balance = Balance::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['amount' => 0]
            );

            $balance = Balance::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ((int) $balance->amount < $amountCents) {
                throw new HttpResponseException(response()->json([
                    'message' => 'No tienes saldo suficiente para hacer este abono.',
                ], 422));
            }

            $balance->amount = (int) $balance->amount - $amountCents;
            $balance->save();

            $balance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'debit',
                'note' => 'Savings box deposit: '.$savingsBox->name,
                'resulting_balance' => $balance->amount,
            ]);

            $principalBefore = (int) $account->principal_cents;
            $earnedBefore = (int) $account->earned_interest_cents;
            $account->principal_cents = $principalBefore + $amountCents;
            $account->save();

            $this->interestService->recordMovement(
                $account,
                $savingsBox,
                'deposit',
                $amountCents,
                $principalBefore,
                $account->principal_cents,
                $earnedBefore,
                $earnedBefore,
                $now,
                'Savings box deposit'
            );

            $amountText = $this->notifications->money($amountCents);
            $this->notifications->recordForMember(
                $user->id,
                'abono',
                'cajas_ahorro',
                'Abonaste '.$amountText.' a la caja '.$savingsBox->name.'.'
            );
            $this->notifications->recordForParent(
                $savingsBox->parent_user_id,
                'abono',
                'cajas_ahorro',
                $user->name.' abono '.$amountText.' a la caja '.$savingsBox->name.'.'
            );

            return [
                'remaining_balance' => $balance->amount,
                'remaining_balance_cents' => (int) $balance->amount,
                'remaining_balance_display' => BalanceHelper::displayCents((int) $balance->amount),
                'account' => $account->fresh(['user:id,name,username']),
            ];
        });

        return response()->json([
            'message' => 'Abono guardado. Desde este momento cuenta para el rendimiento segun el tiempo que permanezca en la caja.',
            'data' => $payload,
            'remaining_balance' => $payload['remaining_balance'],
            'remaining_balance_cents' => $payload['remaining_balance_cents'],
            'remaining_balance_display' => $payload['remaining_balance_display'],
            'account' => $payload['account'],
        ]);
    }

    public function withdraw(Request $request, SavingsBox $savingsBox): JsonResponse
    {
        $user = $request->user();
        if (! $user || ($user->role !== 'child' && $user->role !== 'member')) {
            return response()->json(['message' => 'Only member users can withdraw from savings boxes.'], 403);
        }

        if (! $savingsBox->allow_early_withdrawal) {
            return response()->json(['message' => 'Esta caja no permite retiro anticipado.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        try {
            $amountCents = BalanceHelper::parseMoneyToCents($validated['amount']);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpResponseException(response()->json([
                'message' => 'El monto debe escribirse como dinero normal, con hasta 2 decimales.',
            ], 422));
        }

        if ($amountCents < 1) {
            return response()->json(['message' => 'El retiro debe ser mayor a cero.'], 422);
        }

        $payload = DB::transaction(function () use ($user, $savingsBox, $amountCents) {
            $account = SavingsBoxAccount::query()
                ->where('savings_box_id', $savingsBox->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw new HttpResponseException(response()->json([
                    'message' => 'No tienes acceso a esta caja de ahorro.',
                ], 403));
            }

            $now = now();
            $this->interestService->accrueAccountUntil($account, $now, $savingsBox);
            $account->refresh();

            if ((int) $account->principal_cents < $amountCents) {
                throw new HttpResponseException(response()->json([
                    'message' => 'No tienes suficiente capital disponible en esta caja.',
                ], 422));
            }

            $principalBefore = (int) $account->principal_cents;
            $earnedBefore = (int) $account->earned_interest_cents;
            $account->principal_cents = $principalBefore - $amountCents;
            $account->save();

            $balance = Balance::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['amount' => 0]
            );

            $balance = Balance::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $balance->amount = (int) $balance->amount + $amountCents;
            $balance->save();

            $balance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'credit',
                'note' => 'Savings box early withdrawal: '.$savingsBox->name,
                'resulting_balance' => $balance->amount,
            ]);

            $this->interestService->recordMovement(
                $account,
                $savingsBox,
                'withdrawal',
                $amountCents,
                $principalBefore,
                $account->principal_cents,
                $earnedBefore,
                $earnedBefore,
                $now,
                'Savings box early withdrawal'
            );

            $amountText = $this->notifications->money($amountCents);
            $this->notifications->recordForMember(
                $user->id,
                'retiro',
                'cajas_ahorro',
                'Retiraste '.$amountText.' de la caja '.$savingsBox->name.'.'
            );
            $this->notifications->recordForParent(
                $savingsBox->parent_user_id,
                'retiro',
                'cajas_ahorro',
                $user->name.' retiro '.$amountText.' de la caja '.$savingsBox->name.'.'
            );

            return [
                'remaining_balance' => $balance->amount,
                'remaining_balance_cents' => (int) $balance->amount,
                'remaining_balance_display' => BalanceHelper::displayCents((int) $balance->amount),
                'account' => $account->fresh(['user:id,name,username']),
            ];
        });

        return response()->json([
            'message' => 'Retiro guardado. El dinero retirado deja de generar rendimiento desde este momento.',
            'data' => $payload,
            'remaining_balance' => $payload['remaining_balance'],
            'remaining_balance_cents' => $payload['remaining_balance_cents'],
            'remaining_balance_display' => $payload['remaining_balance_display'],
            'account' => $payload['account'],
        ]);
    }
}
