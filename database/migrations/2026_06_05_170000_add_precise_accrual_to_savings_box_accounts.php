<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_box_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('savings_box_accounts', 'interest_remainder_microcents')) {
                $table->unsignedBigInteger('interest_remainder_microcents')->default(0)->after('earned_interest_cents');
            }

            if (! Schema::hasColumn('savings_box_accounts', 'interest_accrued_until_at')) {
                $table->timestamp('interest_accrued_until_at')->nullable()->after('last_interest_accrued_on');
            }
        });

        DB::table('savings_box_accounts')
            ->whereNull('interest_accrued_until_at')
            ->update(['interest_accrued_until_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('savings_box_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('savings_box_accounts', 'interest_accrued_until_at')) {
                $table->dropColumn('interest_accrued_until_at');
            }

            if (Schema::hasColumn('savings_box_accounts', 'interest_remainder_microcents')) {
                $table->dropColumn('interest_remainder_microcents');
            }
        });
    }
};
