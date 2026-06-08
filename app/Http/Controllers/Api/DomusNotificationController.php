<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DomusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomusNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if (! in_array($user->role, ['parent', 'child', 'member'], true)) {
            return response()->json(['message' => 'Only family users can view notifications.'], 403);
        }

        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        $notifications = DomusNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json(['notifications' => $notifications]);
    }
}
