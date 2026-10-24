<?php

namespace App\Services;

use App\Models\BillingSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BillingSubscriptionService
{
    public function __construct(private GooglePlayDemoClient $play) {}

    public function accountId(User $account): string
    {
        return hash_hmac('sha256', 'subscription-demo:'.$account->getKey(), config('app.key'));
    }

    public function verify(User $account, string $token): BillingSubscription
    {
        $hash = hash('sha256', $token);
        try {
            return Cache::lock('billing-purchase.'.$hash, 90)->block(5, function () use ($account, $token, $hash) {
                $record = BillingSubscription::query()->where('purchase_token_hash', $hash)->first();
                abort_if($record && (int) $record->user_id !== (int) $account->getKey(), 403, 'Esta compra corresponde a otra cuenta de Domus.');
                if ($record && ! $this->mayAttempt($record)) {
                    return $record;
                }
                if (config('billing_demo.dummy_verification')) {
                    return $this->persist($account, $token, [
                        'google_state' => 'DEMO_CONFIRMED', 'acknowledged' => true,
                        'auto_renew_enabled' => null,
                        'expires_at' => CarbonImmutable::now('UTC')->addMonthNoOverflow(),
                    ]);
                }
                if ($record) {
                    $this->beginAttempt($record);
                }
                try {
                    $purchase = $this->play->lookup($token);
                    if ($purchase === null && $record) {
                        $record->forceFill([
                            'google_state' => 'SUBSCRIPTION_STATE_EXPIRED',
                            'acknowledged' => false, 'auto_renew_enabled' => null, 'verified_at' => now('UTC'),
                        ])->save();
                    }
                    abort_if($purchase === null, 422, 'El comprobante de Google Play no es válido o ya venció.');
                    $details = $this->details($account, $purchase);
                    abort_if(in_array($details['google_state'], ['SUBSCRIPTION_STATE_PENDING', 'SUBSCRIPTION_STATE_PENDING_PURCHASE_CANCELED'], true), 422, 'La compra aún no está confirmada en Google Play.');
                } catch (\Throwable $error) {
                    if (! $record) {
                        throw $error;
                    }
                    $this->failed($record, $error);
                    if ($error instanceof HttpExceptionInterface && $error->getStatusCode() < 500) {
                        throw $error;
                    }

                    return $record;
                }
                $record = $this->persist($account, $token, $details);
                $this->supersede($account, $record, $purchase);
                if ($record->sync_started_at === null) {
                    $this->beginAttempt($record);
                }
                $this->acknowledgeAndSchedule($record, $token);

                return $record;
            });
        } catch (LockTimeoutException) {
            abort(503, 'La compra se está actualizando. Reintenta en unos momentos.');
        }
    }

    public function refresh(BillingSubscription $record, bool $scheduled = false): BillingSubscription
    {
        if (config('billing_demo.dummy_verification') || $record->google_state === 'DEMO_CONFIRMED') {
            return $record;
        }
        Cache::lock('billing-purchase.'.$record->purchase_token_hash, 90)->get(function () use ($record, $scheduled): void {
            $record->refresh();
            if (! $this->mayAttempt($record, $scheduled)) {
                return;
            }
            $this->beginAttempt($record);
            try {
                $account = User::findOrFail($record->user_id);
                $token = Crypt::decryptString($record->purchase_token);
                $purchase = $this->play->lookup($token);
                if ($purchase === null) {
                    $record->forceFill([
                        'google_state' => 'SUBSCRIPTION_STATE_EXPIRED', 'acknowledged' => false,
                        'auto_renew_enabled' => null, 'verified_at' => now('UTC'),
                    ])->save();
                    $this->resolved($record);

                    return;
                }
                $record->forceFill($this->details($account, $purchase));
                $this->markVerified($record);
                $record->save();
                $this->supersede($account, $record, $purchase);
                $this->acknowledgeAndSchedule($record, $token);
            } catch (\Throwable $error) {
                $this->failed($record, $error);
            }
        });

        return $record->refresh();
    }

    public function runDue(int $limit = 25): int
    {
        if (! config('billing_demo.enabled') || config('billing_demo.dummy_verification')) {
            return 0;
        }
        $records = BillingSubscription::query()->where('product_id', config('billing_demo.product_id'))
            ->whereNull('superseded_at')->whereNull('sync_stopped_at')
            ->where('google_state', '!=', 'DEMO_CONFIRMED')
            ->where('next_sync_at', '<=', now('UTC'))->orderBy('next_sync_at')->limit($limit)->get();
        $started = microtime(true);
        $processed = 0;
        foreach ($records as $record) {
            if (microtime(true) - $started >= 45) {
                break;
            }
            $this->refresh($record, true);
            $processed++;
        }

        return $processed;
    }

    private function persist(User $account, string $token, array $details): BillingSubscription
    {
        return DB::transaction(function () use ($account, $token, $details) {
            $hash = hash('sha256', $token);
            DB::table('billing_demo_subscriptions')->insertOrIgnore([
                'user_id' => $account->getKey(), 'purchase_token_hash' => $hash,
                'purchase_token' => Crypt::encryptString($token), 'product_id' => config('billing_demo.product_id'),
                'google_state' => $details['google_state'],
                'verified_at' => now('UTC'), 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $record = BillingSubscription::query()->where('purchase_token_hash', $hash)->lockForUpdate()->firstOrFail();
            abort_unless((int) $record->user_id === (int) $account->getKey(), 403, 'Esta compra corresponde a otra cuenta de Domus.');
            $record->forceFill($details);
            $this->markVerified($record);
            $record->save();

            return $record;
        });
    }

    private function markVerified(BillingSubscription $record): void
    {
        $record->verified_at = now('UTC');
        if ($record->isEligible() && $record->first_verified_at === null) {
            $record->first_verified_at = now('UTC');
        }
    }

    private function supersede(User $account, BillingSubscription $record, array $purchase): void
    {
        if (BillingSubscription::query()->where('user_id', $account->getKey())
            ->where('product_id', $record->product_id)->where('id', '!=', $record->id)
            ->where('linked_purchase_token_hash', $record->purchase_token_hash)->exists()) {
            $record->forceFill(['superseded_at' => $record->superseded_at ?? now('UTC'), 'next_sync_at' => null])->save();

            return;
        }
        $linked = $purchase['linkedPurchaseToken'] ?? null;
        BillingSubscription::query()->where('user_id', $account->getKey())
            ->where('product_id', $record->product_id)->where('id', '!=', $record->id)->whereNull('superseded_at')
            ->where(function ($query) use ($linked): void {
                $query->where('google_state', 'DEMO_CONFIRMED');
                if (is_string($linked) && $linked !== '') {
                    $query->orWhere('purchase_token_hash', hash('sha256', $linked));
                }
            })->update(['superseded_at' => now('UTC'), 'next_sync_at' => null]);
    }

    private function details(User $account, array $purchase): array
    {
        $owner = data_get($purchase, 'externalAccountIdentifiers.obfuscatedExternalAccountId')
            ?? data_get($purchase, 'outOfAppPurchaseContext.expiredExternalAccountIdentifiers.obfuscatedExternalAccountId');
        abort_unless(is_string($owner) && hash_equals($this->accountId($account), $owner), 403, 'Esta compra corresponde a otra cuenta de Domus.');
        $item = collect($purchase['lineItems'] ?? [])->first(fn ($line) => ($line['productId'] ?? null) === config('billing_demo.product_id'));
        abort_if($item === null, 422, 'La compra no corresponde a la suscripción de prueba.');
        try {
            $expiresAt = isset($item['expiryTime']) ? CarbonImmutable::parse($item['expiryTime'])->utc() : null;
        } catch (\Throwable) {
            abort(503, 'Google Play no devolvió una fecha válida.');
        }
        $autoRenew = data_get($item, 'autoRenewingPlan.autoRenewEnabled');
        $state = $purchase['subscriptionState'] ?? null;
        $acknowledgement = $purchase['acknowledgementState'] ?? null;
        abort_unless(in_array($state, [
            'SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD', 'SUBSCRIPTION_STATE_CANCELED',
            'SUBSCRIPTION_STATE_EXPIRED', 'SUBSCRIPTION_STATE_ON_HOLD', 'SUBSCRIPTION_STATE_PAUSED',
            'SUBSCRIPTION_STATE_PENDING', 'SUBSCRIPTION_STATE_PENDING_PURCHASE_CANCELED',
        ], true) && in_array($acknowledgement, ['ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED', 'ACKNOWLEDGEMENT_STATE_PENDING'], true), 503, 'Google Play no devolvió un estado válido.');
        abort_if(in_array($state, BillingSubscription::ELIGIBLE_STATES, true) && $expiresAt === null, 503, 'Google Play no devolvió una fecha válida.');

        $details = [
            'google_state' => $state,
            'acknowledged' => $acknowledgement === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
            'auto_renew_enabled' => is_bool($autoRenew) ? $autoRenew : null, 'expires_at' => $expiresAt,
        ];
        if (is_string($purchase['linkedPurchaseToken'] ?? null) && $purchase['linkedPurchaseToken'] !== '') {
            $details['linked_purchase_token_hash'] = hash('sha256', $purchase['linkedPurchaseToken']);
        }

        return $details;
    }

    private function mayAttempt(BillingSubscription $record, bool $scheduled = false): bool
    {
        if ($record->superseded_at !== null) {
            return false;
        }
        $cutoff = now('UTC')->subMinutes(config('billing_demo.refresh_interval_minutes'));
        if ($record->sync_stopped_at !== null) {
            // A user returning to the app may recover a temporary failure; background work stays stopped.
            return ! $scheduled
                && in_array($record->last_sync_error, ['google_unavailable', 'renewal_pending', 'retry_limit'], true)
                && ! $record->sync_stopped_at->greaterThan($cutoff);
        }
        if ($record->sync_started_at !== null && ($record->sync_attempts >= config('billing_demo.retry_max_attempts')
            || ! $this->deadline($record)->isFuture())) {
            $this->stop($record, $record->last_sync_error ?? 'retry_limit');

            return false;
        }

        if ($scheduled) {
            return $record->sync_started_at === null || ! ($record->next_sync_at?->isFuture() ?? false);
        }

        // App refreshes use a short cache, independently of the longer background retry delays.
        $lastAttempt = $record->sync_started_at !== null ? $record->updated_at : $record->verified_at;

        return $record->first_verified_at === null || ! $lastAttempt->greaterThan($cutoff);
    }

    private function beginAttempt(BillingSubscription $record): void
    {
        $anchor = $record->expires_at !== null && ! $record->expires_at->isFuture() ? $record->expires_at : now('UTC');
        if ($record->acknowledged === false && $record->first_verified_at !== null) {
            $anchor = $record->first_verified_at;
        }
        $record->forceFill([
            'sync_started_at' => $record->sync_started_at ?? $anchor,
            'sync_attempts' => min($record->sync_attempts + 1, (int) config('billing_demo.retry_max_attempts')),
            // Reserve before the network call, so interruptions also consume an attempt.
            'next_sync_at' => now('UTC')->addMinutes(config('billing_demo.retry_delays_minutes')[0]),
        ])->save();
    }

    private function deadline(BillingSubscription $record): CarbonImmutable
    {
        $deadline = $record->sync_started_at->addDays(config('billing_demo.retry_window_days'));
        if ($record->acknowledged === false && $record->first_verified_at !== null) {
            $deadline = $deadline->min($record->first_verified_at->addDays(3));
        }

        return $deadline;
    }

    private function acknowledgeAndSchedule(BillingSubscription $record, string $token): void
    {
        if ($record->superseded_at !== null) {
            $this->resolved($record);

            return;
        }
        try {
            if ($record->isEligible() && ! $record->acknowledged) {
                $this->play->acknowledge($token);
                $record->acknowledged = true;
                $record->save();
            }
        } catch (\Throwable $error) {
            $this->failed($record, $error);

            return;
        }
        if (! $record->isPremium() && $record->auto_renew_enabled !== false
            && in_array($record->google_state, ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD'], true)) {
            $this->retry($record, 'renewal_pending');
        } else {
            $this->resolved($record);
        }
    }

    private function resolved(BillingSubscription $record): void
    {
        $next = $record->isPremium() && $record->auto_renew_enabled !== false
            && $record->google_state !== 'SUBSCRIPTION_STATE_CANCELED' ? $record->expires_at : null;
        $record->forceFill([
            'sync_attempts' => 0, 'sync_started_at' => null, 'sync_stopped_at' => null,
            'last_sync_error' => null, 'next_sync_at' => $next,
        ])->save();
    }

    private function failed(BillingSubscription $record, \Throwable $error): void
    {
        if ($error instanceof HttpExceptionInterface && $error->getStatusCode() < 500) {
            $this->stop($record, 'purchase_rejected');
        } elseif ($error instanceof DecryptException) {
            $this->stop($record, 'token_unreadable');
        } else {
            $this->retry($record, 'google_unavailable');
        }
    }

    private function retry(BillingSubscription $record, string $reason): void
    {
        if ($record->sync_attempts >= config('billing_demo.retry_max_attempts') || ! $this->deadline($record)->isFuture()) {
            $this->stop($record, $reason);

            return;
        }
        $delays = config('billing_demo.retry_delays_minutes');
        $delay = $delays[min(max(0, $record->sync_attempts - 1), count($delays) - 1)];
        $record->forceFill([
            'last_sync_error' => $reason,
            'next_sync_at' => CarbonImmutable::now('UTC')->addMinutes($delay)->min($this->deadline($record)),
        ])->save();
    }

    private function stop(BillingSubscription $record, string $reason): void
    {
        $record->forceFill(['sync_stopped_at' => now('UTC'), 'next_sync_at' => null, 'last_sync_error' => $reason])->save();
        Log::warning('Billing subscription requires review', ['subscription_id' => $record->id, 'reason' => $reason, 'attempts' => $record->sync_attempts]);
    }
}
