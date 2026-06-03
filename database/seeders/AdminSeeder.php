<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admins')->updateOrInsert(
            ['email' => 'admin@domus.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin12345!'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
