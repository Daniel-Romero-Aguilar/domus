<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BillingSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingDemoController extends Controller
{
    public function __construct(private BillingSubscriptionService $billing) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless(config('billing_demo.enabled'), 404);
        $account = $request->user()->accessAccount();
        abort_unless($account, 403, 'No hay una cuenta familiar asociada.');
        if ($record = $account->currentSubscription) {
            $this->billing->refresh($record);
        }

        return $this->response($request, $account);
    }

    public function verify(Request $request): JsonResponse
    {
        abort_unless(config('billing_demo.enabled'), 404);
        $validated = $request->validate([
            'product_id' => ['required', 'string', Rule::in([config('billing_demo.product_id')])],
            'purchase_token' => ['required', 'string', 'max:4096'],
        ]);
        $account = $request->user()->accessAccount();
        abort_unless($account, 403, 'No hay una cuenta familiar asociada.');
        $this->billing->verify($account, $validated['purchase_token']);

        return $this->response($request, $account);
    }

    private function response(Request $request, User $account): JsonResponse
    {
        $account->unsetRelation('currentSubscription');
        $request->user()->unsetRelation('currentSubscription');
        $record = $account->currentSubscription;
        $simulated = config('billing_demo.dummy_verification') && $record?->google_state === 'DEMO_CONFIRMED'
            && ($record->expires_at?->isFuture() ?? false);

        return response()->json(['data' => [
            'account_id' => $this->billing->accountId($account),
            'subscribed' => $simulated || ($record?->isPremium() ?? false),
            'auto_renew_enabled' => $account->auto_renew_enabled,
            'is_enabled_functions' => $account->isEnabledFunctions(),
            'product_id' => config('billing_demo.product_id'),
            'expires_at' => $record?->expires_at?->toIso8601String(),
            'sync_pending' => $record?->sync_started_at !== null && $record?->sync_stopped_at === null,
            'is_premium_grace_period' => $account->is_premium_grace_period,
            'premium_grace_ends_at' => $account->premium_grace_ends_at,
        ]]);
    }
}
