<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DomusLevelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $levels = [
            ['level_number' => 1, 'name' => 'Principiante', 'min_points' => 0, 'max_points' => 20, 'definition' => 'Estas dando tus primeros pasos y aprendiendo como funcionan tus puntos Domus.', 'sort_order' => 1],
            ['level_number' => 2, 'name' => 'Curioso', 'min_points' => 21, 'max_points' => 50, 'definition' => 'Ya descubriste que ahorrar, cumplir y participar te ayuda a crecer.', 'sort_order' => 2],
            ['level_number' => 3, 'name' => 'Aprendiz', 'min_points' => 51, 'max_points' => 100, 'definition' => 'Comienzas a formar habitos y a entender mejor tus decisiones financieras.', 'sort_order' => 3],
            ['level_number' => 4, 'name' => 'Constante', 'min_points' => 101, 'max_points' => 180, 'definition' => 'Tu progreso ya no es casualidad: eres mas constante y responsable.', 'sort_order' => 4],
            ['level_number' => 5, 'name' => 'Organizado', 'min_points' => 181, 'max_points' => 300, 'definition' => 'Sabes ordenar tus metas y avanzar con disciplina en tu camino Domus.', 'sort_order' => 5],
            ['level_number' => 6, 'name' => 'Estratega', 'min_points' => 301, 'max_points' => 450, 'definition' => 'No solo cumples, tambien piensas antes de actuar y usas mejor tus recursos.', 'sort_order' => 6],
            ['level_number' => 7, 'name' => 'Responsable', 'min_points' => 451, 'max_points' => 650, 'definition' => 'Tu historial demuestra compromiso y una forma madura de manejar tus logros.', 'sort_order' => 7],
            ['level_number' => 8, 'name' => 'Experto familiar', 'min_points' => 651, 'max_points' => 850, 'definition' => 'Ya eres un referente en casa para cumplir metas y tomar buenas decisiones.', 'sort_order' => 8],
            ['level_number' => 9, 'name' => 'Maestro Domus', 'min_points' => 851, 'max_points' => 1199, 'definition' => 'Tu nivel refleja experiencia, constancia y dominio de las dinamicas Domus.', 'sort_order' => 9],
            ['level_number' => 10, 'name' => 'Super profesional', 'min_points' => 1200, 'max_points' => null, 'definition' => 'Llegaste a la cima: tu historial de puntos muestra un dominio sobresaliente.', 'sort_order' => 10],
        ];

        foreach ($levels as $level) {
            DB::table('domus_levels')->updateOrInsert(
                ['level_number' => $level['level_number']],
                $level + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
