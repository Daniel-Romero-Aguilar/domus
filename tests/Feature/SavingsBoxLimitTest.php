<?php

namespace Tests\Feature;

use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\SavingsBox;
use App\Models\User;
use App\Services\SavingsBoxSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SavingsBoxLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_parent_can_load_creation_options_without_creating_a_box(): void
    {
        Carbon::setTestNow('2026-01-31 10:00:00');
        config()->set('savings_box.months_available', [1, 3]);
        config()->set('savings_box.days_per_cycle', 14);
        [$parent, $child] = $this->familyWithChildren(1);

        $this->actingAs($parent, 'sanctum')
            ->getJson('/api/savings-boxes/creation-options')
            ->assertOk()
            ->assertJsonPath('creation_options.plan_version', 'fixed_days_ceil_v1')
            ->assertJsonPath('creation_options.is_preview', true)
            ->assertJsonPath('creation_options.cycle_days', 14)
            ->assertJsonPath('creation_options.cycle_label', 'Cada 14 días')
            ->assertJsonPath('creation_options.months_available', [1, 3])
            ->assertJsonPath('creation_options.duration_options.0.duration_months', 1)
            ->assertJsonPath('creation_options.duration_options.0.duration_days_preview', 28)
            ->assertJsonPath('creation_options.duration_options.0.cycle_count_preview', 2)
            ->assertJsonPath('creation_options.duration_options.0.max_total_multiplier_preview', 2);

        $this->assertDatabaseCount('savings_boxes', 0);

        $this->actingAs($child, 'sanctum')
            ->getJson('/api/savings-boxes/creation-options')
            ->assertForbidden();
    }

    public function test_creation_freezes_the_calculated_plan_in_the_database(): void
    {
        Carbon::setTestNow('2026-01-31 10:00:00');
        config()->set('savings_box.months_available', [1, 2, 3]);
        config()->set('savings_box.days_per_cycle', 14);
        [$parent] = $this->familyWithChildren(1);

        $response = $this->actingAs($parent, 'sanctum')->postJson('/api/savings-boxes', [
            'name' => 'Meta familiar',
            'duration_months' => 1,
            'max_per_cycle' => '10.00',
            'benefit_percent' => 5,
            'allow_early_withdrawal' => false,
            'audience' => 'all',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('savings_box.duration_months', 1)
            ->assertJsonPath('savings_box.duration_days', 28)
            ->assertJsonPath('savings_box.cycle_days', 14)
            ->assertJsonPath('savings_box.cycle_count', 2)
            ->assertJsonPath('savings_box.plan_version', 'fixed_days_ceil_v1')
            ->assertJsonPath('savings_box.max_per_cycle_cents', 1000)
            ->assertJsonPath('savings_box.max_amount_cents', 2000)
            ->assertJsonPath('savings_box.benefit_mode', 'final_balance_simple');

        $this->assertDatabaseHas('savings_boxes', [
            'id' => $response->json('savings_box.id'),
            'duration_months' => 1,
            'duration_days' => 28,
            'cycle_days' => 14,
            'cycle_count' => 2,
            'plan_version' => 'fixed_days_ceil_v1',
            'max_per_cycle_cents' => 1000,
            'max_amount_cents' => 2000,
            'benefit_percent' => 5,
            'benefit_mode' => 'final_balance_simple',
        ]);
    }

    public function test_config_changes_only_affect_boxes_created_after_the_change(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        config()->set('savings_box.months_available', [1, 2, 3]);
        config()->set('savings_box.days_per_cycle', 14);
        [$parent] = $this->familyWithChildren(1);

        $oldBoxId = $this->createBox($parent, 1, '10.00')->json('savings_box.id');
        $oldBox = SavingsBox::query()->findOrFail($oldBoxId);

        config()->set('savings_box.months_available', [1, 3, 6, 12]);
        config()->set('savings_box.days_per_cycle', 7);

        $newBoxId = $this->createBox($parent, 3, '10.00')->json('savings_box.id');
        $newBox = SavingsBox::query()->findOrFail($newBoxId);

        $this->assertSame(14, $oldBox->fresh()->cycle_days);
        $this->assertSame(3, $oldBox->fresh()->cycle_count);
        $this->assertSame(3000, $oldBox->fresh()->max_amount_cents);

        $this->assertSame(7, $newBox->cycle_days);
        $this->assertSame(13, $newBox->cycle_count);
        $this->assertSame(13000, $newBox->max_amount_cents);
    }

    public function test_deposits_are_limited_independently_in_each_cycle(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        config()->set('savings_box.months_available', [1]);
        config()->set('savings_box.days_per_cycle', 14);
        [$parent, $child] = $this->familyWithChildren(1);
        $boxId = $this->createBox($parent, 1, '10.00', true)->json('savings_box.id');
        Balance::query()->create(['user_id' => $child->id, 'amount' => 5000]);

        $this->actingAs($child, 'sanctum')
            ->postJson("/api/savings-boxes/{$boxId}/deposit", ['amount' => '7.00'])
            ->assertOk()
            ->assertJsonPath('cycle_number', 1)
            ->assertJsonPath('remaining_cycle_cents', 300);

        $this->postJson("/api/savings-boxes/{$boxId}/deposit", ['amount' => '4.00'])
            ->assertUnprocessable()
            ->assertJsonPath('cycle_number', 1)
            ->assertJsonPath('remaining_cycle_cents', 300);

        Carbon::setTestNow('2026-01-15 10:00:00');

        $this->postJson("/api/savings-boxes/{$boxId}/deposit", ['amount' => '10.00'])
            ->assertOk()
            ->assertJsonPath('cycle_number', 2)
            ->assertJsonPath('remaining_cycle_cents', 0);

        // The third cycle is only the final three days of this calendar month,
        // but rounding up gives it the complete per-cycle allowance.
        Carbon::setTestNow('2026-01-31 10:00:00');

        $this->postJson("/api/savings-boxes/{$boxId}/deposit", ['amount' => '10.00'])
            ->assertOk()
            ->assertJsonPath('cycle_number', 3)
            ->assertJsonPath('remaining_cycle_cents', 0);

        $this->assertDatabaseHas('savings_box_accounts', [
            'savings_box_id' => $boxId,
            'user_id' => $child->id,
            'principal_cents' => 2700,
        ]);
    }

    public function test_settlement_service_pays_the_final_balance_once(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        config()->set('savings_box.months_available', [1]);
        config()->set('savings_box.days_per_cycle', 14);
        [$parent, $child] = $this->familyWithChildren(1);
        $boxId = $this->createBox($parent, 1, '100.00', false, 10)->json('savings_box.id');
        Balance::query()->create(['user_id' => $child->id, 'amount' => 10000]);

        $this->actingAs($child, 'sanctum')
            ->postJson("/api/savings-boxes/{$boxId}/deposit", ['amount' => '80.00'])
            ->assertOk();

        Carbon::setTestNow('2026-02-01 23:59:00');
        $result = app(SavingsBoxSettlementService::class)->runDue(Carbon::now());

        $this->assertSame(1, $result['settled']);
        $this->assertSame(8800, $result['paid_cents']);
        $this->assertDatabaseHas('savings_boxes', [
            'id' => $boxId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('savings_box_accounts', [
            'savings_box_id' => $boxId,
            'user_id' => $child->id,
            'principal_cents' => 0,
            'settled_principal_cents' => 8000,
            'settled_benefit_cents' => 800,
            'settled_total_cents' => 8800,
        ]);
        $this->assertDatabaseHas('balances', [
            'user_id' => $child->id,
            'amount' => 10800,
        ]);

        $secondResult = app(SavingsBoxSettlementService::class)->runDue(Carbon::now());

        $this->assertSame(0, $secondResult['settled']);
        $this->assertDatabaseHas('balances', [
            'user_id' => $child->id,
            'amount' => 10800,
        ]);
    }

    private function createBox(
        User $parent,
        int $durationMonths,
        string $maxPerCycle,
        bool $allowEarlyWithdrawal = false,
        float $benefitPercent = 5
    ) {
        return $this->actingAs($parent, 'sanctum')->postJson('/api/savings-boxes', [
            'name' => 'Caja de prueba '.uniqid(),
            'duration_months' => $durationMonths,
            'max_per_cycle' => $maxPerCycle,
            'benefit_percent' => $benefitPercent,
            'allow_early_withdrawal' => $allowEarlyWithdrawal,
            'audience' => 'all',
        ])->assertCreated();
    }

    /** @return array<int, User> */
    private function familyWithChildren(int $count): array
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $children = [];

        for ($index = 0; $index < $count; $index++) {
            $child = User::factory()->create(['role' => 'child']);
            FamilyMember::query()->create([
                'parent_user_id' => $parent->id,
                'user_id' => $child->id,
            ]);
            $children[] = $child;
        }

        return [$parent, ...$children];
    }
}
