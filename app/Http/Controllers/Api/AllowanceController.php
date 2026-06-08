<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allowance;
use App\Models\FamilyMember;
use App\Services\AllowanceService;
use App\Services\DomusNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AllowanceController extends Controller
{
    public function __construct(
        private readonly AllowanceService $allowanceService,
        private readonly DomusNotificationService $notifications
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can view allowances.'], 403);
        }

        $allowances = Allowance::query()
            ->with(['child:id,name,username', 'parent:id,name', 'latestPayment'])
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->get();

        $now = Carbon::now()->startOfSecond();
        $summary = [
            'pending_due_payments' => 0,
            'pending_due_payments_capped' => false,
            'allowances_with_pending_due_payments' => 0,
            'paused_due_allowances' => 0,
        ];

        $allowances->each(function (Allowance $allowance) use ($now, &$summary): void {
            $preview = $this->allowanceService->duePaymentPreview($allowance, $now);
            $dueCount = (int) $preview['count'];
            $isPaused = $allowance->status === 'paused';

            $allowance->setAttribute('due_payments_count', $dueCount);
            $allowance->setAttribute('due_payments_count_capped', (bool) $preview['capped']);
            $allowance->setAttribute('has_due_payments', $dueCount > 0);

            if ($dueCount < 1) {
                return;
            }

            if ($isPaused) {
                $summary['paused_due_allowances']++;

                return;
            }

            $summary['pending_due_payments'] += $dueCount;
            $summary['pending_due_payments_capped'] = $summary['pending_due_payments_capped'] || (bool) $preview['capped'];
            $summary['allowances_with_pending_due_payments']++;
        });

        return response()->json([
            'allowances' => $allowances,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can create allowances.'], 403);
        }

        $validated = $request->validate([
            'child_user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'string', 'regex:/^\d+(?:[.,]\d{1,2})?$/'],
            'frequency' => ['required', 'in:daily,weekly,monthly,ten_seconds'],
            'first_payment_immediate' => ['required', 'boolean'],
            'start_at' => ['required_if:first_payment_immediate,false', 'nullable', 'date'],
        ]);

        $childId = (int) $validated['child_user_id'];
        $belongsToParent = FamilyMember::query()
            ->where('parent_user_id', $parent->id)
            ->where('user_id', $childId)
            ->exists();

        if (! $belongsToParent) {
            return response()->json(['message' => 'Selected member does not belong to your family.'], 422);
        }

        $firstImmediate = (bool) $validated['first_payment_immediate'];
        $startAt = $firstImmediate
            ? Carbon::today()
            : Carbon::parse($validated['start_at'])->startOfDay();
        $amountCents = $this->moneyToCents($validated['amount']);
        $nextRunAt = $firstImmediate
            ? Carbon::now()->startOfSecond()
            : $startAt->copy()->startOfDay();

        $allowance = DB::transaction(function () use ($parent, $childId, $amountCents, $validated, $startAt, $nextRunAt, $firstImmediate): Allowance {
            $allowance = Allowance::create([
                'parent_user_id' => $parent->id,
                'child_user_id' => $childId,
                'amount_cents' => $amountCents,
                'frequency' => $validated['frequency'],
                'start_at' => $startAt->toDateString(),
                'next_run_at' => $nextRunAt->toDateTimeString(),
                'first_payment_immediate' => $firstImmediate,
                'status' => 'pending',
            ])->load('child:id,name,username');

            $amountText = $this->notifications->money($amountCents);
            $childName = $allowance->child?->name ?? 'un integrante';

            $this->notifications->recordForParent(
                $parent->id,
                'creacion',
                'mesadas',
                'Creaste una mesada de '.$amountText.' para '.$childName.'.'
            );
            $this->notifications->recordForMember(
                $childId,
                'creacion',
                'mesadas',
                'Te configuraron una mesada de '.$amountText.'.'
            );

            return $allowance;
        });

        $result = null;
        if ($firstImmediate) {
            $result = $this->allowanceService->execute($allowance->id);
            $allowance = $result['allowance'];
        }

        return response()->json([
            'message' => $result['message'] ?? 'Allowance created successfully.',
            'allowance' => $allowance->load(['child:id,name,username', 'parent:id,name']),
            'result' => $result,
        ], 201);
    }

    public function execute(Request $request, Allowance $allowance): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can execute allowances.'], 403);
        }

        if ($allowance->parent_user_id !== $parent->id) {
            return response()->json(['message' => 'Allowance does not belong to your account.'], 403);
        }

        $result = $this->allowanceService->execute($allowance->id, true);

        return response()->json($result);
    }

    private function moneyToCents(string|int|float $value): int
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new HttpResponseException(response()->json([
                'message' => 'El monto debe escribirse como dinero normal, con hasta 2 decimales.',
            ], 422));
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + ((int) $fraction);
    }
}
