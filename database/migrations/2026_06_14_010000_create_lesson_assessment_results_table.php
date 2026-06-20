<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('lesson_part_id')->constrained('lesson_parts')->cascadeOnDelete();
            $table->uuid('assessment_uuid');
            $table->string('assessment_type', 20);
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->json('submitted_answers')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'lesson_part_id', 'submitted_at'], 'lesson_assessment_user_part_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_assessment_results');
    }
};
