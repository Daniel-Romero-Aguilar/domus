<?php

namespace Tests\Feature;

use App\Models\Allowance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllowanceAmountUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_update_the_amount_of_an_owned_allowance(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $child = User::factory()->create(['role' => 'child']);
        $allowance = $this->allowance($parent, $child, 1000);
        $allowance->update(['next_payment_amount_cents' => 1500]);

        $response = $this->actingAs($parent, 'sanctum')
            ->patchJson("/api/allowances/{$allowance->id}", [
                'amount' => '25.50',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Cantidad de la mesada actualizada correctamente.')
            ->assertJsonPath('one_time', false)
            ->assertJsonPath('next_payment_date', $allowance->next_run_at->toISOString())
            ->assertJsonPath('next_payment_amount_cents', 2550)
            ->assertJsonPath('next_payment_amount_display', '$25.50')
            ->assertJsonPath('allowance.id', $allowance->id)
            ->assertJsonPath('allowance.amount_cents', 2550)
            ->assertJsonPath('allowance.next_payment_amount_cents', null);

        $this->assertDatabaseHas('allowances', [
            'id' => $allowance->id,
            'amount_cents' => 2550,
            'next_payment_amount_cents' => null,
        ]);
    }

    public function test_one_time_amount_is_used_once_and_then_cleared(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $child = User::factory()->create(['role' => 'child']);
        $allowance = $this->allowance($parent, $child, 1000);

        $this->actingAs($parent, 'sanctum')
            ->patchJson("/api/allowances/{$allowance->id}", [
                'amount' => '25.50',
                'one_time' => true,
            ])
            ->assertOk()
            ->assertJsonPath('one_time', true)
            ->assertJsonPath('next_payment_date', $allowance->next_run_at->toISOString())
            ->assertJsonPath('next_payment_amount_cents', 2550)
            ->assertJsonPath('next_payment_amount_display', '$25.50')
            ->assertJsonPath('allowance.amount_cents', 1000)
            ->assertJsonPath('allowance.next_payment_amount_cents', 2550);

        $this->postJson("/api/allowances/{$allowance->id}/execute")
            ->assertOk()
            ->assertJsonPath('executed', true)
            ->assertJsonPath('payment.amount_cents', 2550)
            ->assertJsonPath('allowance.amount_cents', 1000)
            ->assertJsonPath('allowance.next_payment_amount_cents', null);

        $this->assertDatabaseHas('allowances', [
            'id' => $allowance->id,
            'amount_cents' => 1000,
            'next_payment_amount_cents' => null,
        ]);
    }

    public function test_parent_cannot_update_another_parents_allowance(): void
    {
        $owner = User::factory()->create(['role' => 'parent']);
        $otherParent = User::factory()->create(['role' => 'parent']);
        $child = User::factory()->create(['role' => 'child']);
        $allowance = $this->allowance($owner, $child, 1000);

        $this->actingAs($otherParent, 'sanctum')
            ->patchJson("/api/allowances/{$allowance->id}", [
                'amount' => '25.50',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta mesada no pertenece a tu cuenta.');

        $this->assertDatabaseHas('allowances', [
            'id' => $allowance->id,
            'amount_cents' => 1000,
        ]);
    }

    public function test_parent_can_pause_and_resume_an_allowance_without_changing_its_schedule(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $child = User::factory()->create(['role' => 'child']);
        $allowance = $this->allowance($parent, $child, 1000);
        $nextPaymentDate = $allowance->next_run_at->toISOString();

        $this->actingAs($parent, 'sanctum')
            ->patchJson("/api/allowances/{$allowance->id}", [
                'paused' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Mesada pausada correctamente.')
            ->assertJsonPath('paused', true)
            ->assertJsonPath('allowance.status', 'paused')
            ->assertJsonPath('next_payment_date', $nextPaymentDate)
            ->assertJsonPath('next_payment_amount_cents', 1000);

        $this->postJson("/api/allowances/{$allowance->id}/execute")
            ->assertOk()
            ->assertJsonPath('executed', false)
            ->assertJsonPath('message', 'Esta mesada esta pausada.');

        $this->patchJson("/api/allowances/{$allowance->id}", [
            'paused' => false,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Mesada reanudada correctamente.')
            ->assertJsonPath('paused', false)
            ->assertJsonPath('allowance.status', 'active')
            ->assertJsonPath('next_payment_date', $nextPaymentDate)
            ->assertJsonPath('next_payment_amount_cents', 1000);
    }

    public function test_allowance_amount_must_use_valid_money_format(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $child = User::factory()->create(['role' => 'child']);
        $allowance = $this->allowance($parent, $child, 1000);

        $this->actingAs($parent, 'sanctum')
            ->patchJson("/api/allowances/{$allowance->id}", [
                'amount' => '25.999',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseHas('allowances', [
            'id' => $allowance->id,
            'amount_cents' => 1000,
        ]);
    }

    private function allowance(User $parent, User $child, int $amountCents): Allowance
    {
        return Allowance::query()->create([
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'amount_cents' => $amountCents,
            'frequency' => 'weekly',
            'start_at' => now()->toDateString(),
            'next_run_at' => now()->addWeek(),
            'first_payment_immediate' => false,
            'status' => 'active',
        ]);
    }
}
