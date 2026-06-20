<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\ChildGoal;
use App\Support\BalanceHelper;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChildGoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can view goals.'], 403);
        }

        $goals = ChildGoal::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (ChildGoal $goal) => $this->presentGoal($goal))
            ->values();

        return response()->json([
            'goals' => $goals,
            'balance_cents' => $user->balance_cents,
            'balance_display' => $user->balance_display,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can create goals.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'target_amount' => ['nullable', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        $targetAmountCents = null;

        if (! empty($validated['target_amount'])) {
            try {
                $targetAmountCents = BalanceHelper::parseMoneyToCents($validated['target_amount']);
            } catch (\InvalidArgumentException $exception) {
                throw new HttpResponseException(response()->json([
                    'message' => 'La meta debe escribirse como dinero normal, con hasta 2 decimales.',
                ], 422));
            }

            if ($targetAmountCents < 1) {
                return response()->json(['message' => 'La meta debe ser mayor a cero.'], 422);
            }
        }

        $goal = ChildGoal::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'target_amount_cents' => $targetAmountCents,
            'saved_amount_cents' => 0,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Meta creada correctamente.',
            'goal' => $this->presentGoal($goal),
        ], 201);
    }

    public function deposit(Request $request, ChildGoal $goal): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can save into goals.'], 403);
        }

        if ((int) $goal->user_id !== (int) $user->id) {
            return response()->json(['message' => 'This goal does not belong to you.'], 403);
        }

        if ($goal->status !== 'active') {
            return response()->json(['message' => 'Solo puedes ahorrar en metas activas.'], 422);
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
            return response()->json(['message' => 'Debes guardar un monto mayor a cero.'], 422);
        }

        $payload = DB::transaction(function () use ($user, $goal, $amountCents) {
            $goal = ChildGoal::query()->whereKey($goal->id)->lockForUpdate()->firstOrFail();
            $balance = $this->lockBalance($user->id);

            if ((int) $balance->amount < $amountCents) {
                throw new HttpResponseException(response()->json([
                    'message' => 'No tienes saldo suficiente para guardar ese dinero.',
                ], 422));
            }

            $balance->amount = (int) $balance->amount - $amountCents;
            $balance->save();

            $balance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'goal_deposit',
                'note' => 'Meta: '.$goal->name,
                'resulting_balance' => $balance->amount,
            ]);

            $goal->saved_amount_cents = (int) $goal->saved_amount_cents + $amountCents;
            $goal->save();

            return [
                'goal' => $this->presentGoal($goal->fresh()),
                'balance_cents' => (int) $balance->amount,
                'balance_display' => '$'.number_format(((int) $balance->amount) / 100, 2, '.', ','),
            ];
        });

        return response()->json([
            'message' => 'Dinero guardado en tu meta.',
            'data' => $payload,
        ]);
    }

    public function withdraw(Request $request, ChildGoal $goal): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can withdraw from goals.'], 403);
        }

        if ((int) $goal->user_id !== (int) $user->id) {
            return response()->json(['message' => 'This goal does not belong to you.'], 403);
        }

        if ($goal->status === 'canceled') {
            return response()->json(['message' => 'No puedes retirar de una meta cancelada.'], 422);
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
            return response()->json(['message' => 'Debes retirar un monto mayor a cero.'], 422);
        }

        $payload = DB::transaction(function () use ($user, $goal, $amountCents) {
            $goal = ChildGoal::query()->whereKey($goal->id)->lockForUpdate()->firstOrFail();

            if ((int) $goal->saved_amount_cents < $amountCents) {
                throw new HttpResponseException(response()->json([
                    'message' => 'No tienes suficiente dinero guardado en esta meta.',
                ], 422));
            }

            $balance = $this->lockBalance($user->id);
            $goal->saved_amount_cents = (int) $goal->saved_amount_cents - $amountCents;
            $goal->save();

            $balance->amount = (int) $balance->amount + $amountCents;
            $balance->save();

            $balance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'goal_withdrawal',
                'note' => 'Retiro de meta: '.$goal->name,
                'resulting_balance' => $balance->amount,
            ]);

            return [
                'goal' => $this->presentGoal($goal->fresh()),
                'balance_cents' => (int) $balance->amount,
                'balance_display' => '$'.number_format(((int) $balance->amount) / 100, 2, '.', ','),
            ];
        });

        return response()->json([
            'message' => 'Dinero retirado de tu meta.',
            'data' => $payload,
        ]);
    }

    public function complete(Request $request, ChildGoal $goal): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can complete goals.'], 403);
        }

        if ((int) $goal->user_id !== (int) $user->id) {
            return response()->json(['message' => 'This goal does not belong to you.'], 403);
        }

        if ($goal->status !== 'active') {
            return response()->json(['message' => 'Solo puedes completar metas activas.'], 422);
        }

        if ($goal->target_amount_cents !== null && (int) $goal->saved_amount_cents < (int) $goal->target_amount_cents) {
            return response()->json([
                'message' => 'Todavia no alcanzas el ahorro necesario para completar esta meta.',
            ], 422);
        }

        $goal->status = 'completed';
        $goal->completed_at = now();
        $goal->save();

        return response()->json([
            'message' => 'Meta completada correctamente.',
            'goal' => $this->presentGoal($goal->fresh()),
        ]);
    }

    public function cancel(Request $request, ChildGoal $goal): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can cancel goals.'], 403);
        }

        if ((int) $goal->user_id !== (int) $user->id) {
            return response()->json(['message' => 'This goal does not belong to you.'], 403);
        }

        if ($goal->status === 'canceled') {
            return response()->json(['message' => 'Esta meta ya fue cancelada.'], 422);
        }

        $payload = DB::transaction(function () use ($user, $goal) {
            $goal = ChildGoal::query()->whereKey($goal->id)->lockForUpdate()->firstOrFail();
            $balance = $this->lockBalance($user->id);
            $refundCents = (int) $goal->saved_amount_cents;

            if ($refundCents > 0) {
                $balance->amount = (int) $balance->amount + $refundCents;
                $balance->save();

                $balance->movements()->create([
                    'amount_added' => $refundCents,
                    'movement_type' => 'goal_cancel_refund',
                    'note' => 'Cancelacion de meta: '.$goal->name,
                    'resulting_balance' => $balance->amount,
                ]);
            }

            $goal->saved_amount_cents = 0;
            $goal->status = 'canceled';
            $goal->canceled_at = now();
            $goal->save();

            return [
                'goal' => $this->presentGoal($goal->fresh()),
                'balance_cents' => (int) $balance->amount,
                'balance_display' => '$'.number_format(((int) $balance->amount) / 100, 2, '.', ','),
            ];
        });

        return response()->json([
            'message' => 'Meta cancelada y dinero devuelto a tu saldo.',
            'data' => $payload,
        ]);
    }

    private function presentGoal(ChildGoal $goal): array
    {
        $targetAmountCents = $goal->target_amount_cents === null ? null : (int) $goal->target_amount_cents;
        $savedAmountCents = (int) $goal->saved_amount_cents;
        $progressPercent = $targetAmountCents && $targetAmountCents > 0
            ? min(($savedAmountCents / $targetAmountCents) * 100, 100)
            : null;

        return [
            'id' => (int) $goal->id,
            'name' => $goal->name,
            'description' => $goal->description,
            'target_amount_cents' => $targetAmountCents,
            'target_amount_display' => $targetAmountCents === null ? null : '$'.number_format($targetAmountCents / 100, 2, '.', ','),
            'saved_amount_cents' => $savedAmountCents,
            'saved_amount_display' => '$'.number_format($savedAmountCents / 100, 2, '.', ','),
            'status' => $goal->status,
            'progress_percent' => $progressPercent,
            'can_complete' => $goal->status === 'active' && ($targetAmountCents === null || $savedAmountCents >= $targetAmountCents),
            'completed_at' => $goal->completed_at?->toISOString(),
            'canceled_at' => $goal->canceled_at?->toISOString(),
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
