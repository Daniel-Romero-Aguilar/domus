<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('child_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('reason', 120)->nullable();
            $table->date('due_date');
            $table->unsignedInteger('installments_count')->default(1);
            $table->string('installment_frequency', 20)->default('monthly');
            $table->boolean('has_interest')->default(false);
            $table->string('interest_mode', 20)->default('percent');
            $table->decimal('annual_interest_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('fixed_interest_amount')->default(0);
            $table->string('status', 20)->default('offered');
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('installment_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
