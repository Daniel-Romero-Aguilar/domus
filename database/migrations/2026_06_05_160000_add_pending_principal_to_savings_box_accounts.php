<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_box_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('savings_box_accounts', 'principal_pending_cents')) {
                $table->unsignedBigInteger('principal_pending_cents')->default(0)->after('principal_cents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('savings_box_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('savings_box_accounts', 'principal_pending_cents')) {
                $table->dropColumn('principal_pending_cents');
            }
        });
    }
};
