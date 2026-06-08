<?php

namespace App\Services;

use App\Models\DomusNotification;
use App\Models\User;

class DomusNotificationService
{
    public function __invoke(int $userId, string $category, string $section, string $text): DomusNotification
    {
        return $this->record($userId, $category, $section, $text);
    }

    public function record(int $userId, string $category, string $section, string $text): DomusNotification
    {
        return DomusNotification::create([
            'user_id' => $userId,
            'section' => trim($section),
            'category' => trim($category),
            'text' => trim($text),
        ]);
    }

    public function recordForParent(int $userId, string $category, string $section, string $text): DomusNotification
    {
        $this->assertRole($userId, ['parent']);

        return $this->record($userId, $category, $section, $text);
    }

    public function recordForMember(int $userId, string $category, string $section, string $text): DomusNotification
    {
        $this->assertRole($userId, ['child', 'member']);

        return $this->record($userId, $category, $section, $text);
    }

    public function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2, '.', ',');
    }

    private function assertRole(int $userId, array $roles): void
    {
        $role = User::query()
            ->whereKey($userId)
            ->value('role');

        if (! in_array($role, $roles, true)) {
            throw new \InvalidArgumentException('Notification recipient role mismatch.');
        }
    }
}
