<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_boxes', function (Blueprint $table) {
            if (! Schema::hasColumn('savings_boxes', 'allow_early_withdrawal')) {
                $table->boolean('allow_early_withdrawal')->default(false)->after('annual_gain_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('savings_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('savings_boxes', 'allow_early_withdrawal')) {
                $table->dropColumn('allow_early_withdrawal');
            }
        });
    }
};
