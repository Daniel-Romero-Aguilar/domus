<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Services\DomusNotificationService;
use App\Support\BalanceHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    public function __construct(private readonly DomusNotificationService $notifications)
    {
    }

    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        $user = $request->user();

        $payload = DB::transaction(function () use ($user, $validated) {
            try {
                $amountCents = BalanceHelper::parseMoneyToCents($validated['amount']);
            } catch (\InvalidArgumentException $exception) {
                throw new HttpResponseException(response()->json([
                    'message' => 'El monto debe escribirse como dinero normal, con hasta 2 decimales.',
                ], 422));
            }

            $balance = Balance::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['amount' => 0]
            );

            $balance->refresh();
            $balance->amount = $balance->amount + $amountCents;
            $balance->save();

            $movement = $balance->movements()->create([
                'amount_added' => $amountCents,
                'movement_type' => 'credit',
                'note' => 'Balance top-up',
                'resulting_balance' => $balance->amount,
            ]);

            $this->notifications->record(
                $user->id,
                'saldo',
                'balance',
                'Agregaste '.$this->notifications->money($amountCents).' a tu saldo.'
            );

            return [
                'balance' => $balance->amount,
                'movement' => [
                    'id' => $movement->id,
                    'amount_added' => $movement->amount_added,
                    'resulting_balance' => $movement->resulting_balance,
                    'created_at' => $movement->created_at,
                ],
            ];
        });

        return response()->json([
            'message' => 'Balance updated.',
            'data' => $payload,
        ]);
    }
}
