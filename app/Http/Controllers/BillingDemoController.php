<?php

namespace App\Http\Controllers;

use App\Services\GooglePlayDemoClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BillingDemoController extends Controller
{
    public function __construct(private GooglePlayDemoClient $play) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless(config('billing_demo.enabled'), 404);
        $record = DB::table('billing_demo_subscriptions')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('product_id', config('billing_demo.product_id'))
            ->orderByDesc('verified_at')->orderByDesc('id')->first();

        if (! $record) {
            return $this->response($request);
        }

        if (config('billing_demo.dummy_verification')) {
            if ($record->google_state !== 'DEMO_CONFIRMED' || $record->expires_at === null) {
                return $this->response($request);
            }

            $expiresAt = CarbonImmutable::parse($record->expires_at, 'UTC');
            return $this->response($request, [
                'eligible' => $expiresAt->isFuture(),
                'acknowledged' => true,
                'expires_at' => $expiresAt,
            ]);
        }

        if ($record->google_state === 'DEMO_CONFIRMED') {
            return $this->response($request);
        }

        $purchase = $this->play->lookup(Crypt::decryptString($record->purchase_token));
        if ($purchase === null) {
            DB::table('billing_demo_subscriptions')->where('id', $record->id)->update([
                'google_state' => 'SUBSCRIPTION_STATE_EXPIRED',
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
            return $this->response($request);
        }

        $details = $this->details($request, $purchase);
        DB::table('billing_demo_subscriptions')->where('id', $record->id)->update([
            'google_state' => $details['state'],
            'expires_at' => $details['expires_at']?->toDateTimeString(),
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->response($request, $details);
    }

    public function verify(Request $request): JsonResponse
    {
        abort_unless(config('billing_demo.enabled'), 404);
        $validated = $request->validate([
            'product_id' => ['required', 'string', Rule::in([config('billing_demo.product_id')])],
            'purchase_token' => ['required', 'string', 'max:4096'],
        ]);
        $token = $validated['purchase_token'];
        $hash = hash('sha256', $token);
        $userId = $request->user()->getAuthIdentifier();
        $existing = DB::table('billing_demo_subscriptions')->where('purchase_token_hash', $hash)->first();
        abort_if($existing && (string) $existing->user_id !== (string) $userId, 403, 'Esta compra corresponde a otra cuenta de Domus.');

        if (config('billing_demo.dummy_verification')) {
            $details = [
                'eligible' => true,
                'acknowledged' => true,
                'state' => 'DEMO_CONFIRMED',
                'expires_at' => CarbonImmutable::now('UTC')->addMonthNoOverflow(),
            ];
        } else {
            $purchase = $this->play->lookup($token);
            abort_if($purchase === null, 422, 'El comprobante de Google Play no es válido o ya venció.');
            $details = $this->details($request, $purchase);
            abort_if(in_array($details['state'], [
                'SUBSCRIPTION_STATE_PENDING',
                'SUBSCRIPTION_STATE_PENDING_PURCHASE_CANCELED',
            ], true), 422, 'La compra aún no está confirmada en Google Play.');
        }

        // This table only records the demo. Never update users, roles or access.
        DB::transaction(function () use ($userId, $hash, $token, $details) {
            DB::table('billing_demo_subscriptions')->insertOrIgnore([
                'user_id' => $userId,
                'purchase_token_hash' => $hash,
                'purchase_token' => Crypt::encryptString($token),
                'product_id' => config('billing_demo.product_id'),
                'google_state' => $details['state'],
                'expires_at' => $details['expires_at']?->toDateTimeString(),
                'verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $record = DB::table('billing_demo_subscriptions')->where('purchase_token_hash', $hash)
                ->lockForUpdate()->first();
            abort_unless($record && (string) $record->user_id === (string) $userId, 403, 'Esta compra corresponde a otra cuenta de Domus.');
            DB::table('billing_demo_subscriptions')->where('id', $record->id)->update([
                'google_state' => $details['state'],
                'expires_at' => $details['expires_at']?->toDateTimeString(),
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if (! config('billing_demo.dummy_verification') && $details['eligible'] && ! $details['acknowledged']) {
            $this->play->acknowledge($token);
            $details['acknowledged'] = true;
        }

        return $this->response($request, $details);
    }

    private function accountId(Request $request): string
    {
        return hash_hmac('sha256', 'subscription-demo:'.$request->user()->getAuthIdentifier(), config('app.key'));
    }

    private function details(Request $request, array $purchase): array
    {
        $owner = data_get($purchase, 'externalAccountIdentifiers.obfuscatedExternalAccountId');
        abort_unless(is_string($owner) && hash_equals($this->accountId($request), $owner), 403, 'Esta compra corresponde a otra cuenta de Domus.');
        $item = collect($purchase['lineItems'] ?? [])
            ->first(fn ($line) => ($line['productId'] ?? null) === config('billing_demo.product_id'));
        abort_if($item === null, 422, 'La compra no corresponde a la suscripción de prueba.');

        try {
            $expiresAt = isset($item['expiryTime']) ? CarbonImmutable::parse($item['expiryTime'])->utc() : null;
        } catch (\Throwable) {
            abort(503, 'Google Play no devolvió una fecha válida.');
        }

        $state = $purchase['subscriptionState'] ?? 'SUBSCRIPTION_STATE_UNSPECIFIED';
        $eligible = in_array($state, [
            'SUBSCRIPTION_STATE_ACTIVE',
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
            // Cancelled subscriptions remain valid until the paid period ends.
            'SUBSCRIPTION_STATE_CANCELED',
        ], true) && $expiresAt !== null && $expiresAt->isFuture();

        return [
            'eligible' => $eligible,
            'acknowledged' => ($purchase['acknowledgementState'] ?? null) === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
            'state' => $state,
            'expires_at' => $expiresAt,
        ];
    }

    private function response(Request $request, ?array $details = null): JsonResponse
    {
        return response()->json(['data' => [
            'account_id' => $this->accountId($request),
            'subscribed' => $details !== null && $details['eligible'] && $details['acknowledged'],
            'product_id' => config('billing_demo.product_id'),
            'expires_at' => ($details['expires_at'] ?? null)?->toIso8601String(),
        ]]);
    }
}
