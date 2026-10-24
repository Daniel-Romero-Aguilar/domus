<?php

namespace Tests\Feature;

use App\Livewire\AdminSubscriptionList;
use App\Models\Admin;
use App\Models\User;
use App\Services\GooglePlayDemoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AdminSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_see_real_subscription_snapshots(): void
    {
        Carbon::setTestNow('2026-10-18 12:00:00');

        $juan = User::factory()->create(['name' => 'Juan Suscrito']);
        $maria = User::factory()->create(['name' => 'Maria Pendiente']);
        $ana = User::factory()->create(['name' => 'Ana Simulada']);
        $luis = User::factory()->create(['name' => 'Luis Sin Compra']);

        $this->subscription($juan, 'SUBSCRIPTION_STATE_ACTIVE', true, '2026-11-18 09:00:00');
        $this->subscription($maria, 'SUBSCRIPTION_STATE_ACTIVE', null, '2026-11-18 09:00:00');
        $this->subscription($ana, 'DEMO_CONFIRMED', true, '2026-11-18 09:00:00');

        $this->get(route('admin.subscriptions.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($juan, 'web')
            ->get(route('admin.subscriptions.index'))
            ->assertRedirect(route('admin.login'));

        $admin = Admin::query()->create([
            'name' => 'Administradora DOMUS',
            'email' => 'admin-subscriptions@domus.test',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'admin_web')
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin, 'admin_web')
            ->get(route('admin.subscriptions.index'))
            ->assertOk()
            ->assertSee('Juan Suscrito')
            ->assertSee('ID '.$juan->id)
            ->assertSee('Producto: domus_dummy')
            ->assertSee('Vence 18/11/2026 09:00 UTC')
            ->assertSee('Verificado 18/10/2026 12:00 UTC')
            ->assertSee('Maria Pendiente')
            ->assertSee('Pendiente de comprobar')
            ->assertSee('Ana Simulada')
            ->assertSee('Simulada')
            ->assertSee('Luis Sin Compra')
            ->assertSee('No suscrito')
            ->assertDontSee('purchase-token-');
    }

    public function test_successful_google_acknowledgement_is_saved_for_the_admin_page(): void
    {
        Carbon::setTestNow('2026-10-18 12:00:00');
        config()->set('billing_demo.enabled', true);
        config()->set('billing_demo.dummy_verification', false);
        $user = User::factory()->create(['name' => 'Juan Compra']);
        $owner = hash_hmac('sha256', 'subscription-demo:'.$user->id, config('app.key'));

        $play = Mockery::mock(GooglePlayDemoClient::class);
        $play->shouldReceive('lookup')->once()->with('purchase-token-real')->andReturn([
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => $owner],
            'lineItems' => [[
                'productId' => 'domus_dummy',
                'expiryTime' => '2026-11-18T09:00:00Z',
                'autoRenewingPlan' => ['autoRenewEnabled' => true],
            ]],
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_PENDING',
        ]);
        $play->shouldReceive('acknowledge')->once()->with('purchase-token-real');
        $this->app->instance(GooglePlayDemoClient::class, $play);

        Sanctum::actingAs($user);
        $this->postJson('/api/billing/demo/verify', [
            'product_id' => 'domus_dummy',
            'purchase_token' => 'purchase-token-real',
        ])->assertOk()->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.auto_renew_enabled', true);

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.has_active_subscription', true)
            ->assertJsonPath('user.auto_renew_enabled', true);

        $this->assertDatabaseHas('billing_demo_subscriptions', [
            'user_id' => $user->id,
            'product_id' => 'domus_dummy',
            'google_state' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledged' => true,
            'auto_renew_enabled' => true,
            'expires_at' => '2026-11-18 09:00:00',
        ]);
    }

    public function test_refreshing_a_renewed_purchase_updates_the_admin_snapshot(): void
    {
        Carbon::setTestNow('2026-10-18 06:03:00');
        config()->set('billing_demo.enabled', true);
        config()->set('billing_demo.dummy_verification', false);
        $user = User::factory()->create(['name' => 'Daniel Renovado']);
        $this->subscription($user, 'SUBSCRIPTION_STATE_ACTIVE', true, '2026-10-18 06:06:00');
        DB::table('billing_demo_subscriptions')->where('user_id', $user->id)->update([
            'purchase_token' => Crypt::encryptString('purchase-token-'.$user->id),
            'auto_renew_enabled' => false,
        ]);

        Carbon::setTestNow('2026-10-18 06:24:00');
        $owner = hash_hmac('sha256', 'subscription-demo:'.$user->id, config('app.key'));
        $play = Mockery::mock(GooglePlayDemoClient::class);
        $play->shouldReceive('lookup')->once()->with('purchase-token-'.$user->id)->andReturn([
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => $owner],
            'lineItems' => [[
                'productId' => 'domus_dummy',
                'expiryTime' => '2026-10-18T06:31:00Z',
                'autoRenewingPlan' => ['autoRenewEnabled' => true],
            ]],
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
        ]);
        $this->app->instance(GooglePlayDemoClient::class, $play);

        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')
            ->assertOk()
            ->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.auto_renew_enabled', true)
            ->assertJsonPath('data.expires_at', '2026-10-18T06:31:00+00:00');

        $this->assertDatabaseHas('billing_demo_subscriptions', [
            'user_id' => $user->id,
            'expires_at' => '2026-10-18 06:31:00',
            'verified_at' => '2026-10-18 06:24:00',
            'auto_renew_enabled' => true,
        ]);

        $admin = Admin::query()->create([
            'name' => 'Administradora DOMUS',
            'email' => 'admin-renewal@domus.test',
            'password' => Hash::make('password'),
        ]);
        Livewire::actingAs($admin, 'admin_web')
            ->test(AdminSubscriptionList::class)
            ->assertSee('Daniel Renovado')
            ->assertSee('Vence 18/10/2026 06:31 UTC')
            ->assertSee('Verificado 18/10/2026 06:24 UTC')
            ->assertSee('Suscrito');
    }

    public function test_refreshing_a_canceled_purchase_disables_renewal_and_keeps_access_until_expiry(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 23)->setTime(12, 0));
        config()->set('billing_demo.enabled', true);
        config()->set('billing_demo.dummy_verification', false);
        $user = User::factory()->create(['created_at' => now()->subDays(30)]);
        $this->subscription($user, 'SUBSCRIPTION_STATE_ACTIVE', true, '2026-11-18 09:00:00');
        DB::table('billing_demo_subscriptions')->where('user_id', $user->id)->update([
            'purchase_token' => Crypt::encryptString('renewal-token'),
            'auto_renew_enabled' => true,
        ]);

        $owner = hash_hmac('sha256', 'subscription-demo:'.$user->id, config('app.key'));
        $play = Mockery::mock(GooglePlayDemoClient::class);
        $play->shouldReceive('lookup')->once()->with('renewal-token')->andReturn([
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => $owner],
            'lineItems' => [[
                'productId' => 'domus_dummy',
                'expiryTime' => '2026-11-18T09:00:00Z',
                'autoRenewingPlan' => ['autoRenewEnabled' => false],
            ]],
            'subscriptionState' => 'SUBSCRIPTION_STATE_CANCELED',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
        ]);
        $this->app->instance(GooglePlayDemoClient::class, $play);

        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')->assertOk()
            ->assertJsonPath('data.subscribed', true)
            ->assertJsonPath('data.auto_renew_enabled', false);
        $this->assertDatabaseHas('billing_demo_subscriptions', [
            'user_id' => $user->id,
            'google_state' => 'SUBSCRIPTION_STATE_CANCELED',
            'auto_renew_enabled' => false,
        ]);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.has_active_subscription', true)
            ->assertJsonPath('user.auto_renew_enabled', false)
            ->assertJsonPath('user.is_enabled_functions', true);

        $this->travelTo(now('UTC')->setDate(2026, 11, 18)->setTime(9, 0));
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.has_active_subscription', false)
            ->assertJsonPath('user.auto_renew_enabled', null)
            ->assertJsonPath('user.is_enabled_functions', false)
            ->assertJsonPath('user.premium_expires_at', '2026-11-18T09:00:00+00:00');
    }

    public function test_a_purchase_no_longer_found_in_google_clears_renewal(): void
    {
        config()->set('billing_demo.enabled', true);
        config()->set('billing_demo.dummy_verification', false);
        $user = User::factory()->create();
        $this->subscription($user, 'SUBSCRIPTION_STATE_ACTIVE', true, now()->addMonth()->toDateTimeString());
        DB::table('billing_demo_subscriptions')->where('user_id', $user->id)->update([
            'purchase_token' => Crypt::encryptString('missing-token'),
            'auto_renew_enabled' => true,
        ]);
        $play = Mockery::mock(GooglePlayDemoClient::class);
        $play->shouldReceive('lookup')->once()->with('missing-token')->andReturnNull();
        $this->app->instance(GooglePlayDemoClient::class, $play);

        Sanctum::actingAs($user);
        $this->getJson('/api/billing/demo')->assertOk()
            ->assertJsonPath('data.subscribed', false)
            ->assertJsonPath('data.auto_renew_enabled', null);
        $this->assertDatabaseHas('billing_demo_subscriptions', [
            'user_id' => $user->id,
            'auto_renew_enabled' => null,
        ]);
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.has_active_subscription', false)
            ->assertJsonPath('user.auto_renew_enabled', null);
    }

    public function test_livewire_filters_all_users_before_paginating_and_resets_the_page(): void
    {
        Carbon::setTestNow('2026-10-18 12:00:00');
        $admin = Admin::query()->create([
            'name' => 'Administradora DOMUS',
            'email' => 'admin-filters@domus.test',
            'password' => Hash::make('password'),
        ]);
        $juan = User::factory()->create(['name' => 'Juan Suscrito', 'email' => 'juan@example.test']);
        $maria = User::factory()->create(['name' => 'Maria Pendiente', 'email' => 'maria@example.test']);
        $ana = User::factory()->create(['name' => 'Ana Simulada']);
        $luis = User::factory()->create(['name' => 'Luis Sin Compra']);
        $eva = User::factory()->create(['name' => 'Eva Vencida']);
        $paco = User::factory()->create(['name' => 'Paco Cancelada Vigente']);
        $this->subscription($juan, 'SUBSCRIPTION_STATE_ACTIVE', true, '2026-11-18 09:00:00');
        $this->subscription($maria, 'SUBSCRIPTION_STATE_ACTIVE', null, '2026-11-18 09:00:00');
        $this->subscription($ana, 'DEMO_CONFIRMED', true, '2026-11-18 09:00:00');
        $this->subscription($eva, 'SUBSCRIPTION_STATE_ACTIVE', true, '2026-10-17 09:00:00');
        $this->subscription($paco, 'SUBSCRIPTION_STATE_CANCELED', true, '2026-11-18 09:00:00');

        for ($index = 1; $index <= 11; $index++) {
            User::factory()->create(['name' => 'Extra '.$index]);
        }

        Livewire::actingAs($admin, 'admin_web')
            ->test(AdminSubscriptionList::class)
            ->assertSee('Extra 11')
            ->assertDontSee('Juan Suscrito')
            ->call('nextPage')
            ->assertSee('Juan Suscrito')
            ->set('search', 'juan@example.test')
            ->assertSee('Juan Suscrito')
            ->assertDontSee('Maria Pendiente')
            ->set('search', '')
            ->set('status', 'subscribed')
            ->assertSee('Juan Suscrito')
            ->assertSee('Paco Cancelada Vigente')
            ->assertDontSee('Maria Pendiente')
            ->assertDontSee('Ana Simulada')
            ->assertDontSee('Eva Vencida')
            ->assertDontSee('Luis Sin Compra')
            ->set('status', 'not_subscribed')
            ->set('search', 'maria@example.test')
            ->assertSee('Maria Pendiente')
            ->assertDontSee('Juan Suscrito')
            ->set('search', 'Ana Simulada')
            ->assertSee('Ana Simulada')
            ->set('search', 'Eva Vencida')
            ->assertSee('Eva Vencida')
            ->set('search', 'Luis Sin Compra')
            ->assertSee('Luis Sin Compra')
            ->assertDontSee('Juan Suscrito');
    }

    public function test_livewire_subscription_list_requires_the_admin_guard(): void
    {
        Livewire::test(AdminSubscriptionList::class)->assertForbidden();
    }

    private function subscription(User $user, string $state, ?bool $acknowledged, string $expiresAt): void
    {
        DB::table('billing_demo_subscriptions')->insert([
            'user_id' => $user->id,
            'purchase_token_hash' => hash('sha256', 'purchase-token-'.$user->id),
            'purchase_token' => 'purchase-token-'.$user->id,
            'product_id' => 'domus_dummy',
            'google_state' => $state,
            'acknowledged' => $acknowledged,
            'expires_at' => $expiresAt,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
