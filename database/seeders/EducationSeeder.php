<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EducationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $categories = [
            [
                'name' => 'Finanzas personales',
                'slug' => 'finanzas-personales',
                'description' => 'Habitos, ahorro, presupuesto y decisiones del dia a dia.',
                'sort_order' => 1,
                'is_active' => 1,
            ],
            [
                'name' => 'Matematicas financieras',
                'slug' => 'matematicas-financieras',
                'description' => 'Porcentajes, intereses, pagos y calculos utiles.',
                'sort_order' => 2,
                'is_active' => 1,
            ],
            [
                'name' => 'Contabilidad',
                'slug' => 'contabilidad',
                'description' => 'Registro de dinero, movimientos y control simple.',
                'sort_order' => 3,
                'is_active' => 1,
            ],
            [
                'name' => 'Economia basica',
                'slug' => 'economia-basica',
                'description' => 'Conceptos generales para entender el dinero y su flujo.',
                'sort_order' => 4,
                'is_active' => 1,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('course_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                $category + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $categoryId = DB::table('course_categories')
            ->where('slug', 'finanzas-personales')
            ->value('id');

        if (! $categoryId) {
            return;
        }

        DB::table('courses')->updateOrInsert(
            ['slug' => 'curso-1-ahorro'],
            [
                'course_category_id' => $categoryId,
                'title' => 'Curso 1: Ahorro para ninos',
                'description' => 'Conceptos basicos de ahorro en familia.',
                'image_url' => 'https://images.unsplash.com/photo-1532634726-8b9fb99825f4?q=80&w=1400&auto=format&fit=crop',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $courseRecord = DB::table('courses')->where('slug', 'curso-1-ahorro')->first();
        if (! $courseRecord) {
            return;
        }

        DB::table('lessons')->updateOrInsert(
            ['slug' => 'leccion-1-que-es-ahorrar'],
            [
                'course_id' => $courseRecord->id,
                'name' => 'Que es ahorrar',
                'title' => 'Leccion 1: Que es ahorrar',
                'description' => 'Primer acercamiento al ahorro y su valor en familia.',
                'position' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $lessonRecord = DB::table('lessons')->where('slug', 'leccion-1-que-es-ahorrar')->first();
        if (! $lessonRecord) {
            return;
        }

        $parts = [
            ['type' => 'title', 'content' => 'Que es ahorrar?', 'meta' => null, 'position' => 1],
            ['type' => 'text', 'content' => 'Ahorrar es guardar una parte de tu dinero para usarlo despues.', 'meta' => null, 'position' => 2],
            ['type' => 'video', 'content' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'meta' => null, 'position' => 3],
            ['type' => 'image', 'content' => 'https://images.unsplash.com/photo-1579621970795-87facc2f976d?q=80&w=1200&auto=format&fit=crop', 'meta' => null, 'position' => 4],
        ];

        DB::table('lesson_parts')->where('lesson_id', $lessonRecord->id)->delete();

        foreach ($parts as $part) {
            DB::table('lesson_parts')->insert([
                'lesson_id' => $lessonRecord->id,
                'type' => $part['type'],
                'content' => $part['content'],
                'meta' => $part['meta'],
                'position' => $part['position'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
