<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        $payload = DB::transaction(function () use ($user, $validated) {
            $balance = Balance::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['amount' => 0]
            );

            $balance->refresh();
            $balance->amount = $balance->amount + $validated['amount'];
            $balance->save();

            $movement = $balance->movements()->create([
                'amount_added' => $validated['amount'],
                'movement_type' => 'credit',
                'note' => 'Balance top-up',
                'resulting_balance' => $balance->amount,
            ]);

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
