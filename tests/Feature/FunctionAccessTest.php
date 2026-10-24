<?php

namespace Tests\Feature;

use App\Livewire\AdminSubscriptionList;
use App\Models\Admin;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\GooglePlayDemoClient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FunctionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('UTC')->setDate(2026, 10, 20)->setTime(12, 0));
        config()->set('access.trial_days', 15);
        config()->set('access.subscription_grace_days', 0);
    }

    public function test_trial_uses_registration_date_and_expires_at_the_exact_boundary(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(15)->addSecond()]);

        $this->assertTrue($user->isEnabledFunctions());
        $this->assertSame('2026-10-20 12:00:01', $user->trialExpiresAt()->toDateTimeString());
        $this->travel(1)->seconds();
        $this->assertFalse($user->isEnabledFunctions());
    }

    public function test_configurable_trial_days_and_admin_override_share_the_same_rule(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(8)]);
        config()->set('access.trial_days', 7);
        $this->assertFalse($user->isEnabledFunctions());

        $user->trial_ends_at = now()->addHour();
        $user->save();
        $this->assertTrue($user->fresh()->isEnabledFunctions());

        $user->trial_ends_at = now()->subSecond();
        $user->save();
        config()->set('access.trial_days', 30);
        $this->assertFalse($user->fresh()->isEnabledFunctions());

        config()->set('access.trial_days', 0);
        $this->assertFalse(User::factory()->create()->isEnabledFunctions());
    }

    #[DataProvider('subscriptionStates')]
    public function test_only_valid_verified_premium_grants_access_after_trial(
        string $state, ?bool $acknowledged, string $expiry, bool $enabled,
    ): void {
        $user = User::factory()->create(['created_at' => now()->subDays(30)]);
        $this->subscription($user, $state, $acknowledged, $expiry);

        $this->assertSame($enabled, $user->isEnabledFunctions());
    }

    public static function subscriptionStates(): array
    {
        return [
            'active' => ['SUBSCRIPTION_STATE_ACTIVE', true, '2026-10-21 12:00:00', true],
            'grace' => ['SUBSCRIPTION_STATE_IN_GRACE_PERIOD', true, '2026-10-21 12:00:00', true],
            'canceled but paid' => ['SUBSCRIPTION_STATE_CANCELED', true, '2026-10-21 12:00:00', true],
            'expired by date' => ['SUBSCRIPTION_STATE_ACTIVE', true, '2026-10-20 11:59:59', false],
            'exact expiry' => ['SUBSCRIPTION_STATE_ACTIVE', true, '2026-10-20 12:00:00', false],
            'unacknowledged' => ['SUBSCRIPTION_STATE_ACTIVE', false, '2026-10-21 12:00:00', false],
            'unknown acknowledgement' => ['SUBSCRIPTION_STATE_ACTIVE', null, '2026-10-21 12:00:00', false],
            'pending' => ['SUBSCRIPTION_STATE_PENDING', true, '2026-10-21 12:00:00', false],
            'on hold' => ['SUBSCRIPTION_STATE_ON_HOLD', true, '2026-10-21 12:00:00', false],
            'paused' => ['SUBSCRIPTION_STATE_PAUSED', true, '2026-10-21 12:00:00', false],
            'expired state' => ['SUBSCRIPTION_STATE_EXPIRED', true, '2026-10-21 12:00:00', false],
            'simulated' => ['DEMO_CONFIRMED', true, '2026-10-21 12:00:00', false],
        ];
    }

    public function test_latest_verification_and_product_determine_access_and_admin_filter(): void
    {
        $user = User::factory()->create(['name' => 'Latest Snapshot', 'created_at' => now()->subDays(30)]);
        $first = $this->subscription($user);
        $this->subscription($user, 'SUBSCRIPTION_STATE_EXPIRED');
        $this->assertFalse($user->fresh()->isEnabledFunctions());

        $admin = $this->admin();
        Livewire::actingAs($admin, 'admin_web')->test(AdminSubscriptionList::class)
            ->set('status', 'subscribed')->assertDontSee('Latest Snapshot')
            ->set('status', 'free')->assertSee('Latest Snapshot');

        // A lower ID with a later verification must win over insertion order.
        DB::table('billing_demo_subscriptions')->where('id', $first)->update(['verified_at' => now()->addSecond()]);
        $this->assertTrue($user->fresh()->isEnabledFunctions());
        DB::table('billing_demo_subscriptions')->where('id', $first)->update(['product_id' => 'other_product']);
        $this->assertFalse($user->fresh()->isEnabledFunctions());
    }

    public function test_family_members_inherit_the_owners_trial_and_premium(): void
    {
        $parent = User::factory()->create(['created_at' => now()->subDays(30)]);
        $children = collect(['child', 'member'])->map(function (string $role) use ($parent): User {
            $user = User::factory()->create(['role' => $role]);
            FamilyMember::create(['parent_user_id' => $parent->id, 'user_id' => $user->id]);
            $this->assertFalse($user->isEnabledFunctions());
            $this->actingAs($user, 'sanctum')->getJson('/api/me')
                ->assertOk()->assertJsonPath('user.is_enabled_functions', false);

            return $user;
        });

        $parent->trial_ends_at = now()->addDay();
        $parent->save();
        foreach ($children as $child) {
            $this->assertTrue($child->fresh()->isEnabledFunctions());
        }
        $parent->trial_ends_at = now()->subDay();
        $parent->save();
        $this->subscription($parent);
        foreach ($children as $child) {
            $this->assertTrue($child->fresh()->isEnabledFunctions());
        }
        $this->assertFalse(User::factory()->create(['role' => 'child'])->isEnabledFunctions());
    }

    public function test_auth_returns_a_boolean_and_registration_cannot_override_trial(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Account',
            'email' => 'trial@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms_accepted' => true,
            'trial_ends_at' => '2099-01-01 00:00:00',
            'trial_expires_at' => '2099-01-01T00:00:00+00:00',
            'is_enabled_functions' => false,
        ])->assertCreated()->assertJsonPath('user.is_enabled_functions', true);

        $user = User::findOrFail($response->json('user.id'));
        $this->assertNull($user->trial_ends_at);
        $this->assertSame('2026-11-04 12:00:00', $user->trialExpiresAt()->toDateTimeString());
        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('user.is_enabled_functions', true);
        $this->actingAs($user, 'sanctum')->getJson('/api/user')
            ->assertOk()->assertJsonPath('is_enabled_functions', true)
            ->assertJsonMissingPath('trial_expires_at');
        $this->getJson('/api/me')
            ->assertOk()->assertJsonPath('user.is_enabled_functions', true)
            ->assertJsonPath('user.trial_expires_at', '2026-11-04T12:00:00+00:00');

        $user->created_at = now()->subDays(30);
        $user->save();
        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('user.is_enabled_functions', false);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.is_enabled_functions', false)
            ->assertJsonMissingPath('user.current_subscription')->assertJsonMissingPath('user.trial_ends_at');
    }

    public function test_me_returns_the_effective_trial_expiration_with_the_configured_duration(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(8)]);

        $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.trial_expires_at', '2026-10-27T12:00:00+00:00')
            ->assertJsonPath('user.premium_expires_at', null)
            ->assertJsonPath('user.is_enabled_functions', true)
            ->assertJsonMissingPath('user.trial_ends_at');

        config()->set('access.trial_days', 8);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.trial_expires_at', '2026-10-20T12:00:00+00:00')
            ->assertJsonPath('user.is_enabled_functions', false);

        $this->travel(1)->days();
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.trial_expires_at', '2026-10-20T12:00:00+00:00')
            ->assertJsonPath('user.is_enabled_functions', false);
    }

    public function test_me_uses_the_family_owners_effective_trial_expiration(): void
    {
        $parent = User::factory()->create(['created_at' => now()->subDays(5)]);
        foreach (['child', 'member'] as $role) {
            $user = User::factory()->create(['role' => $role, 'trial_ends_at' => now()->addYear()]);
            FamilyMember::create(['parent_user_id' => $parent->id, 'user_id' => $user->id]);

            $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk()
                ->assertJsonPath('user.trial_expires_at', '2026-10-30T12:00:00+00:00')
                ->assertJsonPath('user.is_enabled_functions', true);

            $parent->forceFill(['trial_ends_at' => '2026-10-19 12:00:00'])->save();
            $this->getJson('/api/me')->assertOk()
                ->assertJsonPath('user.trial_expires_at', '2026-10-19T12:00:00+00:00')
                ->assertJsonPath('user.is_enabled_functions', false);

            $parent->forceFill(['trial_ends_at' => null])->save();
        }
    }

    public function test_me_returns_null_trial_expiration_for_family_members_without_an_owner(): void
    {
        foreach (['child', 'member'] as $role) {
            $user = User::factory()->create(['role' => $role, 'trial_ends_at' => now()->addYear()]);

            $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk()
                ->assertJsonStructure(['user' => ['trial_expires_at']])
                ->assertJsonPath('user.trial_expires_at', null)
                ->assertJsonPath('user.is_enabled_functions', false);
        }
    }

    public function test_me_returns_null_premium_expiration_without_a_real_purchase(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonStructure(['user' => ['premium_expires_at']])
            ->assertJsonPath('user.premium_expires_at', null)
            ->assertJsonPath('user.has_active_subscription', false)
            ->assertJsonPath('user.auto_renew_enabled', null)
            ->assertJsonPath('user.is_enabled_functions', true);

        $user->created_at = now()->subDays(30);
        $user->save();
        $this->subscription($user, 'DEMO_CONFIRMED');
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.premium_expires_at', null)
            ->assertJsonPath('user.has_active_subscription', false)
            ->assertJsonPath('user.auto_renew_enabled', null)
            ->assertJsonPath('user.is_enabled_functions', false);
    }

    public function test_me_does_not_assume_renewal_for_a_purchase_without_a_saved_flag(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(30)]);
        $this->subscription($user);

        $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.has_active_subscription', true)
            ->assertJsonPath('user.auto_renew_enabled', null)
            ->assertJsonPath('user.is_enabled_functions', true);
    }

    public function test_me_keeps_premium_expiration_after_cancellation_and_expiry(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(30)]);
        $id = $this->subscription($user, expiresAt: '2026-10-21 12:00:00');
        $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.premium_expires_at', '2026-10-21T12:00:00+00:00')
            ->assertJsonPath('user.is_enabled_functions', true);

        DB::table('billing_demo_subscriptions')->where('id', $id)
            ->update(['google_state' => 'SUBSCRIPTION_STATE_CANCELED']);
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.premium_expires_at', '2026-10-21T12:00:00+00:00')
            ->assertJsonPath('user.is_enabled_functions', true);

        $this->travel(2)->days();
        DB::table('billing_demo_subscriptions')->where('id', $id)->update([
            'google_state' => 'SUBSCRIPTION_STATE_EXPIRED',
            'acknowledged' => false,
        ]);
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.premium_expires_at', '2026-10-21T12:00:00+00:00')
            ->assertJsonPath('user.is_enabled_functions', false)
            ->assertJsonMissingPath('user.current_subscription')
            ->assertJsonMissingPath('user.purchase_token');
    }

    public function test_me_returns_the_latest_known_real_purchase_expiration(): void
    {
        $user = User::factory()->create();
        $first = $this->subscription($user);
        $this->subscription($user, expiresAt: '2026-12-20 12:00:00');
        $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.premium_expires_at', '2026-12-20T12:00:00+00:00');

        $this->subscription($user, 'DEMO_CONFIRMED', expiresAt: '2027-01-20 12:00:00');
        $otherProduct = $this->subscription($user, expiresAt: '2027-02-20 12:00:00');
        DB::table('billing_demo_subscriptions')->where('id', $otherProduct)
            ->update(['product_id' => 'other_product']);
        $unknownExpiration = $this->subscription($user);
        DB::table('billing_demo_subscriptions')->where('id', $unknownExpiration)
            ->update(['expires_at' => null]);
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.premium_expires_at', '2026-12-20T12:00:00+00:00');

        DB::table('billing_demo_subscriptions')->where('id', $first)
            ->update(['verified_at' => now()->addSecond()]);
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.premium_expires_at', '2026-12-20T12:00:00+00:00');
    }

    public function test_me_uses_the_family_owners_premium_expiration(): void
    {
        $parent = User::factory()->create();
        $this->subscription($parent, autoRenewEnabled: false);
        foreach (['child', 'member'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            FamilyMember::create(['parent_user_id' => $parent->id, 'user_id' => $user->id]);
            $this->subscription($user, expiresAt: '2026-12-20 12:00:00', autoRenewEnabled: true);
            $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk()
                ->assertJsonPath('user.premium_expires_at', '2026-11-20T12:00:00+00:00')
                ->assertJsonPath('user.has_active_subscription', true)
                ->assertJsonPath('user.auto_renew_enabled', false);
        }

        $orphan = User::factory()->create(['role' => 'child']);
        $this->subscription($orphan);
        $this->actingAs($orphan, 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonStructure(['user' => ['premium_expires_at']])
            ->assertJsonPath('user.premium_expires_at', null)
            ->assertJsonPath('user.has_active_subscription', false)
            ->assertJsonPath('user.auto_renew_enabled', null);
    }

    public function test_expired_access_blocks_reads_and_writes_but_keeps_auth_and_billing_available(): void
    {
        config()->set('billing_demo.enabled', true);
        $user = User::factory()->create(['created_at' => now()->subDays(30)]);
        $token = $user->createToken('test')->plainTextToken;
        $this->getJson('/api/notifications')->assertUnauthorized()->assertJsonMissingPath('code');
        $this->withToken($token);

        foreach (['/api/notifications', '/api/loans', '/api/family-members', '/api/education/categories'] as $uri) {
            $this->getJson($uri)->assertForbidden()->assertExactJson([
                'message' => 'Tu periodo gratuito ha terminado. Necesitas Premium para usar las funciones de Domus.',
                'code' => 'FUNCTIONS_DISABLED',
                'is_enabled_functions' => false,
            ]);
        }
        $this->postJson('/api/balance/add', ['amount' => '100.00', 'is_enabled_functions' => true])
            ->assertForbidden()->assertJsonPath('is_enabled_functions', false)
            ->assertJsonPath('code', 'FUNCTIONS_DISABLED');
        $this->assertDatabaseCount('balance_movements', 0);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.is_enabled_functions', false);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('is_enabled_functions', false);
        $this->getJson('/api/billing/demo')->assertOk()->assertJsonPath('data.is_enabled_functions', false);
        $this->postJson('/api/billing/demo/verify', [])->assertUnprocessable();
        $this->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_trial_and_premium_users_can_use_protected_endpoints(): void
    {
        $trial = User::factory()->create();
        $this->actingAs($trial, 'sanctum')->getJson('/api/notifications')->assertOk();

        $premium = User::factory()->create(['created_at' => now()->subDays(30)]);
        $this->subscription($premium);
        $this->actingAs($premium, 'sanctum')->getJson('/api/notifications')->assertOk();
    }

    public function test_role_errors_are_distinct_from_disabled_access(): void
    {
        $parent = User::factory()->create();
        $this->actingAs($parent, 'sanctum')->getJson('/api/child/tasks')
            ->assertForbidden()->assertJsonMissingPath('code')->assertJsonMissingPath('is_enabled_functions');
    }

    public function test_verified_purchase_immediately_restores_access_after_expiration(): void
    {
        config()->set('billing_demo.enabled', true);
        config()->set('billing_demo.dummy_verification', false);
        $user = User::factory()->create(['created_at' => now()->subDays(30)]);
        $owner = hash_hmac('sha256', 'subscription-demo:'.$user->id, config('app.key'));
        $play = Mockery::mock(GooglePlayDemoClient::class);
        $play->shouldReceive('lookup')->once()->with('premium-token')->andReturn([
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => $owner],
            'lineItems' => [['productId' => config('billing_demo.product_id'), 'expiryTime' => '2026-11-20T12:00:00Z']],
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
        ]);
        $this->app->instance(GooglePlayDemoClient::class, $play);

        $this->withToken($user->createToken('purchase-test')->plainTextToken);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.is_enabled_functions', false);
        $this->postJson('/api/billing/demo/verify', [
            'product_id' => config('billing_demo.product_id'),
            'purchase_token' => 'premium-token',
        ])->assertOk()->assertJsonPath('data.subscribed', true)->assertJsonPath('data.is_enabled_functions', true);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.premium_expires_at', '2026-11-20T12:00:00+00:00');
        $this->getJson('/api/notifications')->assertOk();
    }

    public function test_admin_can_filter_free_accounts_and_edit_their_expiration(): void
    {
        $free = User::factory()->create(['name' => 'Free Account', 'created_at' => now()->subDays(30)]);
        $premium = User::factory()->create(['name' => 'Premium Account']);
        $this->subscription($premium);
        $createdAt = $free->created_at->toDateTimeString();

        $component = Livewire::actingAs($this->admin(), 'admin_web')->test(AdminSubscriptionList::class)
            ->set('status', 'free')->assertSee('Free Account')->assertDontSee('Premium Account')
            ->assertSee('Prueba vencida')->assertSee('Funciones bloqueadas')
            ->call('editTrial', $free->id)
            ->set('trialEndsAt', '2026-11-01T12:34')->call('saveTrial')->assertHasNoErrors()
            ->assertSee('Gratis hasta 01/11/2026 12:34:00 UTC')
            ->assertSee('Funciones habilitadas');

        $this->assertTrue($free->fresh()->isEnabledFunctions());
        $this->assertSame($createdAt, $free->fresh()->created_at->toDateTimeString());
        $this->actingAs($free->fresh(), 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.trial_expires_at', '2026-11-01T12:34:00+00:00')
            ->assertJsonPath('user.is_enabled_functions', true);
        $component->call('editTrial', $free->id)
            ->set('trialEndsAt', '2026-10-20T11:59:59')->call('saveTrial')->assertHasNoErrors()
            ->assertSee('Funciones bloqueadas');
        $this->assertFalse($free->fresh()->isEnabledFunctions());
        $this->actingAs($free->fresh(), 'sanctum')->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.trial_expires_at', '2026-10-20T11:59:59+00:00')
            ->assertJsonPath('user.is_enabled_functions', false);
    }

    public function test_admin_date_validation_and_cancel_do_not_change_access(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($this->admin(), 'admin_web')->test(AdminSubscriptionList::class)
            ->call('editTrial', $user->id)->set('trialEndsAt', 'not-a-date')
            ->call('saveTrial')->assertHasErrors(['trialEndsAt'])
            ->call('cancelEdit')->assertSet('editingUserId', null)->assertHasNoErrors();
        $this->assertNull($user->fresh()->trial_ends_at);
    }

    public function test_normal_users_cannot_edit_trials_and_family_members_have_no_separate_trial(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user, 'web')->test(AdminSubscriptionList::class)->assertForbidden();
        $child = User::factory()->create(['role' => 'child']);
        $this->assertNull($child->trial_ends_at);
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($this->admin(), 'admin_web')->test(AdminSubscriptionList::class)
            ->call('editTrial', $child->id);
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Superadmin', 'email' => 'trial-admin@example.test', 'password' => 'password']);
    }

    private function subscription(
        User $user, string $state = 'SUBSCRIPTION_STATE_ACTIVE', ?bool $acknowledged = true,
        string $expiresAt = '2026-11-20 12:00:00',
        ?bool $autoRenewEnabled = null,
    ): int {
        return DB::table('billing_demo_subscriptions')->insertGetId([
            'user_id' => $user->id,
            'purchase_token_hash' => hash('sha256', $user->id.'-'.DB::table('billing_demo_subscriptions')->count()),
            'purchase_token' => 'test-token',
            'product_id' => config('billing_demo.product_id'),
            'google_state' => $state,
            'acknowledged' => $acknowledged,
            'auto_renew_enabled' => $autoRenewEnabled,
            'expires_at' => $expiresAt,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
