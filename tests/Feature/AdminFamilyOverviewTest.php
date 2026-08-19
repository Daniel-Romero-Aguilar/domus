<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FamilyMember;
use App\Models\Loan;
use App\Models\SavingsBox;
use App\Models\SavingsBoxAccount;
use App\Models\User;
use App\Services\Admin\AdminFamilyOverviewService;
use App\Services\Admin\AdminFamilyReadService;
use App\Services\Admin\AdminLoanReadService;
use App\Services\Admin\AdminSavingsBoxReadService;
use App\Support\AdminAbilities;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminFamilyOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_admin_guard_can_open_the_family_overview(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);

        $this->get(route('admin.families.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($parent, 'web')
            ->get(route('admin.families.index'))
            ->assertRedirect(route('admin.login'));

        $this->assertFalse(
            Gate::forUser($parent)->allows(AdminAbilities::VIEW_FAMILY_OVERVIEW)
        );
    }

    public function test_admin_sees_paginated_families_with_read_only_financial_summaries_without_n_plus_one(): void
    {
        $admin = $this->admin();
        $parents = collect();

        for ($index = 1; $index <= 7; $index++) {
            $parent = User::factory()->create([
                'name' => 'Familia '.$index,
                'role' => 'parent',
                'created_at' => Carbon::parse('2026-01-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)),
            ]);
            $child = User::factory()->create([
                'name' => 'Integrante '.$index,
                'username' => 'integrante'.$index,
                'role' => 'child',
            ]);

            FamilyMember::query()->create([
                'parent_user_id' => $parent->id,
                'user_id' => $child->id,
                'is_minor' => true,
                'guardian_declaration_accepted' => true,
            ]);

            Loan::query()->create([
                'parent_user_id' => $parent->id,
                'child_user_id' => $child->id,
                'amount' => 100 + $index,
                'reason' => 'Prueba administrativa',
                'due_date' => '2026-12-31',
                'installments_count' => 2,
                'installment_frequency' => 'monthly',
                'has_interest' => false,
                'interest_mode' => 'percent',
                'annual_interest_rate' => 0,
                'fixed_interest_amount' => 0,
                'status' => 'approved',
                'requested_by_user_id' => $parent->id,
                'total_amount' => 100 + $index,
                'installment_amount' => 51 + intdiv($index, 2),
            ]);

            $box = SavingsBox::query()->create([
                'parent_user_id' => $parent->id,
                'name' => 'Caja familiar '.$index,
                'duration_months' => 1,
                'duration_days' => 31,
                'cycle_days' => 14,
                'cycle_count' => 3,
                'plan_version' => 'fixed_days_ceil_v1',
                'max_per_cycle_cents' => 1000,
                'max_amount_cents' => 3000,
                'starts_at' => '2026-01-01 10:00:00',
                'cutoff_at' => '2026-02-01 10:00:00',
                'delivery_date' => '2026-02-01',
                'annual_gain_percent' => 5,
                'benefit_percent' => 5,
                'benefit_mode' => 'final_balance_simple',
                'allow_early_withdrawal' => false,
                'audience' => 'all',
                'status' => 'active',
            ]);

            SavingsBoxAccount::query()->create([
                'savings_box_id' => $box->id,
                'user_id' => $child->id,
                'principal_cents' => 500 + $index,
            ]);

            $parents->push($parent);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        Model::preventLazyLoading();

        try {
            $response = $this->actingAs($admin, 'admin_web')
                ->get(route('admin.families.index'));
            $queryCount = count(DB::getQueryLog());
        } finally {
            Model::preventLazyLoading(false);
            DB::disableQueryLog();
        }

        $response
            ->assertOk()
            ->assertSee('Familias en DOMUS')
            ->assertSee('Familia 7')
            ->assertSee('Integrante 7')
            ->assertSee('Caja familiar 7')
            ->assertDontSee('Familia 1')
            ->assertSee('Página 1 de 2');

        $this->assertLessThanOrEqual(14, $queryCount, 'The admin overview query count must not grow per parent.');

        $this->actingAs($admin, 'admin_web')
            ->get(route('admin.families.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Familia 1');
    }

    public function test_each_admin_read_service_enforces_the_gate_when_called_directly(): void
    {
        $unpersistedAdmin = new Admin([
            'name' => 'Sin acceso',
            'email' => 'sin-acceso@domus.test',
        ]);

        $calls = [
            fn () => app(AdminFamilyOverviewService::class)->overview($unpersistedAdmin),
            fn () => app(AdminFamilyReadService::class)->paginate($unpersistedAdmin),
            fn () => app(AdminLoanReadService::class)->forParents($unpersistedAdmin, []),
            fn () => app(AdminSavingsBoxReadService::class)->forParents($unpersistedAdmin, []),
        ];

        foreach ($calls as $call) {
            $denied = false;

            try {
                $call();
            } catch (AuthorizationException) {
                $denied = true;
            }

            $this->assertTrue($denied, 'Every admin read service must enforce the admin Gate.');
        }
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Administradora DOMUS',
            'email' => 'admin-overview@domus.test',
            'password' => Hash::make('password'),
        ]);
    }
}
