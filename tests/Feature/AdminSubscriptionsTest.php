<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Services\GooglePlayDemoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
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
            'lineItems' => [['productId' => 'domus_dummy', 'expiryTime' => '2026-11-18T09:00:00Z']],
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_PENDING',
        ]);
        $play->shouldReceive('acknowledge')->once()->with('purchase-token-real');
        $this->app->instance(GooglePlayDemoClient::class, $play);

        Sanctum::actingAs($user);
        $this->postJson('/api/billing/demo/verify', [
            'product_id' => 'domus_dummy',
            'purchase_token' => 'purchase-token-real',
        ])->assertOk()->assertJsonPath('data.subscribed', true);

        $this->assertDatabaseHas('billing_demo_subscriptions', [
            'user_id' => $user->id,
            'product_id' => 'domus_dummy',
            'google_state' => 'SUBSCRIPTION_STATE_ACTIVE',
            'acknowledged' => true,
            'expires_at' => '2026-11-18 09:00:00',
        ]);
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
