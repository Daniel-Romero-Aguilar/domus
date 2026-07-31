<?php

namespace App\Support;

final class LoanAmountGuard
{
    public static function isPositive(int $amount, int $totalAmount, int $installmentAmount): bool
    {
        return $amount > 0 && $totalAmount > 0 && $installmentAmount > 0;
    }

    public static function assertPositive(int $amount, int $totalAmount, int $installmentAmount): void
    {
        foreach ([
            'amount' => $amount,
            'total_amount' => $totalAmount,
            'installment_amount' => $installmentAmount,
        ] as $field => $value) {
            if ($value <= 0) {
                throw new \LogicException('LOAN_INVARIANT_FAILED: '.$field.' must be greater than zero.');
            }
        }
    }

    /**
     * Confirms that the database returned the same monetary values that the
     * application sent to the insert. This catches silent conversions such as
     * 20 becoming 0 before payments or notifications are created.
     */
    public static function assertMatches(array $expected, array $actual, string $stage = 'unknown'): void
    {
        foreach (['amount', 'total_amount', 'installment_amount'] as $field) {
            $expectedValue = (int) ($expected[$field] ?? 0);
            $actualValue = (int) ($actual[$field] ?? 0);

            if ($expectedValue !== $actualValue) {
                throw new \LogicException(
                    'LOAN_AMOUNT_TRANSFORMATION_FAILED: '.json_encode([
                        'field' => $field,
                        'stage' => $stage,
                        'expected' => $expectedValue,
                        'persisted' => $actualValue,
                        'expected_values' => array_map('intval', $expected),
                        'persisted_values' => array_map('intval', $actual),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
            }
        }
    }
}
