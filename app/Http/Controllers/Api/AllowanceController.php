<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allowance;
use App\Models\FamilyMember;
use App\Services\AllowanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AllowanceController extends Controller
{
    public function __construct(private readonly AllowanceService $allowanceService)
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

        return response()->json(['allowances' => $allowances]);
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

        $allowance = Allowance::create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $childId,
            'amount_cents' => $amountCents,
            'frequency' => $validated['frequency'],
            'start_at' => $startAt->toDateString(),
            'next_run_at' => $nextRunAt->toDateTimeString(),
            'first_payment_immediate' => $firstImmediate,
            'status' => 'pending',
        ]);

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
