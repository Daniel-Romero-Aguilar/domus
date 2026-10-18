<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminSubscriptionController extends Controller
{
    public function __invoke(): View
    {
        $admin = Auth::guard('admin_web')->user();
        abort_unless($admin instanceof Admin, 403);

        $users = DB::table('users')
            ->select('id', 'name', 'email', 'username')
            ->orderByDesc('id')
            ->paginate(25);

        $subscriptions = DB::table('billing_demo_subscriptions')
            ->select('id', 'user_id', 'product_id', 'google_state', 'acknowledged', 'expires_at', 'verified_at')
            ->where('product_id', config('billing_demo.product_id'))
            ->whereIn('user_id', $users->getCollection()->pluck('id'))
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $now = CarbonImmutable::now('UTC');
        $entries = $users->getCollection()->map(function (object $user) use ($subscriptions, $now): array {
            $subscription = $subscriptions->get($user->id);
            $expiresAt = $subscription?->expires_at
                ? CarbonImmutable::parse($subscription->expires_at, 'UTC')
                : null;
            $eligible = $expiresAt?->greaterThan($now) && in_array($subscription->google_state, [
                'SUBSCRIPTION_STATE_ACTIVE',
                'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
                'SUBSCRIPTION_STATE_CANCELED',
            ], true);

            if ($subscription?->google_state === 'DEMO_CONFIRMED') {
                $status = 'Simulada';
                $statusClass = 'da-status--pending';
            } elseif ($eligible && $subscription->acknowledged === null) {
                $status = 'Pendiente de comprobar';
                $statusClass = 'da-status--pending';
            } elseif ($eligible && (bool) $subscription->acknowledged) {
                $status = 'Suscrito';
                $statusClass = 'da-status--active';
            } else {
                $status = 'No suscrito';
                $statusClass = 'da-status--rejected';
            }

            return [
                'user' => $user,
                'status' => $status,
                'status_class' => $statusClass,
                'product_id' => $subscription?->google_state === 'DEMO_CONFIRMED' ? null : $subscription?->product_id,
                'expires_at' => $subscription?->google_state === 'DEMO_CONFIRMED' ? null : $expiresAt,
                'verified_at' => $subscription?->google_state === 'DEMO_CONFIRMED' || ! $subscription?->verified_at
                    ? null
                    : CarbonImmutable::parse($subscription->verified_at, 'UTC'),
            ];
        });

        return view('admin-subscriptions', [
            'admin' => $admin,
            'users' => $users,
            'entries' => $entries,
        ]);
    }
}
