<?php

namespace Tests\Feature;

use App\Models\BillingSubscription;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\BillingSubscriptionService;
use App\Services\GooglePlayDemoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BillingRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('UTC')->setDate(2026, 10, 24)->setTime(12, 0));
        config()->set('billing_demo.enabled', true);
        config()->set('billing_demo.dummy_verification', false);
        config()->set('billing_demo.retry_max_attempts', 50);
        config()->set('billing_demo.retry_window_days', 3);
        config()->set('access.subscription_grace_days', 3);
    }

    private function account(): User
    {
        return User::factory()->create(['created_at' => now()->subDays(30)]);
    }

    private function subscription(User $user, string $expiry, array $overrides = []): BillingSubscription
    {
        $token = 'token-'.$user->id;

        return tap(new BillingSubscription, function (BillingSubscription $record) use ($user, $token, $expiry, $overrides): void {
            $record->forceFill(array_merge([
                'user_id' => $user->id,
                'purchase_token_hash' => hash('sha256', $token),
                'purchase_token' => Crypt::encryptString($token),
                'product_id' => config('billing_demo.product_id'),
                'google_state' => 'SUBSCRIPTION_STATE_ACTIVE',
                'acknowledged' => true,
                'auto_renew_enabled' => true,
                'expires_at' => $expiry,
                'verified_at' => now()->subMinutes(10),
                'first_verified_at' => now()->subDays(30),
            ], $overrides))->save();
        });
    }

    private function purchase(User $user, string $expiry, bool $acknowledged = true): array
    {
        return [
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => app(BillingSubscriptionService::class)->accountId($user)],
            'lineItems' => [[
                'productId' => config('billing_demo.product_id'),
                'expiryTime' => $expiry,
                'autoRenewingPlan' => ['autoRenewEnabled' => true],
            ]],
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledgementState' => $acknowledged ? 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED' : 'ACKNOWLEDGEMENT_STATE_PENDING',
        ];
    }

    private function play(): MockInterface
    {
        $play = Mockery::mock(GooglePlayDemoClient::class);
        $this->app->instance(GooglePlayDemoClient::class, $play);

        return $play;
    }

    #[DataProvider('graceDays')]
    public function test_grace_is_configurable_and_expires_at_the_exact_boundary(int $days): void
    {
        config()->set('access.subscription_grace_days', $days);
        $user = $this->account();
        $record = $this->subscription($user, now()->toDateTimeString());
        Sanctum::actingAs($user);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.has_active_subscription', false)
            ->assertJsonPath('user.is_enabled_functions', $days > 0)
            ->assertJsonPath('user.is_premium_grace_period', $days > 0)
            ->assertJsonPath('user.premium_grace_ends_at', $days > 0 ? $record->expires_at->addDays($days)->toIso8601String() : null)
            ->assertJsonPath('user.premium_expires_at', $record->expires_at->toIso8601String());
        if ($days > 0) {
            $this->getJson('/api/notifications')->assertOk();
            $this->travelTo($record->expires_at->addDays($days));
            $this->getJson('/api/me')->assertOk()
                ->assertJsonPath('user.is_enabled_functions', false)
                ->assertJsonPath('user.is_premium_grace_period', false);
            $this->getJson('/api/notifications')->assertForbidden();
        }
    }

    public static function graceDays(): array
    {
        return [[0], [1], [3]];
    }

    public function test_acknowledgement_failure_grants_fixed_grace_and_recovers_without_the_app(): void
    {
        $user = $this->account();
        $purchase = $this->purchase($user, '2026-11-24T12:00:00Z', false);
        $play = $this->play();
        $play->shouldReceive('lookup')->twice()->with('new-token')->andReturn($purchase);
        $attempt = 0;
        $play->shouldReceive('acknowledge')->twice()->with('new-token')->andReturnUsing(function () use (&$attempt): void {
            if (++$attempt === 1) {
                throw new HttpException(503, 'Temporary outage');
            }
        });
        Sanctum::actingAs($user);
        $this->postJson('/api/billing/demo/verify', ['product_id' => config('billing_demo.product_id'), 'purchase_token' => 'new-token'])
            ->assertOk()->assertJsonPath('data.subscribed', false)
            ->assertJsonPath('data.sync_pending', true)
            ->assertJsonPath('data.is_enabled_functions', true)
            ->assertJsonPath('data.premium_grace_ends_at', '2026-10-27T12:00:00+00:00');
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.is_premium_grace_period', true);
        $this->travel(5)->minutes();
        $this->artisan('billing:sync')->assertExitCode(0);
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.has_active_subscription', true)
            ->assertJsonPath('user.is_premium_grace_period', false)
            ->assertJsonPath('user.premium_grace_ends_at', null);
        $this->assertDatabaseHas('billing_demo_subscriptions', ['user_id' => $user->id, 'acknowledged' => true, 'sync_attempts' => 0]);
    }

    public function test_google_outage_preserves_paid_dates_and_cannot_extend_grace(): void
    {
        $user = $this->account();
        $record = $this->subscription($user, now()->subHour()->toDateTimeString());
        $end = $record->expires_at->addDays(3);
        $play = $this->play();
        $play->shouldReceive('lookup')->twice()->with('token-'.$user->id)->andThrow(new HttpException(503));
        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.premium_grace_ends_at', $end->toIso8601String());
        $this->travel(5)->minutes();
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.premium_grace_ends_at', $end->toIso8601String());
        $this->assertSame($record->expires_at->toDateTimeString(), $record->fresh()->expires_at->toDateTimeString());
        $this->travelTo($end);
        $this->artisan('billing:sync')->assertExitCode(0);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.is_enabled_functions', false);
        $this->assertNotNull($record->fresh()->sync_stopped_at);
    }

    public function test_a_client_token_without_google_validation_never_grants_grace(): void
    {
        $user = $this->account();
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->with('unverified-token')->andThrow(new HttpException(503));
        Sanctum::actingAs($user);
        $this->postJson('/api/billing/demo/verify', ['product_id' => config('billing_demo.product_id'), 'purchase_token' => 'unverified-token'])
            ->assertStatus(503);
        $this->assertDatabaseCount('billing_demo_subscriptions', 0);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.is_enabled_functions', false)
            ->assertJsonPath('user.is_premium_grace_period', false);
    }

    public function test_repeated_acknowledgement_failures_do_not_restart_the_initial_grace(): void
    {
        $user = $this->account();
        $play = $this->play();
        $play->shouldReceive('lookup')->twice()->with('pending-token')->andReturn($this->purchase($user, '2026-11-24T12:00:00Z', false));
        $play->shouldReceive('acknowledge')->twice()->andThrow(new HttpException(503));
        Sanctum::actingAs($user);
        $payload = ['product_id' => config('billing_demo.product_id'), 'purchase_token' => 'pending-token'];
        $this->postJson('/api/billing/demo/verify', $payload)->assertOk()
            ->assertJsonPath('data.premium_grace_ends_at', '2026-10-27T12:00:00+00:00');
        $this->travel(2)->days();
        $this->postJson('/api/billing/demo/verify', $payload)->assertOk()
            ->assertJsonPath('data.premium_grace_ends_at', '2026-10-27T12:00:00+00:00');
        $this->assertDatabaseHas('billing_demo_subscriptions', [
            'user_id' => $user->id, 'first_verified_at' => '2026-10-24 12:00:00', 'sync_attempts' => 2,
        ]);
    }

    public function test_retry_intervals_increase_instead_of_polling_every_five_minutes(): void
    {
        $user = $this->account();
        $record = $this->subscription($user, now()->subHour()->toDateTimeString(), ['next_sync_at' => now()]);
        $play = $this->play();
        $play->shouldReceive('lookup')->times(3)->andThrow(new HttpException(503));
        foreach ([5, 15, 30] as $minutes) {
            $expected = now('UTC')->addMinutes($minutes);
            $this->artisan('billing:sync')->assertExitCode(0);
            $this->assertSame($expected->toDateTimeString(), $record->fresh()->next_sync_at->toDateTimeString());
            $this->travelTo($expected);
        }
    }

    public function test_migration_can_resume_and_only_schedules_recent_unresolved_purchases(): void
    {
        $recent = $this->subscription($this->account(), now()->addDay()->toDateTimeString());
        $historical = $this->subscription($this->account(), now()->subYears(3)->toDateTimeString());
        $pending = $this->subscription($this->account(), now()->addMonth()->toDateTimeString(), [
            'acknowledged' => false, 'first_verified_at' => now()->subDay(),
        ]);
        $stopped = $this->subscription($this->account(), now()->addDay()->toDateTimeString(), ['sync_stopped_at' => now()]);
        $migration = require database_path('migrations/2026_10_24_000001_add_sync_tracking_to_billing_demo_subscriptions_table.php');
        $migration->up();
        $migration->up();
        $this->assertSame($recent->expires_at->toDateTimeString(), $recent->fresh()->next_sync_at->toDateTimeString());
        $this->assertNull($historical->fresh()->next_sync_at);
        $this->assertSame(now()->toDateTimeString(), $pending->fresh()->next_sync_at->toDateTimeString());
        $this->assertSame($pending->first_verified_at->toDateTimeString(), $pending->fresh()->first_verified_at->toDateTimeString());
        $this->assertNull($stopped->fresh()->next_sync_at);
        $this->assertDatabaseCount('billing_demo_subscriptions', 4);
    }

    public function test_retry_limit_survives_app_visits_and_future_scheduler_runs(): void
    {
        config()->set('billing_demo.retry_max_attempts', 2);
        $user = $this->account();
        $record = $this->subscription($user, now()->subHour()->toDateTimeString(), ['next_sync_at' => now()]);
        $play = $this->play();
        $play->shouldReceive('lookup')->times(3)->with('token-'.$user->id)->andThrow(new HttpException(503));
        $this->artisan('billing:sync')->assertExitCode(0);
        $firstDue = $record->fresh()->next_sync_at;
        $this->assertSame(now()->addMinutes(5)->toDateTimeString(), $firstDue->toDateTimeString());
        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')->assertOk();
        $this->travelTo($firstDue);
        $this->artisan('billing:sync')->assertExitCode(0);
        $this->assertSame(2, $record->fresh()->sync_attempts);
        $this->assertNotNull($record->fresh()->sync_stopped_at);
        $this->assertNull($record->fresh()->next_sync_at);
        $this->getJson('/api/billing/demo')->assertOk();
        $this->postJson('/api/billing/demo/verify', ['product_id' => config('billing_demo.product_id'), 'purchase_token' => 'token-'.$user->id])->assertOk();
        $this->travel(5)->minutes();
        $this->getJson('/api/billing/demo')->assertOk()
            ->assertJsonPath('data.sync_pending', false)
            ->assertJsonPath('data.premium_grace_ends_at', '2026-10-27T11:00:00+00:00');
        $this->assertSame(2, $record->fresh()->sync_attempts);
        $this->assertNull($record->fresh()->next_sync_at);
        $this->travel(3)->years();
        $this->artisan('billing:sync')->assertExitCode(0);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.is_enabled_functions', false);
        $this->assertDatabaseCount('billing_demo_subscriptions', 1);
    }

    public function test_app_refresh_can_recover_after_automatic_retries_stop(): void
    {
        config()->set('billing_demo.retry_max_attempts', 1);
        $user = $this->account();
        $record = $this->subscription($user, now()->subHour()->toDateTimeString(), ['next_sync_at' => now()]);
        $renewed = $this->purchase($user, '2026-11-24T12:00:00Z');
        $calls = 0;
        $play = $this->play();
        $play->shouldReceive('lookup')->twice()->andReturnUsing(function () use (&$calls, $renewed): array {
            if (++$calls === 1) {
                throw new HttpException(503);
            }

            return $renewed;
        });
        $this->artisan('billing:sync')->assertExitCode(0);
        $this->assertNotNull($record->fresh()->sync_stopped_at);
        $this->travel(5)->minutes();
        $this->artisan('billing:sync')->assertExitCode(0);
        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')->assertOk()
            ->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.is_premium_grace_period', false)
            ->assertJsonPath('data.expires_at', '2026-11-24T12:00:00+00:00');
        $this->assertNull($record->fresh()->sync_stopped_at);
        $this->assertSame(0, $record->fresh()->sync_attempts);
    }

    public function test_app_refresh_can_check_before_a_long_background_retry_delay(): void
    {
        config()->set('billing_demo.retry_delays_minutes', [180]);
        $user = $this->account();
        $record = $this->subscription($user, now()->subHour()->toDateTimeString(), ['next_sync_at' => now()]);
        $renewed = $this->purchase($user, '2026-11-24T12:00:00Z');
        $calls = 0;
        $play = $this->play();
        $play->shouldReceive('lookup')->twice()->andReturnUsing(function () use (&$calls, $renewed): array {
            if (++$calls === 1) {
                throw new HttpException(503);
            }

            return $renewed;
        });
        $this->artisan('billing:sync')->assertExitCode(0);
        $this->assertSame(now()->addHours(3)->toDateTimeString(), $record->fresh()->next_sync_at->toDateTimeString());
        $this->travel(5)->minutes();
        $this->artisan('billing:sync')->assertExitCode(0);
        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.subscribed', true);
    }

    public function test_repeated_refreshes_of_an_expired_purchase_use_the_same_short_cache(): void
    {
        $user = $this->account();
        $this->subscription($user, now()->subDay()->toDateTimeString());
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->andReturnNull();
        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.subscribed', false);
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.subscribed', false);
    }

    #[DataProvider('negativeStates')]
    public function test_confirmed_negative_states_and_disabled_renewal_do_not_get_renewal_grace(string $state, bool $renew): void
    {
        $user = $this->account();
        $this->subscription($user, now()->subMinute()->toDateTimeString(), ['google_state' => $state, 'auto_renew_enabled' => $renew]);
        Sanctum::actingAs($user);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.is_enabled_functions', false)
            ->assertJsonPath('user.is_premium_grace_period', false);
    }

    public static function negativeStates(): array
    {
        return [
            ['SUBSCRIPTION_STATE_CANCELED', false], ['SUBSCRIPTION_STATE_EXPIRED', true],
            ['SUBSCRIPTION_STATE_ON_HOLD', true], ['SUBSCRIPTION_STATE_PAUSED', true],
            ['SUBSCRIPTION_STATE_ACTIVE', false],
        ];
    }

    public function test_a_confirmed_expiration_ends_provisional_access_immediately(): void
    {
        $user = $this->account();
        $record = $this->subscription($user, now()->subHour()->toDateTimeString());
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->andReturnNull();
        Sanctum::actingAs($user);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.is_enabled_functions', true);
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.is_enabled_functions', false);
        $this->assertNull($record->fresh()->next_sync_at);
    }

    public function test_a_member_refreshes_the_owners_purchase(): void
    {
        $parent = $this->account();
        $record = $this->subscription($parent, now()->subMinute()->toDateTimeString());
        $child = User::factory()->create(['role' => 'child']);
        FamilyMember::create(['parent_user_id' => $parent->id, 'user_id' => $child->id]);
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->with('token-'.$parent->id)->andReturn($this->purchase($parent, '2026-11-24T12:00:00Z'));
        Sanctum::actingAs($child);
        $this->getJson('/api/billing/demo')->assertOk()
            ->assertJsonPath('data.account_id', app(BillingSubscriptionService::class)->accountId($parent))
            ->assertJsonPath('data.subscribed', true);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.has_active_subscription', true);
        $this->assertSame('2026-11-24 12:00:00', $record->fresh()->expires_at->toDateTimeString());
    }

    public function test_an_old_expired_receipt_cannot_hide_a_valid_purchase(): void
    {
        $user = $this->account();
        $this->subscription($user, '2026-11-24 12:00:00');
        $old = $this->purchase($user, '2026-10-20T12:00:00Z');
        $old['subscriptionState'] = 'SUBSCRIPTION_STATE_EXPIRED';
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->with('old-token')->andReturn($old);
        Sanctum::actingAs($user);
        $this->postJson('/api/billing/demo/verify', ['product_id' => config('billing_demo.product_id'), 'purchase_token' => 'old-token'])
            ->assertOk()->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.expires_at', '2026-11-24T12:00:00+00:00');
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.has_active_subscription', true);
        $this->assertDatabaseCount('billing_demo_subscriptions', 2);
    }

    public function test_a_linked_replacement_wins_even_with_a_shorter_expiry_and_old_receipts_stay_retired(): void
    {
        $user = $this->account();
        $old = $this->subscription($user, '2026-12-24 12:00:00');
        $purchase = $this->purchase($user, '2026-11-24T12:00:00Z');
        $purchase['linkedPurchaseToken'] = 'token-'.$user->id;
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->with('replacement-token')->andReturn($purchase);
        Sanctum::actingAs($user);
        $this->postJson('/api/billing/demo/verify', ['product_id' => config('billing_demo.product_id'), 'purchase_token' => 'replacement-token'])
            ->assertOk()->assertJsonPath('data.expires_at', '2026-11-24T12:00:00+00:00');
        $this->assertNotNull($old->fresh()->superseded_at);
        $this->postJson('/api/billing/demo/verify', ['product_id' => config('billing_demo.product_id'), 'purchase_token' => 'token-'.$user->id])
            ->assertOk()->assertJsonPath('data.expires_at', '2026-11-24T12:00:00+00:00');
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.premium_expires_at', '2026-11-24T12:00:00+00:00');
    }

    public function test_malformed_google_response_preserves_last_verified_entitlement(): void
    {
        $user = $this->account();
        $record = $this->subscription($user, '2026-11-24 12:00:00');
        $purchase = $this->purchase($user, '2026-11-24T12:00:00Z');
        unset($purchase['lineItems'][0]['expiryTime']);
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->andReturn($purchase);
        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.subscribed', true)->assertJsonPath('data.sync_pending', true);
        $this->assertSame('2026-11-24 12:00:00', $record->fresh()->expires_at->toDateTimeString());
    }

    public function test_an_unknown_old_token_is_retired_when_its_replacement_was_received_first(): void
    {
        $user = $this->account();
        $replacement = $this->purchase($user, '2026-11-24T12:00:00Z');
        $replacement['linkedPurchaseToken'] = 'unknown-old-token';
        $old = $this->purchase($user, '2026-12-24T12:00:00Z');
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->with('replacement-first')->andReturn($replacement);
        $play->shouldReceive('lookup')->once()->with('unknown-old-token')->andReturn($old);
        Sanctum::actingAs($user);
        foreach (['replacement-first', 'unknown-old-token'] as $token) {
            $this->postJson('/api/billing/demo/verify', ['product_id' => config('billing_demo.product_id'), 'purchase_token' => $token])
                ->assertOk()->assertJsonPath('data.expires_at', '2026-11-24T12:00:00+00:00');
        }
        $oldRecord = BillingSubscription::query()->where('purchase_token_hash', hash('sha256', 'unknown-old-token'))->firstOrFail();
        $this->assertNotNull($oldRecord->superseded_at);
        $this->assertNull($oldRecord->next_sync_at);
    }

    public function test_an_operator_can_retry_without_resetting_the_fixed_grace_period(): void
    {
        $user = $this->account();
        $record = $this->subscription($user, now()->subDays(4)->toDateTimeString(), ['sync_stopped_at' => now()->subDay()]);
        $play = $this->play();
        $play->shouldReceive('lookup')->once()->andThrow(new HttpException(503));
        $this->artisan('billing:sync', ['--subscription' => $record->id, '--retry' => true])->assertExitCode(0);
        $this->assertFalse($record->fresh()->allowsAccess());
        $this->assertSame('2026-10-20 12:00:00', $record->fresh()->expires_at->toDateTimeString());
        $this->artisan('billing:sync', ['--retry' => true])->assertExitCode(1);
    }

    public function test_a_concurrent_refresh_does_not_duplicate_attempts(): void
    {
        $user = $this->account();
        $record = $this->subscription($user, now()->subHour()->toDateTimeString());
        $play = $this->play();
        $play->shouldNotReceive('lookup');
        $lock = Cache::lock('billing-purchase.'.$record->purchase_token_hash, 90);
        $this->assertTrue($lock->get());
        try {
            app(BillingSubscriptionService::class)->refresh($record);
            $this->assertSame(0, $record->fresh()->sync_attempts);
        } finally {
            $lock->release();
        }
    }

    public function test_simulated_purchases_do_not_grant_real_premium_or_provisional_access(): void
    {
        config()->set('billing_demo.dummy_verification', true);
        $user = $this->account();
        $play = $this->play();
        $play->shouldNotReceive('lookup');
        Sanctum::actingAs($user);
        $this->postJson('/api/billing/demo/verify', ['product_id' => config('billing_demo.product_id'), 'purchase_token' => 'dummy-token'])
            ->assertOk()->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.is_enabled_functions', false)
            ->assertJsonPath('data.is_premium_grace_period', false);
        $this->artisan('billing:sync')->assertExitCode(0);
    }
}
