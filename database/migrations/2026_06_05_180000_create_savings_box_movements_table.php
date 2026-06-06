<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_box_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_box_account_id')->constrained('savings_box_accounts')->cascadeOnDelete();
            $table->foreignId('savings_box_id')->constrained('savings_boxes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('movement_type', 32);
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->unsignedBigInteger('principal_before_cents')->default(0);
            $table->unsignedBigInteger('principal_after_cents')->default(0);
            $table->unsignedBigInteger('earned_interest_before_cents')->default(0);
            $table->unsignedBigInteger('earned_interest_after_cents')->default(0);
            $table->timestamp('occurred_at');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['savings_box_id', 'movement_type']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_box_movements');
    }
};
