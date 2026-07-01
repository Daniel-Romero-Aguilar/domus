<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DomusMissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $badges = [
            [
                'slug' => 'primer-abono',
                'title' => 'Insignia de primer abono',
                'description' => 'Gana esta insignia al recibir tu primer abono y dar tu primer paso en tu historial financiero.',
                'points_reward' => 5,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'slug' => 'primer-pago-prestamo',
                'title' => 'Insignia de primer pago de prestamo',
                'description' => 'Gana esta insignia al hacer tu primer pago de prestamo y demostrar constancia con tus compromisos.',
                'points_reward' => 5,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'slug' => 'primera-tarea-completada',
                'title' => 'Insignia de primera tarea completada',
                'description' => 'Gana esta insignia al completar tu primera tarea familiar.',
                'points_reward' => 5,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($badges as $badge) {
            DB::table('domus_missions')->updateOrInsert(
                ['slug' => $badge['slug']],
                $badge + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
