<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allowance_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_balance_before')->nullable()->change();
            $table->unsignedBigInteger('parent_balance_after')->nullable()->change();
            $table->unsignedBigInteger('child_balance_before')->nullable()->change();
            $table->unsignedBigInteger('child_balance_after')->nullable()->change();
        });

        Schema::table('family_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_balance_before')->nullable()->change();
            $table->unsignedBigInteger('parent_balance_after')->nullable()->change();
            $table->unsignedBigInteger('child_balance_before')->nullable()->change();
            $table->unsignedBigInteger('child_balance_after')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('allowance_payments', function (Blueprint $table) {
            $table->integer('parent_balance_before')->nullable()->change();
            $table->integer('parent_balance_after')->nullable()->change();
            $table->integer('child_balance_before')->nullable()->change();
            $table->integer('child_balance_after')->nullable()->change();
        });

        Schema::table('family_transfers', function (Blueprint $table) {
            $table->integer('parent_balance_before')->nullable()->change();
            $table->integer('parent_balance_after')->nullable()->change();
            $table->integer('child_balance_before')->nullable()->change();
            $table->integer('child_balance_after')->nullable()->change();
        });
    }
};
