<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domus_mission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domus_mission_id')->constrained('domus_missions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('awarded_points');
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['domus_mission_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domus_mission_user');
    }
};
