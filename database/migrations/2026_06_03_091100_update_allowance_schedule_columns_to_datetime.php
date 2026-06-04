<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allowances', function (Blueprint $table) {
            $table->dateTime('next_run_at')->nullable()->change();
        });

        Schema::table('allowance_payments', function (Blueprint $table) {
            $table->dateTime('scheduled_for')->change();
        });
    }

    public function down(): void
    {
        Schema::table('allowances', function (Blueprint $table) {
            $table->date('next_run_at')->nullable()->change();
        });

        Schema::table('allowance_payments', function (Blueprint $table) {
            $table->date('scheduled_for')->change();
        });
    }
};
