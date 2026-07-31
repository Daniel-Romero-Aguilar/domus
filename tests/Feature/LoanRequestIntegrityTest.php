<?php

namespace Tests\Feature;

use App\Http\Middleware\LogFailedUserActions;
use App\Support\LoanAmountGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoanRequestIntegrityTest extends TestCase
{
    public function test_zero_amount_is_rejected_by_the_loan_integrity_guard(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('LOAN_INVARIANT_FAILED: amount must be greater than zero.');

        LoanAmountGuard::assertPositive(0, 250, 250);
    }

    public function test_positive_loan_amounts_are_accepted_by_the_integrity_guard(): void
    {
        $this->assertTrue(LoanAmountGuard::isPositive(250, 250, 250));
    }

    public function test_a_nonzero_amount_changed_to_zero_is_identified_as_a_transformation(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('LOAN_AMOUNT_TRANSFORMATION_FAILED');

        LoanAmountGuard::assertMatches(
            ['amount' => 20, 'total_amount' => 20, 'installment_amount' => 20],
            ['amount' => 0, 'total_amount' => 0, 'installment_amount' => 0],
        );
    }

    public function test_failed_loan_action_writes_one_detailed_warning(): void
    {
        $request = Request::create('/api/loans', 'POST', [
            'child_user_id' => 7,
            'amount' => 0,
        ]);
        $request->setUserResolver(fn () => (object) [
            'id' => 7,
            'role' => 'child',
        ]);
        Log::spy();

        app(LogFailedUserActions::class)->handle(
            $request,
            fn () => response()->json([
                'message' => 'El monto del prestamo debe ser mayor que cero.',
            ], 422)
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'PRESTAMO SOLICITADO FALLIDO'
                    && ($context['failure_type'] ?? null) === 'http_response'
                    && ($context['status'] ?? null) === 422
                    && ($context['request_data']['amount'] ?? null) === 0
                    && ($context['operation'] ?? null) === 'solicitar u ofrecer prestamo';
            });
    }

    public function test_amount_transformation_warning_exposes_expected_and_persisted_values(): void
    {
        $request = Request::create('/api/loans', 'POST', [
            'child_user_id' => 7,
            'amount' => '20',
        ]);
        $request->setUserResolver(fn () => (object) [
            'id' => 7,
            'role' => 'child',
        ]);
        Log::spy();

        try {
            app(LogFailedUserActions::class)->handle(
                $request,
                fn () => throw new \LogicException(
                    'LOAN_AMOUNT_TRANSFORMATION_FAILED: '.json_encode([
                        'field' => 'amount',
                        'stage' => 'after_loan_insert_reload',
                        'expected' => 20,
                        'persisted' => 0,
                    ])
                )
            );
        } catch (\LogicException) {
            // The middleware must rethrow so the transaction is rolled back.
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'PRESTAMO SOLICITADO FALLIDO'
                    && ($context['diagnostic']['field'] ?? null) === 'amount'
                    && ($context['diagnostic']['expected'] ?? null) === 20
                    && ($context['diagnostic']['persisted'] ?? null) === 0
                    && ($context['diagnostic']['stage'] ?? null) === 'after_loan_insert_reload';
            });
    }
}
