<?php

namespace App\Support;

use App\Models\User;

class BalanceHelper
{
    public static function cents(User $user): int
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
