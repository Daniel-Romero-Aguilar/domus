<?php

namespace Database\Seeders;

use App\Models\Balance;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoFamilySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $parent = User::query()->updateOrCreate(
            ['email' => 'correo@correo.com'],
            [
                'name' => 'correo@correo.com',
                'username' => 'correo@correo.com',
                'role' => 'parent',
                'password' => Hash::make('correo@correo.com'),
                'email_verified_at' => $now,
            ]
        );

        Balance::query()->updateOrCreate(
            ['user_id' => $parent->id],
            ['amount' => 0]
        );

        $child = User::query()->updateOrCreate(
            ['username' => 'JuanPerez'],
            [
                'name' => 'JuanPerez',
                'email' => 'juanperez@domus.local',
                'role' => 'child',
                'password' => Hash::make('JuanPerez'),
                'email_verified_at' => $now,
            ]
        );

        Balance::query()->updateOrCreate(
            ['user_id' => $child->id],
            ['amount' => 0]
        );

        FamilyMember::query()->updateOrCreate(
            ['user_id' => $child->id],
            [
                'parent_user_id' => $parent->id,
                'is_minor' => true,
                'guardian_declaration_accepted' => true,
            ]
        );
    }
}
