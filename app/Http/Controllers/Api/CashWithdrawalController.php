<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\CashWithdrawal;
use App\Models\FamilyMember;
use App\Services\DomusNotificationService;
use App\Support\BalanceHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashWithdrawalController extends Controller
{
    public function __construct(private readonly DomusNotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['parent', 'child', 'member'], true)) {
            return response()->json(['message' => 'Only family users can view withdrawals.'], 403);
        }

        $query = CashWithdrawal::query()
            ->with(['parent:id,name', 'child:id,name,username', 'initiatedBy:id,name,role'])
            ->latest();

        if ($user->role === 'parent') {
            $items = $query->where('parent_user_id', $user->id)->get();
            $requests = $items->where('status', 'pending_parent')->values();
            $withdrawals = $items->where('initiated_by_user_id', $user->id)->values();
        } else {
            $items = $query->where('child_user_id', $user->id)->get();
            $requests = $items->where('status', 'pending_child')->values();
            $withdrawals = $items->where('initiated_by_user_id', $user->id)->values();
        }

        return response()->json([
            'withdrawals' => $withdrawals->map(fn (CashWithdrawal $withdrawal) => $this->present($withdrawal))->values(),
            'requests' => $requests->map(fn (CashWithdrawal $withdrawal) => $this->present($withdrawal))->values(),
            'all' => $items->map(fn (CashWithdrawal $withdrawal) => $this->present($withdrawal))->values(),
        ]);
    }

    public function parentStore(Request $request): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can start withdrawals.'], 403);
        }

        $validated = $request->validate([
            'child_user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        $childId = (int) $validated['child_user_id'];
        if (! $this->belongsToParent($parent->id, $childId)) {
            return response()->json(['message' => 'Selected member does not belong to your family.'], 422);
        }

        $withdrawal = DB::transaction(function () use ($parent, $childId, $validated) {
            $amountCents = BalanceHelper::parseMoneyToCents($validated['amount']);
            $withdrawal = $this->reserveWithdrawal($parent->id, $childId, $parent->id, $amountCents, 'pending_child');
            $withdrawal->parent_approved_at = now();
            $withdrawal->save();

            $amountText = $this->notifications->money($amountCents);
            $this->notifications->recordForParent($parent->id, 'retiro', 'retirar_dinero', 'Solicitaste retirar '.$amountText.' para un integrante.');
            $this->notifications->recordForMember($childId, 'retiro', 'retirar_dinero', 'Tu padre solicito retirar '.$amountText.' de tu saldo. Revisa para aceptar.');

            return $withdrawal->fresh(['parent:id,name', 'child:id,name,username', 'initiatedBy:id,name,role']);
        });

        return response()->json([
            'message' => 'Retiro iniciado. Falta aceptacion del integrante.',
            'withdrawal' => $this->present($withdrawal),
            'child_balance' => $this->balanceSnapshot((int) $withdrawal->child_user_id),
        ], 201);
    }

    public function childStore(Request $request): JsonResponse
    {
        $child = $request->user();
        if (! $child || ! in_array($child->role, ['child', 'member'], true)) {
            return response()->json(['message' => 'Only child/member users can request withdrawals.'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
        ]);

        $parentId = (int) FamilyMember::query()
            ->where('user_id', $child->id)
            ->value('parent_user_id');

        if (! $parentId) {
            return response()->json(['message' => 'Family member record not found.'], 422);
        }

        $withdrawal = DB::transaction(function () use ($child, $parentId, $validated) {
            $amountCents = BalanceHelper::parseMoneyToCents($validated['amount']);
            $withdrawal = $this->reserveWithdrawal($parentId, $child->id, $child->id, $amountCents, 'pending_parent');
            $withdrawal->child_approved_at = now();
            $withdrawal->save();

            $amountText = $this->notifications->money($amountCents);
            $this->notifications->recordForMember($child->id, 'retiro', 'retirar_dinero', 'Solicitaste retirar '.$amountText.' de tu saldo.');
            $this->notifications->recordForParent($parentId, 'retiro', 'retirar_dinero', $child->name.' solicito retirar '.$amountText.'.');

            return $withdrawal->fresh(['parent:id,name', 'child:id,name,username', 'initiatedBy:id,name,role']);
        });

        return response()->json([
            'message' => 'Retiro solicitado. Falta aceptacion del padre.',
            'withdrawal' => $this->present($withdrawal),
            'child_balance' => $this->balanceSnapshot((int) $withdrawal->child_user_id),
        ], 201);
    }

    public function accept(Request $request, CashWithdrawal $cashWithdrawal): JsonResponse
    {
        $user = $request->user();

        $withdrawal = DB::transaction(function () use ($user, $cashWithdrawal) {
            $withdrawal = CashWithdrawal::query()
                ->whereKey($cashWithdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status === 'pending_parent') {
                if (! $user || $user->role !== 'parent' || (int) $withdrawal->parent_user_id !== (int) $user->id) {
                    abort(response()->json(['message' => 'Only the parent can accept this withdrawal.'], 403));
                }

                $withdrawal->parent_approved_at = now();
            } elseif ($withdrawal->status === 'pending_child') {
                if (! $user || ! in_array($user->role, ['child', 'member'], true) || (int) $withdrawal->child_user_id !== (int) $user->id) {
                    abort(response()->json(['message' => 'Only the member can accept this withdrawal.'], 403));
                }

                $withdrawal->child_approved_at = now();
            } else {
                abort(response()->json(['message' => 'This withdrawal cannot be accepted.'], 422));
            }

            $withdrawal->status = 'completed';
            $withdrawal->completed_at = now();
            $withdrawal->save();

            $amountText = $this->notifications->money((int) $withdrawal->amount_cents);
            $childName = $withdrawal->child()->value('name') ?? 'El integrante';
            $this->notifications->recordForMember($withdrawal->child_user_id, 'retiro', 'retirar_dinero', 'Retiraste '.$amountText.' de tu saldo.');
            $this->notifications->recordForParent($withdrawal->parent_user_id, 'retiro', 'retirar_dinero', $childName.' retiro '.$amountText.' de su saldo.');

            return $withdrawal->fresh(['parent:id,name', 'child:id,name,username', 'initiatedBy:id,name,role']);
        });

        return response()->json([
            'message' => 'Retiro completado.',
            'withdrawal' => $this->present($withdrawal),
            'child_balance' => $this->balanceSnapshot((int) $withdrawal->child_user_id),
        ]);
    }

    public function cancel(Request $request, CashWithdrawal $cashWithdrawal): JsonResponse
    {
        $user = $request->user();

        $withdrawal = DB::transaction(function () use ($user, $cashWithdrawal) {
            $withdrawal = CashWithdrawal::query()
                ->whereKey($cashWithdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->canTouch($user, $withdrawal)) {
                abort(response()->json(['message' => 'You cannot cancel this withdrawal.'], 403));
            }

            if (! in_array($withdrawal->status, ['pending_parent', 'pending_child'], true)) {
                abort(response()->json(['message' => 'This withdrawal cannot be canceled.'], 422));
            }

            $balance = Balance::query()
                ->where('user_id', $withdrawal->child_user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balance->amount = (int) $balance->amount + (int) $withdrawal->amount_cents;
            $balance->save();
            $balance->movements()->create([
                'amount_added' => (int) $withdrawal->amount_cents,
                'movement_type' => 'cash_withdrawal_refund',
                'note' => 'Cash withdrawal canceled',
                'resulting_balance' => $balance->amount,
            ]);

            $withdrawal->status = 'canceled';
            $withdrawal->canceled_at = now();
            $withdrawal->save();

            return $withdrawal->fresh(['parent:id,name', 'child:id,name,username', 'initiatedBy:id,name,role']);
        });

        return response()->json([
            'message' => 'Retiro cancelado y saldo devuelto.',
            'withdrawal' => $this->present($withdrawal),
            'child_balance' => $this->balanceSnapshot((int) $withdrawal->child_user_id),
        ]);
    }

    private function reserveWithdrawal(int $parentId, int $childId, int $initiatorId, int $amountCents, string $status): CashWithdrawal
    {
        if ($amountCents < 1) {
            abort(response()->json(['message' => 'El monto debe ser mayor a cero.'], 422));
        }

        $balance = Balance::query()->firstOrCreate(
            ['user_id' => $childId],
            ['amount' => 0]
        );

        $balance = Balance::query()
            ->where('user_id', $childId)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $balance->amount < $amountCents) {
            abort(response()->json(['message' => 'El integrante no tiene saldo suficiente para este retiro.'], 422));
        }

        $balance->amount = (int) $balance->amount - $amountCents;
        $balance->save();
        $balance->movements()->create([
            'amount_added' => $amountCents,
            'movement_type' => 'cash_withdrawal_hold',
            'note' => 'Cash withdrawal reserved',
            'resulting_balance' => $balance->amount,
        ]);

        return CashWithdrawal::create([
            'parent_user_id' => $parentId,
            'child_user_id' => $childId,
            'initiated_by_user_id' => $initiatorId,
            'amount_cents' => $amountCents,
            'status' => $status,
        ]);
    }

    private function belongsToParent(int $parentId, int $childId): bool
    {
        return FamilyMember::query()
            ->where('parent_user_id', $parentId)
            ->where('user_id', $childId)
            ->exists();
    }

    private function canTouch($user, CashWithdrawal $withdrawal): bool
    {
        if (! $user) {
            return false;
        }

        return ($user->role === 'parent' && (int) $withdrawal->parent_user_id === (int) $user->id)
            || (in_array($user->role, ['child', 'member'], true) && (int) $withdrawal->child_user_id === (int) $user->id);
    }

    private function present(CashWithdrawal $withdrawal): array
    {
        return [
            'id' => (int) $withdrawal->id,
            'amount_cents' => (int) $withdrawal->amount_cents,
            'amount_display' => $this->notifications->money((int) $withdrawal->amount_cents),
            'status' => $withdrawal->status,
            'status_label' => $this->statusLabel($withdrawal->status),
            'parent' => $withdrawal->parent,
            'child' => $withdrawal->child,
            'initiated_by' => $withdrawal->initiatedBy,
            'parent_approved_at' => $withdrawal->parent_approved_at?->toISOString(),
            'child_approved_at' => $withdrawal->child_approved_at?->toISOString(),
            'completed_at' => $withdrawal->completed_at?->toISOString(),
            'canceled_at' => $withdrawal->canceled_at?->toISOString(),
            'created_at' => $withdrawal->created_at?->toISOString(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending_parent' => 'Esperando aceptacion del padre',
            'pending_child' => 'Esperando aceptacion del integrante',
            'completed' => 'Retirado',
            'canceled' => 'Cancelado',
            default => $status,
        };
    }

    private function balanceSnapshot(int $userId): array
    {
        $amount = (int) (Balance::query()
            ->where('user_id', $userId)
            ->value('amount') ?? 0);

        return [
            'balance_cents' => $amount,
            'balance_display' => BalanceHelper::displayCents($amount),
        ];
    }
}
