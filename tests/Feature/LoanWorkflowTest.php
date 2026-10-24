<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Balance;
use App\Models\BalanceMovement;
use App\Models\DomusNotification;
use App\Models\FamilyMember;
use App\Models\Loan;
use App\Models\User;
use App\Services\Admin\AdminLoanReadService;
use App\Services\LoanPaymentService;
use App\Support\BalanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private User $child;

    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 4, 1)->setTime(10, 0));
        // Loan repayment scenarios advance months beyond the default trial.
        $this->parent = User::factory()->create(['role' => 'parent', 'trial_ends_at' => now()->addYear()]);
        $this->child = User::factory()->create(['role' => 'child']);
        FamilyMember::create(['parent_user_id' => $this->parent->id, 'user_id' => $this->child->id]);
        Balance::create(['user_id' => $this->parent->id, 'amount' => 0]);
        Balance::create(['user_id' => $this->child->id, 'amount' => 1250]);
    }

    public function test_a_real_child_bearer_token_creates_only_a_normalized_pending_request(): void
    {
        $response = $this->apiAs($this->child, 'POST', '/api/loans', [
            'amount' => 1800.25,
            'reason' => '  Tenis  ',
            'months' => 3,
        ])->assertCreated()
            ->assertJsonPath('loan.child_user_id', $this->child->id)
            ->assertJsonPath('loan.parent_user_id', $this->parent->id)
            ->assertJsonPath('loan.status', 'pending')
            ->assertJsonPath('loan.amount', '1800.25')
            ->assertJsonPath('loan.reason', 'Tenis')
            ->assertJsonPath('loan.installments_count', 3)
            ->assertJsonPath('loan.installment_frequency', 'monthly')
            ->assertJsonPath('loan.has_interest', false)
            ->assertJsonPath('loan.annual_interest_rate', 0)
            ->assertJsonPath('loan.fixed_interest_amount', '0.00')
            ->assertJsonPath('loan.total_amount', '1800.25')
            ->assertJsonPath('loan.installment_plan', null)
            ->assertJsonPath('loan.payment_summary.total_installments', 0);

        $loan = Loan::findOrFail($response->json('loan.id'));
        $this->assertSame('1800.25', $loan->requested_amount);
        $this->assertSame('Tenis', $loan->requested_reason);
        $this->assertSame(3, $loan->requested_months);
        $this->assertSame('monthly', $loan->initial_installment_frequency);
        $this->assertFalse($loan->initial_has_interest);
        $this->assertSame(0.0, $loan->initial_annual_interest_rate);
        $this->assertSame('0.00', $loan->initial_fixed_interest_amount);
        $this->assertNull($loan->parent_reviewed_at);
        $this->assertNull($loan->funds_disbursed_at);

        $this->apiAs($this->parent, 'GET', '/api/loans/waiting')
            ->assertOk()->assertJsonPath('loans.0.id', $loan->id)->assertJsonPath('loans.0.payments', []);
        $this->apiAs($this->child, 'GET', '/api/loans')
            ->assertOk()->assertJsonPath('loans.0.payments', []);
        $this->assertDatabaseCount('loan_payments', 0);
        $this->assertDatabaseCount('balance_movements', 0);
        $this->assertSame(1250, $this->childBalance());
        $this->assertSame(0, BalanceHelper::cents($this->parent));
    }

    #[DataProvider('allowedMonths')]
    public function test_the_child_can_choose_each_allowed_month_count(int $months): void
    {
        $this->apiAs($this->child, 'POST', '/api/loans', ['amount' => 1800, 'reason' => 'Tenis', 'months' => $months])
            ->assertCreated()->assertJsonPath('loan.installments_count', $months);
    }

    public static function allowedMonths(): array
    {
        return [[1], [3], [6], [12]];
    }

    #[DataProvider('invalidChildInputs')]
    public function test_invalid_child_input_is_rejected_without_writing_a_loan(array $changes): void
    {
        $this->apiAs($this->child, 'POST', '/api/loans', array_replace([
            'amount' => 1800, 'reason' => 'Tenis', 'months' => 3,
        ], $changes))->assertUnprocessable();

        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_payments', 0);
        $this->assertDatabaseCount('balance_movements', 0);
    }

    public static function invalidChildInputs(): array
    {
        return [
            'missing amount' => [['amount' => null]],
            'zero amount' => [['amount' => 0]],
            'negative amount' => [['amount' => -1]],
            'three decimal places' => [['amount' => '1.234']],
            'amount is an array' => [['amount' => [1800]]],
            'amount is a boolean' => [['amount' => true]],
            'amount exceeds storage' => [['amount' => '1000000000000']],
            'amount contains sql' => [['amount' => '1; DROP TABLE loans']],
            'scientific string' => [['amount' => '1e3']],
            'missing reason' => [['reason' => null]],
            'empty reason' => [['reason' => '   ']],
            'html reason' => [['reason' => '<script>alert(1)</script>']],
            'control characters' => [['reason' => "Te\0nis"]],
            'array reason' => [['reason' => ['Tenis']]],
            'reason too long' => [['reason' => str_repeat('a', 121)]],
            'missing months' => [['months' => null]],
            'months outside whitelist' => [['months' => 2]],
            'fractional months' => [['months' => 1.5]],
            'zero months' => [['months' => 0]],
            'amount cannot fund nonzero installments' => [['amount' => '0.01', 'months' => 12]],
            'spoofed child' => [['child_user_id' => 999]],
            'spoofed parent' => [['parent_user_id' => 999]],
            'client interest' => [['annual_interest_rate' => 20]],
            'client frequency' => [['installment_frequency' => 'weekly']],
            'client due date' => [['due_date' => '2026-05-01']],
            'client status' => [['status' => 'approved']],
        ];
    }

    public function test_a_sql_looking_reason_is_saved_as_text_without_executing_it(): void
    {
        $reason = "Tenis'); DROP TABLE loans; --";
        $this->apiAs($this->child, 'POST', '/api/loans', ['amount' => 100, 'reason' => $reason, 'months' => 1])
            ->assertCreated()->assertJsonPath('loan.reason', $reason);
        $this->assertDatabaseHas('loans', ['reason' => $reason]);
        $this->requestLoan();
        $this->assertDatabaseCount('loans', 2);
    }

    public function test_missing_invalid_and_revoked_tokens_cannot_create_loans(): void
    {
        $input = ['amount' => 100, 'reason' => 'Tenis', 'months' => 1];
        $this->postJson('/api/loans', $input)->assertUnauthorized();
        $this->withToken('invalid-token')->postJson('/api/loans', $input)->assertUnauthorized();
        $token = $this->child->createToken('revoked');
        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->postJson('/api/loans', $input)->assertUnauthorized();
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_a_child_without_an_assigned_parent_cannot_request_a_loan(): void
    {
        $unassigned = User::factory()->create(['role' => 'child']);
        $this->apiAs($unassigned, 'POST', '/api/loans', ['amount' => 100, 'reason' => 'Tenis', 'months' => 1])
            ->assertForbidden()->assertJsonPath('is_enabled_functions', false);
    }

    public function test_direct_approval_uses_the_acceptance_date_and_deposits_only_once(): void
    {
        $loan = $this->requestLoan(['amount' => '1800.25']);
        $initialDueDate = $loan->initial_due_date->toDateString();
        $this->travelTo(now()->setDate(2026, 4, 6)->setTime(12, 30));

        $response = $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/approve")
            ->assertOk()->assertJsonPath('loan.status', 'approved')
            ->assertJsonPath('child_balance_cents', 181275)
            ->assertJsonPath('remaining_balance', 180025)
            ->assertJsonPath('loan.parent.balance_cents', 180025)
            ->assertJsonCount(3, 'loan.payments')
            ->assertJsonPath('loan.installment_plan.total_cents', 180025);
        $this->assertSame(['2026-05-06', '2026-06-06', '2026-07-06'], $this->paymentDates($response));
        $this->assertSame([60009, 60008, 60008], array_column($response->json('loan.payments'), 'total_amount_cents'));
        $this->assertSame($initialDueDate, $loan->fresh()->initial_due_date->toDateString());
        $this->assertSame('2026-04-06', $loan->fresh()->funds_disbursed_at->toDateString());
        $this->assertSame(0, (int) $this->parent->balance()->value('amount'));
        $this->assertSame(180025, BalanceHelper::cents($this->parent));

        $notificationCount = DomusNotification::count();
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/approve")
            ->assertOk()->assertJsonPath('child_balance_cents', 181275);
        $this->assertSame(181275, $this->childBalance());
        $this->assertDatabaseCount('loan_payments', 3);
        $this->assertDatabaseCount('balance_movements', 2);
        $this->assertSame($notificationCount, DomusNotification::count());
    }

    public function test_reviewing_identical_terms_approves_immediately_and_can_be_retried(): void
    {
        $loan = $this->requestLoan();
        $terms = $this->termsFor($loan);
        $this->travelTo(now()->addDays(5));
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)
            ->assertOk()->assertJsonPath('loan.status', 'approved')->assertJsonCount(3, 'loan.payments');
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)->assertOk();
        $this->assertDatabaseCount('balance_movements', 2);
        $this->assertSame(181250, $this->childBalance());
    }

    public function test_parent_rejection_is_final_and_has_no_financial_effect(): void
    {
        $loan = $this->requestLoan();
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/reject")
            ->assertOk()->assertJsonPath('loan.status', 'rejected');
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/reject")->assertOk();
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/approve")->assertUnprocessable();
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $this->termsFor($loan))->assertUnprocessable();
        $this->assertNotNull($loan->fresh()->parent_reviewed_at);
        $this->assertSame(1250, $this->childBalance());
        $this->assertSame(0, BalanceHelper::cents($this->parent));
        $this->assertDatabaseCount('loan_payments', 0);
        $this->assertDatabaseCount('balance_movements', 0);
    }

    public function test_a_review_retry_after_the_loan_is_paid_does_not_report_a_new_proposal(): void
    {
        $loan = $this->requestLoan(['amount' => 100, 'months' => 1]);
        $terms = $this->termsFor($loan);
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)->assertOk();
        $payment = $loan->fresh()->payments->first();
        $this->travelTo($payment->due_date->copy()->setTime(12, 0));
        $this->apiAs($this->child, 'POST', "/api/loan-payments/{$payment->id}/pay")
            ->assertOk()->assertJsonPath('loan.status', 'paid');
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)
            ->assertOk()->assertJsonPath('loan.status', 'paid')
            ->assertJsonPath('message', 'Solicitud aprobada y prestamo depositado.')
            ->assertJsonPath('child_balance_cents', 1250);
        $this->assertSame(1250, $this->childBalance());
        $this->assertSame(10000, BalanceHelper::cents($this->parent));
        $this->assertDatabaseCount('balance_movements', 4);
        $this->assertDatabaseCount('loan_payments', 1);
    }

    public function test_changed_terms_remain_visible_without_payments_until_the_child_accepts(): void
    {
        $loan = $this->requestLoan();
        $terms = $this->termsFor($loan, ['amount' => '2000.00', 'has_interest' => true, 'annual_interest_rate' => 10]);
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)
            ->assertOk()->assertJsonPath('loan.status', 'offered')->assertJsonPath('loan.payments', []);
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)->assertOk();
        $this->assertSame(1250, $this->childBalance());
        $this->assertDatabaseCount('balance_movements', 1);
        $this->assertDatabaseCount('loan_payments', 0);
        $this->assertSame('1800.00', $loan->fresh()->requested_amount);
        $this->assertSame('1800.00', $loan->fresh()->initial_total_amount);

        $this->travelTo(now()->addDays(5));
        $this->apiAs($this->child, 'GET', '/api/loans/waiting')
            ->assertOk()->assertJsonPath('loans.0.status', 'offered')
            ->assertJsonPath('loans.0.total_amount', '2200.00')->assertJsonPath('loans.0.payments', []);
        $accepted = $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'accept'])
            ->assertOk()->assertJsonPath('loan.status', 'approved')
            ->assertJsonPath('member_balance_cents', 201250)->assertJsonPath('remaining_balance', 200000)
            ->assertJsonCount(3, 'loan.payments');
        $this->assertSame(['2026-05-06', '2026-06-06', '2026-07-06'], $this->paymentDates($accepted));
        $this->assertSame(220000, array_sum(array_column($accepted->json('loan.payments'), 'total_amount_cents')));
        $this->assertSame(20000, array_sum(array_column($accepted->json('loan.payments'), 'interest_amount_cents')));

        $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'accept'])
            ->assertOk()->assertJsonPath('member_balance_cents', 201250);
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)->assertOk();
        $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'reject', 'reason' => 'No'])
            ->assertUnprocessable();
        $this->assertSame(201250, $this->childBalance());
        $this->assertSame(200000, BalanceHelper::cents($this->parent));
        $this->assertDatabaseCount('balance_movements', 2);
        $this->assertDatabaseCount('loan_payments', 3);
    }

    public function test_the_child_cannot_change_terms_or_accept_an_unreviewed_request(): void
    {
        $loan = $this->requestLoan();
        $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'accept'])->assertUnprocessable();
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $this->termsFor($loan, ['amount' => 2000]))->assertOk();
        $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'accept', 'amount' => 1])
            ->assertUnprocessable();
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/approve")->assertUnprocessable();
        $this->assertSame('offered', $loan->fresh()->status);
        $this->assertSame(1250, $this->childBalance());
        $this->assertDatabaseCount('loan_payments', 0);
    }

    public function test_waiting_for_both_parent_review_and_child_confirmation_keeps_the_full_month(): void
    {
        $loan = $this->requestLoan();
        $terms = $this->termsFor($loan, ['has_interest' => true, 'annual_interest_rate' => 10]);
        $this->travelTo(now()->addDays(5));
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)->assertOk();
        $this->assertDatabaseCount('loan_payments', 0);
        $this->assertSame(1250, $this->childBalance());
        $this->travelTo(now()->addDays(5));
        $accepted = $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'accept'])
            ->assertOk()->assertJsonPath('loan.total_amount', '1980.00');
        $this->assertSame(['2026-05-11', '2026-06-11', '2026-07-11'], $this->paymentDates($accepted));
        $this->assertSame('2026-05-01', $loan->fresh()->proposed_due_date->toDateString());
    }

    public function test_rejecting_a_modified_proposal_refunds_its_reservation_only_once(): void
    {
        $loan = $this->requestLoan();
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $this->termsFor($loan, ['amount' => '2000.25']))
            ->assertOk();
        $this->parent->balance->movements()->create([
            'amount_added' => 7500, 'movement_type' => 'loan_debit', 'resulting_balance' => 207525,
        ]);
        foreach (range(1, 2) as $attempt) {
            $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'reject', 'reason' => 'No acepto'])
                ->assertOk()->assertJsonPath('loan.status', 'rejected')->assertJsonPath('remaining_balance', 7500)
                ->assertJsonPath('member_balance_cents', 1250);
        }
        $this->assertNotNull($loan->fresh()->funds_released_at);
        $this->assertSame(1250, $this->childBalance());
        $this->assertSame(7500, BalanceHelper::cents($this->parent));
        $this->assertSame(1, BalanceMovement::where('movement_type', 'loan_refund')->count());
        $this->assertDatabaseCount('loan_payments', 0);
    }

    public function test_other_families_and_the_wrong_role_cannot_act_on_a_loan(): void
    {
        $loan = $this->requestLoan();
        $otherParent = User::factory()->create(['role' => 'parent']);
        $otherChild = User::factory()->create(['role' => 'child']);
        FamilyMember::create(['parent_user_id' => $otherParent->id, 'user_id' => $otherChild->id]);
        foreach (['approve', 'reject', 'review'] as $action) {
            $body = $action === 'review' ? $this->termsFor($loan) : [];
            $this->apiAs($otherParent, 'POST', "/api/loans/{$loan->id}/{$action}", $body)->assertForbidden();
            $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/{$action}", $body)->assertForbidden();
        }
        foreach ([$this->parent, $otherChild] as $actor) {
            $this->apiAs($actor, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'accept'])->assertForbidden();
            $this->apiAs($actor, 'GET', '/api/loans')->assertOk();
        }
        $this->apiAs($otherParent, 'GET', '/api/loans')->assertOk()->assertJsonPath('loans', []);
        $this->apiAs($otherChild, 'GET', '/api/loans/waiting')->assertOk()->assertJsonPath('loans', []);
        $this->assertSame('pending', $loan->fresh()->status);
        $this->assertDatabaseCount('balance_movements', 0);
    }

    #[DataProvider('offerResponses')]
    public function test_existing_parent_offers_keep_their_acceptance_and_refund_flow(string $action): void
    {
        $input = [
            'child_user_id' => $this->child->id, 'amount' => '150.50', 'reason' => 'Tenis',
            'due_date' => '2026-04-08', 'installments_count' => 3, 'installment_frequency' => 'weekly',
            'has_interest' => false, 'interest_mode' => 'percent', 'annual_interest_rate' => 0, 'fixed_interest_amount' => 0,
        ];
        $created = $this->apiAs($this->parent, 'POST', '/api/loans', $input)
            ->assertCreated()->assertJsonPath('loan.status', 'offered')->assertJsonPath('loan.payment_summary.total_installments', 0);
        $loanId = $created->json('loan.id');
        $this->assertSame(1250, $this->childBalance());
        $this->assertSame(15050, BalanceHelper::cents($this->parent));
        $this->travelTo(now()->addDays(5));
        foreach (range(1, 2) as $attempt) {
            $response = $this->apiAs($this->child, 'POST', "/api/loans/{$loanId}/respond", ['action' => $action, 'reason' => 'Mi decision'])
                ->assertOk()->assertJsonPath('member_balance_cents', $action === 'accept' ? 16300 : 1250);
            if ($action === 'accept') {
                $this->assertSame(['2026-04-13', '2026-04-20', '2026-04-27'], $this->paymentDates($response));
            }
        }
        $this->assertSame($action === 'accept' ? 15050 : 0, BalanceHelper::cents($this->parent));
        $this->assertDatabaseCount('balance_movements', 2);
        $this->assertDatabaseCount('loan_payments', $action === 'accept' ? 3 : 0);
    }

    public static function offerResponses(): array
    {
        return [['accept'], ['reject']];
    }

    public function test_incomplete_and_incoherent_parent_terms_are_rejected(): void
    {
        $loan = $this->requestLoan();
        foreach ([
            ['annual_interest_rate' => null], ['fixed_interest_amount' => null],
            ['has_interest' => true, 'annual_interest_rate' => 0],
            ['has_interest' => true, 'annual_interest_rate' => 10, 'fixed_interest_amount' => 10],
            ['has_interest' => false, 'annual_interest_rate' => 10],
            ['has_interest' => true, 'interest_mode' => 'fixed', 'fixed_interest_amount' => 10, 'annual_interest_rate' => 5],
        ] as $changes) {
            $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $this->termsFor($loan, $changes))->assertUnprocessable();
        }
        $this->assertSame('pending', $loan->fresh()->status);
        $this->assertDatabaseCount('balance_movements', 0);
    }

    public function test_parent_offers_with_zero_interest_accept_either_existing_interest_mode(): void
    {
        $input = [
            'child_user_id' => $this->child->id, 'amount' => 100, 'reason' => 'Tenis',
            'due_date' => '2026-04-15', 'installments_count' => 2, 'installment_frequency' => 'biweekly',
            'has_interest' => false, 'interest_mode' => 'fixed', 'annual_interest_rate' => 0, 'fixed_interest_amount' => 0,
        ];
        $created = $this->apiAs($this->parent, 'POST', '/api/loans', $input)->assertCreated();
        $this->apiAs($this->child, 'POST', '/api/loans/'.$created->json('loan.id').'/respond', ['action' => 'accept'])
            ->assertOk()->assertJsonPath('loan.total_amount', '100.00');
        $this->assertSame(['2026-04-15', '2026-04-29'], Loan::findOrFail($created->json('loan.id'))
            ->payments->map(fn ($payment): string => $payment->due_date->toDateString())->all());

        unset($input['annual_interest_rate']);
        $this->apiAs($this->parent, 'POST', '/api/loans', $input)->assertUnprocessable()
            ->assertJsonValidationErrors('annual_interest_rate');
    }

    public function test_review_normalizes_a_date_before_comparing_or_persisting_it(): void
    {
        $loan = $this->requestLoan();
        $terms = $this->termsFor($loan, ['due_date' => '2026-05-01T00:00:00Z']);
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)
            ->assertOk()->assertJsonPath('loan.status', 'approved');
    }

    public function test_principal_and_interest_rounding_agrees_with_the_displayed_plan(): void
    {
        $loan = $this->requestLoan();
        $terms = $this->termsFor($loan, [
            'amount' => '10.01', 'has_interest' => true, 'interest_mode' => 'fixed', 'fixed_interest_amount' => '0.01',
        ]);
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $terms)->assertOk();
        $response = $this->apiAs($this->child, 'POST', "/api/loans/{$loan->id}/respond", ['action' => 'accept'])->assertOk();
        $this->assertSame([334, 334, 334], array_column($response->json('loan.payments'), 'total_amount_cents'));
        $this->assertSame(1001, array_sum(array_column($response->json('loan.payments'), 'principal_amount_cents')));
        $this->assertSame(1, array_sum(array_column($response->json('loan.payments'), 'interest_amount_cents')));
        $response->assertJsonPath('loan.installment_plan.groups.0.count', 3)
            ->assertJsonPath('loan.installment_plan.groups.0.amount_cents', 334);
    }

    public function test_month_end_payments_keep_the_day_of_acceptance(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 26));
        $loan = $this->requestLoan();
        $this->travelTo(now()->setDate(2026, 1, 31));
        $response = $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/approve")->assertOk();
        $this->assertSame(['2026-02-28', '2026-03-31', '2026-04-30'], $this->paymentDates($response));
    }

    public function test_installment_payments_preserve_the_existing_simulated_money_accounting(): void
    {
        $loan = $this->requestLoan(['amount' => '1800.25']);
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/approve")->assertOk();
        $payments = $loan->fresh()->payments;
        $otherChild = User::factory()->create(['role' => 'child']);
        $this->apiAs($otherChild, 'POST', "/api/loan-payments/{$payments[0]->id}/pay")->assertForbidden();
        $this->apiAs($this->child, 'POST', "/api/loan-payments/{$payments[0]->id}/pay")->assertUnprocessable();
        $this->assertSame(181275, $this->childBalance());

        foreach ($payments as $index => $payment) {
            $this->travelTo($payment->due_date->copy()->setTime(12, 0));
            $this->apiAs($this->child, 'POST', "/api/loan-payments/{$payment->id}/pay")
                ->assertOk()->assertJsonPath('payment.status', 'paid');
            $balanceAfterPayment = $this->childBalance();
            $this->apiAs($this->child, 'POST', "/api/loan-payments/{$payment->id}/pay")->assertUnprocessable();
            $this->assertSame($balanceAfterPayment, $this->childBalance());
        }
        $this->assertSame('paid', $loan->fresh()->status);
        $this->assertSame(1250, $this->childBalance());
        $this->assertSame(180025, (int) $this->parent->balance()->value('amount'));
        $this->assertSame(180025, BalanceHelper::cents($this->parent));
        $this->assertDatabaseCount('balance_movements', 8);
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/approve")->assertOk();
        $this->assertSame(1250, $this->childBalance());
        $this->apiAs($this->parent, 'GET', '/api/loans/active-total')->assertOk()
            ->assertJsonPath('estimated_total_paid_cents', 180025)
            ->assertJsonPath('interest_generated_historical_cents', 0);
    }

    public function test_a_schedule_retry_keeps_paid_installments_and_their_dates(): void
    {
        $loan = $this->requestLoan();
        $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/approve")->assertOk();
        $firstPayment = $loan->fresh()->payments->first();
        $firstPayment->update(['status' => 'paid', 'paid_at' => now()]);
        $original = $firstPayment->fresh()->getAttributes();
        $this->travelTo(now()->addDays(5));
        app(LoanPaymentService::class)->ensurePaymentsForLoan($loan->fresh());
        $this->assertSame($original, $firstPayment->fresh()->getAttributes());
        $this->assertDatabaseCount('loan_payments', 3);
    }

    public function test_loan_read_summaries_keep_cents_after_the_schema_change(): void
    {
        $this->requestLoan(['amount' => '100.25']);
        $this->requestLoan(['amount' => '200.25']);
        $admin = Admin::create(['name' => 'Admin', 'email' => 'loan-admin@domus.test', 'password' => bcrypt('password')]);
        $summary = app(AdminLoanReadService::class)->forParents($admin, [$this->parent->id])[$this->parent->id]['summary'];
        $this->assertSame(300.5, $summary['total_amount']);
        $this->assertSame(300.5, $summary['open_amount']);
    }

    #[DataProvider('activationPaths')]
    public function test_a_calendar_failure_rolls_back_the_deposit_status_and_movements(bool $modified): void
    {
        $loan = $this->requestLoan();
        if ($modified) {
            $this->apiAs($this->parent, 'POST', "/api/loans/{$loan->id}/review", $this->termsFor($loan, ['amount' => 2000]))->assertOk();
        }
        $movementCount = BalanceMovement::count();
        $notificationCount = DomusNotification::count();
        $parentUsed = BalanceHelper::cents($this->parent);
        $this->partialMock(LoanPaymentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ensurePaymentsForLoan')->once()->andReturnUsing(function (Loan $loan): void {
                (new LoanPaymentService)->ensurePaymentsForLoan($loan);
                throw new \RuntimeException('Simulated calendar failure');
            });
        });
        $this->withoutExceptionHandling();
        try {
            $this->apiAs($modified ? $this->child : $this->parent, 'POST',
                "/api/loans/{$loan->id}/".($modified ? 'respond' : 'approve'),
                $modified ? ['action' => 'accept'] : []);
            $this->fail('The simulated failure must interrupt activation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated calendar failure', $exception->getMessage());
        }
        $this->assertSame($modified ? 'offered' : 'pending', $loan->fresh()->status);
        $this->assertNull($loan->fresh()->funds_disbursed_at);
        $this->assertSame(1250, $this->childBalance());
        $this->assertSame($parentUsed, BalanceHelper::cents($this->parent));
        $this->assertSame($movementCount, BalanceMovement::count());
        $this->assertSame($notificationCount, DomusNotification::count());
        $this->assertDatabaseCount('loan_payments', 0);
    }

    public static function activationPaths(): array
    {
        return [[false], [true]];
    }

    private function apiAs(User $actor, string $method, string $uri, array $data = []): TestResponse
    {
        $this->tokens[$actor->id] ??= $actor->createToken('loan-workflow-test')->plainTextToken;
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokens[$actor->id])->json($method, $uri, $data);
    }

    private function requestLoan(array $changes = []): Loan
    {
        $response = $this->apiAs($this->child, 'POST', '/api/loans', array_replace([
            'amount' => '1800.00', 'reason' => 'Tenis', 'months' => 3,
        ], $changes))->assertCreated();

        return Loan::findOrFail($response->json('loan.id'));
    }

    private function termsFor(Loan $loan, array $changes = []): array
    {
        return array_replace([
            'amount' => $loan->amount, 'due_date' => $loan->due_date->toDateString(),
            'installments_count' => $loan->installments_count, 'installment_frequency' => $loan->installment_frequency,
            'has_interest' => false, 'interest_mode' => 'percent', 'annual_interest_rate' => 0, 'fixed_interest_amount' => 0,
        ], $changes);
    }

    private function childBalance(): int
    {
        return (int) $this->child->balance()->value('amount');
    }

    private function paymentDates(TestResponse $response): array
    {
        return array_map(fn (string $date): string => substr($date, 0, 10), array_column($response->json('loan.payments'), 'due_date'));
    }
}
