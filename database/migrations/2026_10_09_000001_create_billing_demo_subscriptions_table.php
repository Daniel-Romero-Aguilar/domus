<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_demo_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('purchase_token_hash', 64)->unique();
            $table->text('purchase_token');
            $table->string('product_id', 40);
            $table->string('google_state', 80);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('verified_at');
            $table->timestamps();
            $table->index(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_demo_subscriptions');
    }
};
