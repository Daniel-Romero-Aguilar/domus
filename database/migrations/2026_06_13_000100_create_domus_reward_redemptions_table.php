<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domus_reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domus_reward_id')->constrained('domus_rewards')->cascadeOnDelete();
            $table->foreignId('child_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('points_spent');
            $table->string('status', 40)->default('redeemed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domus_reward_redemptions');
    }
};
