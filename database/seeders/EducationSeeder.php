<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EducationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('lesson_exam_reward_grants')->delete();
        DB::table('lesson_exam_reward_rules')->delete();
        DB::table('lesson_parts')->delete();
        DB::table('lessons')->delete();
        DB::table('courses')->delete();
        DB::table('course_categories')->delete();

        $catalog = [
            [
                'category' => [
                    'name' => 'Ahorro inteligente',
                    'slug' => 'ahorro-inteligente',
                    'description' => 'Ideas simples para guardar dinero con calma y constancia.',
                    'sort_order' => 1,
                    'is_active' => 1,
                ],
                'course' => [
                    'title' => 'Mi primer plan de ahorro',
                    'slug' => 'mi-primer-plan-de-ahorro',
                    'description' => 'Un curso corto para entender por que ahorrar, como empezar y como mantener el habito.',
                    'image_url' => 'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?q=80&w=1200&auto=format&fit=crop',
                    'sort_order' => 1,
                    'is_active' => 1,
                ],
                'lessons' => [
                    [
                        'name' => 'Guardar un poco cuenta',
                        'title' => 'Leccion 1: Guardar un poco cuenta',
                        'slug' => 'guardar-un-poco-cuenta',
                        'description' => 'Ahorrar no empieza con mucho dinero, empieza con una decision constante.',
                        'position' => 1,
                        'parts' => [
                            ['type' => 'title', 'content' => 'Guardar un poco tambien es avanzar', 'position' => 1],
                            ['type' => 'text', 'content' => 'Si guardas una parte pequena cada semana, tu ahorro crece sin que se sienta pesado. Lo importante es comenzar y repetirlo.', 'position' => 2],
                            ['type' => 'image', 'content' => 'https://images.unsplash.com/photo-1579621970795-87facc2f976d?q=80&w=1200&auto=format&fit=crop', 'position' => 3],
                        ],
                    ],
                    [
                        'name' => 'Metas que si motivan',
                        'title' => 'Leccion 2: Metas que si motivan',
                        'slug' => 'metas-que-si-motivan',
                        'description' => 'Ponerle nombre a tu meta hace mas facil seguir ahorrando.',
                        'position' => 2,
                        'parts' => [
                            ['type' => 'title', 'content' => 'Tu ahorro necesita una meta clara', 'position' => 1],
                            ['type' => 'text', 'content' => 'Cuando sabes para que ahorras, es mas facil decir que no a compras pequenas que te alejan de lo que realmente quieres.', 'position' => 2],
                            ['type' => 'image', 'content' => 'https://images.unsplash.com/photo-1484981138541-3d074aa97716?q=80&w=1200&auto=format&fit=crop', 'position' => 3],
                        ],
                    ],
                    [
                        'name' => 'Hacer del ahorro un habito',
                        'title' => 'Leccion 3: Hacer del ahorro un habito',
                        'slug' => 'hacer-del-ahorro-un-habito',
                        'description' => 'Pequenas rutinas ayudan a que ahorrar sea algo natural.',
                        'position' => 3,
                        'parts' => [
                            ['type' => 'title', 'content' => 'La constancia vale mas que la emocion del primer dia', 'position' => 1],
                            ['type' => 'text', 'content' => 'Elige un dia fijo para revisar tu dinero y separar una parte. Repetir el proceso hace que tu ahorro deje de depender del animo del momento.', 'position' => 2],
                            ['type' => 'image', 'content' => 'https://images.unsplash.com/photo-1518186233392-c232efbf2373?q=80&w=1200&auto=format&fit=crop', 'position' => 3],
                            ['type' => 'quiz', 'content' => 'Responde este quiz corto para revisar las ideas principales.', 'position' => 4, 'meta' => [
                                'uuid' => 'quiz-ahorro-001',
                                'questions' => [
                                    ['uuid' => 'quiz-ahorro-001-q1', 'question' => 'Que ayuda a mantener un ahorro constante?', 'option_a' => 'Esperar a tener mucho dinero', 'option_b' => 'Ahorrar solo cuando sobra', 'option_c' => 'Tener una meta clara', 'option_d' => 'Gastar primero y ver despues', 'correct_option' => 'c'],
                                    ['uuid' => 'quiz-ahorro-001-q2', 'question' => 'Que hace mas facil seguir ahorrando?', 'option_a' => 'Cambiar de meta cada semana', 'option_b' => 'Ponerle nombre a tu meta', 'option_c' => 'No revisar tu dinero', 'option_d' => 'Comprar impulsivamente', 'correct_option' => 'b'],
                                ],
                            ]],
                            ['type' => 'exam', 'content' => 'Examen corto: selecciona una respuesta por pregunta.', 'position' => 5, 'reward_rule' => [
                                'approved_points' => 10,
                                'excellent_points' => 25,
                            ], 'meta' => [
                                'uuid' => 'exam-ahorro-001',
                                'questions' => [
                                    ['uuid' => 'exam-ahorro-001-q1', 'question' => 'Que vale mas para ahorrar bien?', 'option_a' => 'La constancia', 'option_b' => 'La suerte', 'option_c' => 'El impulso del momento', 'option_d' => 'No planear nada', 'correct_option' => 'a'],
                                    ['uuid' => 'exam-ahorro-001-q2', 'question' => 'Que te ayuda a que ahorrar sea un habito?', 'option_a' => 'Separar dinero solo una vez', 'option_b' => 'Elegir un dia fijo para revisar tu dinero', 'option_c' => 'Gastar antes de ahorrar', 'option_d' => 'Olvidar tus metas', 'correct_option' => 'b'],
                                    ['uuid' => 'exam-ahorro-001-q3', 'question' => 'Que puede pasar si tienes una meta clara?', 'option_a' => 'Te alejas mas rapido de ella', 'option_b' => 'Te cuesta mas decir que no a gastos pequenos', 'option_c' => 'Te resulta mas facil mantener el ahorro', 'option_d' => 'Ahorrar deja de importar', 'correct_option' => 'c'],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
            [
                'category' => [
                    'name' => 'Prestamos sin miedo',
                    'slug' => 'prestamos-sin-miedo',
                    'description' => 'Conceptos basicos para entender un prestamo y pagarlo con orden.',
                    'sort_order' => 2,
                    'is_active' => 1,
                ],
                'course' => [
                    'title' => 'Entiende tu primer prestamo',
                    'slug' => 'entiende-tu-primer-prestamo',
                    'description' => 'Aprende que significa pedir dinero, como revisar pagos y por que conviene cumplir a tiempo.',
                    'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?q=80&w=1200&auto=format&fit=crop',
                    'sort_order' => 1,
                    'is_active' => 1,
                ],
                'lessons' => [
                    [
                        'name' => 'Que es un prestamo',
                        'title' => 'Leccion 1: Que es un prestamo',
                        'slug' => 'que-es-un-prestamo',
                        'description' => 'Un prestamo es dinero que recibes hoy y devuelves despues.',
                        'position' => 1,
                        'parts' => [
                            ['type' => 'title', 'content' => 'Pedir dinero implica una responsabilidad', 'position' => 1],
                            ['type' => 'text', 'content' => 'Un prestamo puede ayudarte a resolver una necesidad, pero siempre debes saber cuanto recibes, cuanto pagas y cuando lo vas a devolver.', 'position' => 2],
                            ['type' => 'image', 'content' => 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?q=80&w=1200&auto=format&fit=crop', 'position' => 3],
                        ],
                    ],
                    [
                        'name' => 'Leer antes de aceptar',
                        'title' => 'Leccion 2: Leer antes de aceptar',
                        'slug' => 'leer-antes-de-aceptar',
                        'description' => 'Antes de aceptar un prestamo revisa monto, plazo y pagos.',
                        'position' => 2,
                        'parts' => [
                            ['type' => 'title', 'content' => 'Entender las condiciones evita sorpresas', 'position' => 1],
                            ['type' => 'text', 'content' => 'Mira con calma cuanto te prestan, cuantas cuotas son y cuanto termina costando. Hacer preguntas antes es mejor que arrepentirte despues.', 'position' => 2],
                            ['type' => 'image', 'content' => 'https://images.unsplash.com/photo-1554224154-22dec7ec8818?q=80&w=1200&auto=format&fit=crop', 'position' => 3],
                        ],
                    ],
                    [
                        'name' => 'Pagar a tiempo ayuda',
                        'title' => 'Leccion 3: Pagar a tiempo ayuda',
                        'slug' => 'pagar-a-tiempo-ayuda',
                        'description' => 'Cumplir con tus pagos protege tu dinero y tu tranquilidad.',
                        'position' => 3,
                        'parts' => [
                            ['type' => 'title', 'content' => 'La puntualidad tambien es una habilidad financiera', 'position' => 1],
                            ['type' => 'text', 'content' => 'Cuando pagas a tiempo mantienes el control de tus gastos y evitas que una deuda pequena se convierta en un problema mayor.', 'position' => 2],
                            ['type' => 'image', 'content' => 'https://images.unsplash.com/photo-1556740749-887f6717d7e4?q=80&w=1200&auto=format&fit=crop', 'position' => 3],
                            ['type' => 'quiz', 'content' => 'Quiz corto: revisa si ya identificas lo importante antes de aceptar un prestamo.', 'position' => 4, 'meta' => [
                                'uuid' => 'quiz-prestamo-001',
                                'questions' => [
                                    ['uuid' => 'quiz-prestamo-001-q1', 'question' => 'Que conviene revisar antes de aceptar un prestamo?', 'option_a' => 'Solo el nombre del prestamo', 'option_b' => 'Monto, plazo y pagos', 'option_c' => 'Nada, solo aceptar rapido', 'option_d' => 'Solo la fecha', 'correct_option' => 'b'],
                                    ['uuid' => 'quiz-prestamo-001-q2', 'question' => 'Que es mejor que arrepentirte despues?', 'option_a' => 'Hacer preguntas antes', 'option_b' => 'Ignorar las condiciones', 'option_c' => 'Aceptar sin leer', 'option_d' => 'Pedir otro prestamo', 'correct_option' => 'a'],
                                ],
                            ]],
                            ['type' => 'exam', 'content' => 'Examen corto: responde segun lo aprendido sobre prestamos y pagos.', 'position' => 5, 'reward_rule' => [
                                'approved_points' => 10,
                                'excellent_points' => 25,
                            ], 'meta' => [
                                'uuid' => 'exam-prestamo-001',
                                'questions' => [
                                    ['uuid' => 'exam-prestamo-001-q1', 'question' => 'Que es un prestamo?', 'option_a' => 'Dinero que recibes hoy y devuelves despues', 'option_b' => 'Dinero gratis sin devolver', 'option_c' => 'Solo una meta de ahorro', 'option_d' => 'Una compra inmediata', 'correct_option' => 'a'],
                                    ['uuid' => 'exam-prestamo-001-q2', 'question' => 'Que ayuda a evitar sorpresas?', 'option_a' => 'No leer nada', 'option_b' => 'Revisar monto, cuotas y plazo', 'option_c' => 'Aceptar con prisa', 'option_d' => 'Pedir prestado mas dinero', 'correct_option' => 'b'],
                                    ['uuid' => 'exam-prestamo-001-q3', 'question' => 'Que pasa cuando pagas a tiempo?', 'option_a' => 'Pierdes el control de tus gastos', 'option_b' => 'La deuda suele crecer mas', 'option_c' => 'Mantienes mejor control y tranquilidad', 'option_d' => 'Ya no importa lo que debias', 'correct_option' => 'c'],
                                ],
                            ]],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($catalog as $entry) {
            $categoryId = DB::table('course_categories')->insertGetId($entry['category'] + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $courseId = DB::table('courses')->insertGetId($entry['course'] + [
                'course_category_id' => $categoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($entry['lessons'] as $lesson) {
                $parts = $lesson['parts'];
                unset($lesson['parts']);

                $lessonId = DB::table('lessons')->insertGetId($lesson + [
                    'course_id' => $courseId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($parts as $part) {
                    $lessonPartId = DB::table('lesson_parts')->insertGetId([
                        'lesson_id' => $lessonId,
                        'type' => $part['type'],
                        'content' => $part['content'],
                        'meta' => array_key_exists('meta', $part) && $part['meta'] !== null
                            ? json_encode($part['meta'], JSON_UNESCAPED_UNICODE)
                            : null,
                        'position' => $part['position'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($part['type'] === 'exam') {
                        $rewardRule = $part['reward_rule'] ?? [
                            'approved_points' => 10,
                            'excellent_points' => 25,
                        ];

                        DB::table('lesson_exam_reward_rules')->insert([
                            'lesson_part_id' => $lessonPartId,
                            'approved_points' => (int) ($rewardRule['approved_points'] ?? 0),
                            'excellent_points' => (int) ($rewardRule['excellent_points'] ?? 0),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }
}
