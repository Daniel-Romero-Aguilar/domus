<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms_accepted' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Balance::create([
            'user_id' => $user->id,
            'amount' => 0,
        ]);

        $token = $user->createToken('parent-auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Parent account created successfully.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = $validated['login'];
        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $user->createToken('parent-auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            Log::error('AUTH_ME_FAILED: No authenticated user resolved from token.', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthenticated context in /me.'], 401);
        }

        if (! in_array($user->role, ['parent', 'child', 'member'], true)) {
            Log::error('AUTH_ME_FAILED: Invalid user role.', [
                'user_id' => $user->id,
                'role' => $user->role,
                'path' => $request->path(),
            ]);
            return response()->json(['message' => 'Invalid role for authenticated user.'], 422);
        }

        if ($user->role !== 'parent') {
            $user->load('balance');
        }
        $familyRelation = FamilyMember::query()
            ->where('user_id', $user->id)
            ->with('parent:id,name')
            ->first();

        $isFamilyMember = (bool) $familyRelation;
        $parentAdminName = $familyRelation?->parent?->name;
        return response()->json([
            'user' => $user,
            'parent_admin_name' => $parentAdminName,
            'is_family_member' => $isFamilyMember,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken) {
            $accessToken->delete();
        }

        return response()->json([
            'message' => 'Token revoked.',
        ]);
    }
}
