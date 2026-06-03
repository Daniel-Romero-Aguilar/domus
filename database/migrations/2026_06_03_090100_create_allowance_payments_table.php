<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allowance_id')->constrained('allowances')->cascadeOnDelete();
            $table->date('scheduled_for');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('amount_cents');
            $table->integer('parent_balance_before')->nullable();
            $table->integer('parent_balance_after')->nullable();
            $table->integer('child_balance_before')->nullable();
            $table->integer('child_balance_after')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['allowance_id', 'scheduled_for']);
            $table->index(['allowance_id', 'status']);
            $table->index(['scheduled_for', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowance_payments');
    }
};
