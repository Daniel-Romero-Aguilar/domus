<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\FamilyTransfer;
use App\Services\DomusNotificationService;
use App\Support\BalanceHelper;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function __construct(private readonly DomusNotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $parent = $request->user();

        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can view transfers.'], 403);
        }

        $transfers = FamilyTransfer::query()
            ->with(['child:id,name,username', 'parent:id,name'])
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->limit(15)
            ->get();

        return response()->json(['transfers' => $transfers]);
    }

    public function store(Request $request): JsonResponse
    {
        $parent = $request->user();

        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can send money.'], 403);
        }

        $validated = $request->validate([
            'child_user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', $request->input('idempotency_key', '')));
        if ($idempotencyKey === '') {
            return response()->json(['message' => 'Idempotency-Key is required.'], 422);
        }

        if (strlen($idempotencyKey) > 120) {
            return response()->json(['message' => 'Idempotency-Key is too long.'], 422);
        }

        $childId = (int) $validated['child_user_id'];
        $belongsToParent = FamilyMember::query()
            ->where('parent_user_id', $parent->id)
            ->where('user_id', $childId)
            ->exists();

        if (! $belongsToParent) {
            return response()->json(['message' => 'Selected member does not belong to your family.'], 422);
        }

        if ($childId === (int) $parent->id) {
            return response()->json(['message' => 'You cannot transfer money to yourself.'], 422);
        }

        try {
            $payload = DB::transaction(function () use ($parent, $childId, $validated, $idempotencyKey) {
                $existingTransfer = FamilyTransfer::query()
                    ->with(['parent:id,name', 'child:id,name,username'])
                    ->where('parent_user_id', $parent->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingTransfer) {
                    return $this->formatTransferResponse($existingTransfer, true);
                }

                $amountCents = BalanceHelper::parseMoneyToCents($validated['amount']);

                $transfer = FamilyTransfer::create([
                    'parent_user_id' => $parent->id,
                    'child_user_id' => $childId,
                    'amount_cents' => $amountCents,
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'processing',
                ]);

                $parentBalance = Balance::query()
                    ->where('user_id', $parent->id)
                    ->lockForUpdate()
                    ->first();

                if (! $parentBalance) {
                    $parentBalance = Balance::create([
                        'user_id' => $parent->id,
                        'amount' => 0,
                    ]);
                    $parentBalance = Balance::query()
                        ->where('user_id', $parent->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $childBalance = Balance::query()
                    ->where('user_id', $childId)
                    ->lockForUpdate()
                    ->first();

                if (! $childBalance) {
                    $childBalance = Balance::create([
                        'user_id' => $childId,
                        'amount' => 0,
                    ]);
                    $childBalance = Balance::query()
                        ->where('user_id', $childId)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $amountCents = (int) $transfer->amount_cents;

                if ((int) $parentBalance->amount < $amountCents) {
                    $transfer->fill([
                        'status' => 'failed',
                        'failure_reason' => 'Fondos insuficientes',
                        'parent_balance_before' => (int) $parentBalance->amount,
                        'parent_balance_after' => (int) $parentBalance->amount,
                        'child_balance_before' => (int) $childBalance->amount,
                        'child_balance_after' => (int) $childBalance->amount,
                        'executed_at' => null,
                    ]);
                    $transfer->save();

                    return $this->formatTransferResponse($transfer->fresh(['parent:id,name', 'child:id,name,username']), false);
                }

                $parentBalanceBefore = (int) $parentBalance->amount;
                $childBalanceBefore = (int) $childBalance->amount;

                $parentBalance->amount = $parentBalanceBefore - $amountCents;
                $parentBalance->save();

                $parentBalance->movements()->create([
                    'amount_added' => $amountCents,
                    'movement_type' => 'transfer_debit',
                    'note' => 'Transfer sent to child',
                    'resulting_balance' => $parentBalance->amount,
                ]);

                $childBalance->amount = $childBalanceBefore + $amountCents;
                $childBalance->save();

                $childBalance->movements()->create([
                    'amount_added' => $amountCents,
                    'movement_type' => 'transfer_credit',
                    'note' => 'Transfer received from parent',
                    'resulting_balance' => $childBalance->amount,
                ]);

                $transfer->fill([
                    'status' => 'completed',
                    'parent_balance_before' => $parentBalanceBefore,
                    'parent_balance_after' => (int) $parentBalance->amount,
                    'child_balance_before' => $childBalanceBefore,
                    'child_balance_after' => (int) $childBalance->amount,
                    'executed_at' => now()->startOfSecond(),
                    'failure_reason' => null,
                ]);
                $transfer->save();

                $completedTransfer = $transfer->fresh(['parent:id,name', 'child:id,name,username']);
                $amountText = $this->notifications->money($amountCents);
                $childName = $completedTransfer->child?->name ?? 'un integrante';
                $parentName = $completedTransfer->parent?->name ?? 'tu familia';

                $this->notifications->recordForParent(
                    $parent->id,
                    'envio',
                    'dar_dinero',
                    'Enviaste '.$amountText.' a '.$childName.'.'
                );
                $this->notifications->recordForMember(
                    $childId,
                    'recepcion',
                    'dar_dinero',
                    'Recibiste '.$amountText.' de '.$parentName.'.'
                );

                return $this->formatTransferResponse($completedTransfer, false);
            });
        } catch (QueryException $exception) {
            if ((string) ($exception->getCode() ?? '') !== '23000') {
                throw $exception;
            }

            $existingTransfer = FamilyTransfer::query()
                ->with(['parent:id,name', 'child:id,name,username'])
                ->where('parent_user_id', $parent->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingTransfer) {
                $existingPayload = $this->formatTransferResponse($existingTransfer, true);
                return response()->json($existingPayload, $existingPayload['executed'] ? 200 : 422);
            }

            throw $exception;
        }

        $statusCode = $payload['executed']
            ? ($payload['created'] ? 201 : 200)
            : 422;

        return response()->json($payload, $statusCode);
    }

    private function formatTransferResponse(FamilyTransfer $transfer, bool $alreadyProcessed): array
    {
        $message = match ($transfer->status) {
            'completed' => $alreadyProcessed ? 'Esta transferencia ya habia sido procesada.' : 'Dinero enviado correctamente.',
            'failed' => 'Fondos insuficientes. No se pudo enviar el dinero.',
            default => 'Transferencia registrada.',
        };

        return [
            'message' => $message,
            'created' => ! $alreadyProcessed,
            'transfer' => $transfer,
            'remaining_parent_balance' => $transfer->parent_balance_after,
            'executed' => $transfer->status === 'completed',
        ];
    }
}
