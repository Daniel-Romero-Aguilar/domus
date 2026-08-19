<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allowances', function (Blueprint $table) {
            $table->unsignedBigInteger('next_payment_amount_cents')->nullable()->after('amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('allowances', function (Blueprint $table) {
            $table->dropColumn('next_payment_amount_cents');
        });
    }
};
