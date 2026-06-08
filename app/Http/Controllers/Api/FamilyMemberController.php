<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\DomusNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FamilyMemberController extends Controller
{
    public function __construct(private readonly DomusNotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $parent = $request->user();

        if ($parent->role !== 'parent') {
            return response()->json([
                'message' => 'Only parent users can view family members.',
            ], 403);
        }

        $members = FamilyMember::query()
            ->with('user:id,name,username,role')
            ->where('parent_user_id', $parent->id)
            ->latest()
            ->get();

        $loanTargets = $members
            ->filter(fn (FamilyMember $member) => (bool) $member->user)
            ->map(function (FamilyMember $member) {
                return [
                    'family_member_id' => $member->id,
                    'user_id' => $member->user->id,
                    'name' => $member->user->name,
                    'username' => $member->user->username,
                    'role' => $member->user->role,
                    'is_minor' => (bool) $member->is_minor,
                ];
            })
            ->values();

        return response()->json([
            'members' => $members,
            'loan_targets' => $loanTargets,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $parent = $request->user();

        if ($parent->role !== 'parent') {
            return response()->json([
                'message' => 'Only parent users can create family members.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:255', 'alpha_num', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_minor' => ['nullable', 'boolean'],
            'guardian_declaration_accepted' => ['nullable', 'boolean'],
        ]);

        $isMinor = (bool) ($validated['is_minor'] ?? false);
        $guardianDeclarationAccepted = (bool) ($validated['guardian_declaration_accepted'] ?? false);

        if ($isMinor && ! $guardianDeclarationAccepted) {
            return response()->json([
                'message' => 'Guardian declaration is required when registering a minor.',
            ], 422);
        }

        $member = DB::transaction(function () use ($parent, $validated, $isMinor, $guardianDeclarationAccepted) {
            $familyRole = $isMinor ? 'child' : 'member';
            $childUser = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'role' => $familyRole,
                'password' => Hash::make($validated['password']),
            ]);

            Balance::create([
                'user_id' => $childUser->id,
                'amount' => 0,
            ]);

            $member = FamilyMember::create([
                'parent_user_id' => $parent->id,
                'user_id' => $childUser->id,
                'is_minor' => $isMinor,
                'guardian_declaration_accepted' => $guardianDeclarationAccepted,
            ])->load('user:id,name,username,role');

            $this->notifications->recordForParent(
                $parent->id,
                'integrantes',
                'usuarios',
                'Creaste el integrante '.$childUser->name.' (@'.$childUser->username.').'
            );
            $this->notifications->recordForMember(
                $childUser->id,
                'creacion',
                'usuarios',
                'Tu cuenta familiar fue creada.'
            );

            return $member;
        });

        return response()->json([
            'message' => 'Family member created.',
            'member' => $member,
        ], 201);
    }
}
