<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('child_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('idempotency_key', 120);
            $table->string('status', 20)->default('processing');
            $table->integer('parent_balance_before')->nullable();
            $table->integer('parent_balance_after')->nullable();
            $table->integer('child_balance_before')->nullable();
            $table->integer('child_balance_after')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['parent_user_id', 'idempotency_key']);
            $table->index(['parent_user_id', 'status']);
            $table->index(['child_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_transfers');
    }
};
