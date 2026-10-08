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
            $table->decimal('amount', 14, 2);
            $table->string('reason', 120)->nullable();
            $table->date('due_date');
            $table->unsignedInteger('installments_count')->default(1);
            $table->string('installment_frequency', 20)->default('monthly');
            $table->boolean('has_interest')->default(false);
            $table->string('interest_mode', 20)->default('percent');
            $table->decimal('annual_interest_rate', 5, 2)->default(0);
            $table->decimal('fixed_interest_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('offered');
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('installment_amount', 14, 2);

            // Immutable record of the three choices made by a child.
            $table->decimal('requested_amount', 14, 2)->nullable();
            $table->string('requested_reason', 120)->nullable();
            $table->unsignedSmallInteger('requested_months')->nullable();

            // The server-derived terms as they stood when the child submitted.
            $table->date('initial_due_date')->nullable();
            $table->unsignedSmallInteger('initial_installments_count')->nullable();
            $table->string('initial_installment_frequency', 20)->nullable();
            $table->boolean('initial_has_interest')->nullable();
            $table->string('initial_interest_mode', 20)->nullable();
            $table->decimal('initial_annual_interest_rate', 5, 2)->nullable();
            $table->decimal('initial_fixed_interest_amount', 14, 2)->nullable();
            $table->decimal('initial_total_amount', 14, 2)->nullable();
            $table->decimal('initial_installment_amount', 14, 2)->nullable();

            $table->date('proposed_due_date')->nullable();
            $table->timestamp('parent_reviewed_at')->nullable();
            $table->unsignedBigInteger('reserved_amount_cents')->nullable();
            $table->timestamp('funds_reserved_at')->nullable();
            $table->timestamp('funds_released_at')->nullable();
            $table->timestamp('funds_disbursed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
