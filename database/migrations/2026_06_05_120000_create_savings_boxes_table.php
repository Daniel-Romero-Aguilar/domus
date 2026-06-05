<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('delivery_date');
            $table->decimal('annual_gain_percent', 8, 2)->default(0);
            $table->boolean('allow_early_withdrawal')->default(false);
            $table->string('audience', 24)->default('all');
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['parent_user_id', 'status']);
            $table->index(['parent_user_id', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_boxes');
    }
};
