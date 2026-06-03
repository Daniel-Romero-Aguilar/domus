<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('child_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('frequency', 20);
            $table->date('start_at');
            $table->date('next_run_at')->nullable();
            $table->boolean('first_payment_immediate')->default(true);
            $table->string('status', 20)->default('pending');
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamps();

            $table->index(['parent_user_id', 'status']);
            $table->index(['child_user_id', 'status']);
            $table->index('next_run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowances');
    }
};
