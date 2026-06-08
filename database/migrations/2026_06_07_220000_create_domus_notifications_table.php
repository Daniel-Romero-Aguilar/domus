<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domus_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('section', 80);
            $table->string('category', 80);
            $table->text('text');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'section', 'created_at']);
            $table->index(['user_id', 'category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domus_notifications');
    }
};
