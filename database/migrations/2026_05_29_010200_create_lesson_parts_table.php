<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('type', 50); // title|string|video_youtube|image_url
            $table->text('content');
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Curso 1: Ahorro para ninos',
            'slug' => 'curso-1-ahorro',
            'description' => 'Conceptos basicos de ahorro en familia.',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lessonId = DB::table('lessons')->insertGetId([
            'course_id' => $courseId,
            'title' => 'Leccion 1: Que es ahorrar',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lesson_parts')->insert([
            ['lesson_id' => $lessonId, 'type' => 'title', 'content' => 'Que es ahorrar?', 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['lesson_id' => $lessonId, 'type' => 'string', 'content' => 'Ahorrar es guardar una parte de tu dinero para usarlo despues.', 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['lesson_id' => $lessonId, 'type' => 'video_youtube', 'content' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'position' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['lesson_id' => $lessonId, 'type' => 'image_url', 'content' => 'https://images.unsplash.com/photo-1579621970795-87facc2f976d?q=80&w=1200&auto=format&fit=crop', 'position' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_parts');
    }
};
