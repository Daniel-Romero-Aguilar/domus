<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\SavingsBox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavingsBoxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $parent = $request->user();
        if (! $parent || $parent->role !== 'parent') {
            return response()->json(['message' => 'Only parent users can view savings boxes.'], 403);
        }

        $boxes = SavingsBox::query()
            ->with(['members:id,name,username'])
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->get();

        return response()->json(['savings_boxes' => $boxes]);
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

        if ($validated['audience'] === 'specific') {
            if ($memberIds->isEmpty()) {
                return response()->json(['message' => 'Selecciona al menos un integrante.'], 422);
            }

            $familyMemberCount = FamilyMember::query()
                ->where('parent_user_id', $parent->id)
                ->whereIn('user_id', $memberIds)
                ->count();

            if ($familyMemberCount !== $memberIds->count()) {
                return response()->json(['message' => 'One or more selected members do not belong to your family.'], 422);
            }
        }

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
            $box->members()->sync($memberIds->all());
        }

        return response()->json([
            'message' => 'Savings box created successfully.',
            'savings_box' => $box->load(['members:id,name,username']),
        ], 201);
    }
}
