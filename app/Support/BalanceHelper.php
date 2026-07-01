<?php

namespace App\Support;

use App\Models\BalanceMovement;
use App\Models\User;

class BalanceHelper
{
    private const PARENT_MONEY_OUT_MOVEMENTS = [
        'allowance_debit',
        'loan_debit',
        'loan_reserve',
        'task_reserve',
        'transfer_debit',
    ];

    private const PARENT_MONEY_REVERSAL_MOVEMENTS = [
        'loan_refund',
        'task_refund',
    ];

    public static function cents(User $user): int
    {
        if ($user->role === 'parent') {
            return self::parentMoneyUsedCents($user);
        }

        return self::storedCents($user);
    }

    public static function storedCents(User $user): int
    {
        if ($user->relationLoaded('balance')) {
            return (int) ($user->balance?->amount ?? 0);
        }

        return (int) ($user->balance()->value('amount') ?? 0);
    }

    public static function display(User $user): string
    {
        return '$'.number_format(self::cents($user) / 100, 2, '.', ',');
    }

    public static function displayCents(int $cents): string
    {
        return '$'.number_format($cents / 100, 2, '.', ',');
    }

    public static function parentMoneyUsedCents(User $user): int
    {
        $balanceId = $user->relationLoaded('balance')
            ? $user->balance?->id
            : $user->balance()->value('id');

        if (! $balanceId) {
            return 0;
        }

        $out = (int) BalanceMovement::query()
            ->where('balance_id', $balanceId)
            ->whereIn('movement_type', self::PARENT_MONEY_OUT_MOVEMENTS)
            ->sum('amount_added');

        $reversed = (int) BalanceMovement::query()
            ->where('balance_id', $balanceId)
            ->whereIn('movement_type', self::PARENT_MONEY_REVERSAL_MOVEMENTS)
            ->sum('amount_added');

        return max($out - $reversed, 0);
    }

    public static function parseMoneyToCents(string|int|float $value): int
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new \InvalidArgumentException('Invalid money format.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + ((int) $fraction);
    }
}
