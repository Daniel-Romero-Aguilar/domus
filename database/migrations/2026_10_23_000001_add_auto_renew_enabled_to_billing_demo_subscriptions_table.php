<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_demo_subscriptions', function (Blueprint $table) {
            $table->boolean('auto_renew_enabled')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_demo_subscriptions', function (Blueprint $table) {
            $table->dropColumn('auto_renew_enabled');
        });
    }
};
