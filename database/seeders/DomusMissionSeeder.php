<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DomusMissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $missions = [
            [
                'slug' => 'primer-abono',
                'title' => 'Primer abono',
                'description' => 'Recibe tu primer abono y da tu primer paso en tu historial financiero.',
                'points_reward' => 5,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'slug' => 'primer-pago-prestamo',
                'title' => 'Primer pago de prestamo',
                'description' => 'Haz tu primer pago de prestamo y demuestra constancia con tus compromisos.',
                'points_reward' => 5,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'slug' => 'primera-tarea-completada',
                'title' => 'Primera tarea completada',
                'description' => 'Completa tu primera tarea familiar para ganar tus primeros puntos Domus.',
                'points_reward' => 5,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($missions as $mission) {
            DB::table('domus_missions')->updateOrInsert(
                ['slug' => $mission['slug']],
                $mission + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
