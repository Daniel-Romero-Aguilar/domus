<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_movements', function (Blueprint $table) {
            $table->string('movement_type')->default('credit')->after('amount_added');
            $table->string('note')->nullable()->after('movement_type');
        });
    }

    public function down(): void
    {
        Schema::table('balance_movements', function (Blueprint $table) {
            $table->dropColumn(['movement_type', 'note']);
        });
    }
};
