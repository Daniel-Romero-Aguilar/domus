<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $badges = [
            'primer-abono' => [
                'title' => 'Insignia de primer abono',
                'description' => 'Gana esta insignia al recibir tu primer abono y dar tu primer paso en tu historial financiero.',
            ],
            'primer-pago-prestamo' => [
                'title' => 'Insignia de primer pago de prestamo',
                'description' => 'Gana esta insignia al hacer tu primer pago de prestamo y demostrar constancia con tus compromisos.',
            ],
            'primera-tarea-completada' => [
                'title' => 'Insignia de primera tarea completada',
                'description' => 'Gana esta insignia al completar tu primera tarea familiar.',
            ],
        ];

        foreach ($badges as $slug => $values) {
            DB::table('domus_missions')
                ->where('slug', $slug)
                ->update($values + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $missions = [
            'primer-abono' => [
                'title' => 'Primer abono',
                'description' => 'Recibe tu primer abono y da tu primer paso en tu historial financiero.',
            ],
            'primer-pago-prestamo' => [
                'title' => 'Primer pago de prestamo',
                'description' => 'Haz tu primer pago de prestamo y demuestra constancia con tus compromisos.',
            ],
            'primera-tarea-completada' => [
                'title' => 'Primera tarea completada',
                'description' => 'Completa tu primera tarea familiar para ganar tus primeros puntos Domus.',
            ],
        ];

        foreach ($missions as $slug => $values) {
            DB::table('domus_missions')
                ->where('slug', $slug)
                ->update($values + ['updated_at' => now()]);
        }
    }
};
