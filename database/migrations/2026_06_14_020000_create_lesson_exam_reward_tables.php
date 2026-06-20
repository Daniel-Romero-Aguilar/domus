<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_exam_reward_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_part_id')->constrained('lesson_parts')->cascadeOnDelete();
            $table->unsignedInteger('approved_points')->default(0);
            $table->unsignedInteger('excellent_points')->default(0);
            $table->timestamps();

            $table->unique('lesson_part_id');
        });

        Schema::create('lesson_exam_reward_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('lesson_part_id')->constrained('lesson_parts')->cascadeOnDelete();
            $table->foreignId('lesson_assessment_result_id')->nullable()->constrained('lesson_assessment_results')->nullOnDelete();
            $table->string('reward_tier', 20);
            $table->unsignedInteger('awarded_points')->default(0);
            $table->decimal('percentage_achieved', 5, 2)->default(0);
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['user_id', 'lesson_part_id', 'reward_tier'], 'lesson_exam_reward_user_part_tier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_exam_reward_grants');
        Schema::dropIfExists('lesson_exam_reward_rules');
    }
};
