<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('billing_account_id')->nullable()->unique();
        });

        DB::table('users')->select('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'billing_account_id' => (string) Str::uuid(),
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('billing_account_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['billing_account_id']);
            $table->dropColumn('billing_account_id');
        });
    }
};
