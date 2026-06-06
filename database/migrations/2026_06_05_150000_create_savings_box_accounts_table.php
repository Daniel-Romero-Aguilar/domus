<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_box_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_box_id')->constrained('savings_boxes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('principal_cents')->default(0);
            $table->unsignedBigInteger('principal_pending_cents')->default(0);
            $table->unsignedBigInteger('earned_interest_cents')->default(0);
            $table->unsignedBigInteger('interest_remainder_microcents')->default(0);
            $table->date('last_interest_accrued_on')->nullable();
            $table->timestamp('interest_accrued_until_at')->nullable();
            $table->timestamps();

            $table->unique(['savings_box_id', 'user_id']);
            $table->index('user_id');
        });

        $now = now();
        $boxes = DB::table('savings_boxes')->select('id', 'parent_user_id', 'audience')->get();

        foreach ($boxes as $box) {
            $memberIds = $box->audience === 'specific'
                ? DB::table('savings_box_members')->where('savings_box_id', $box->id)->pluck('user_id')
                : DB::table('family_members')->where('parent_user_id', $box->parent_user_id)->pluck('user_id');

            foreach ($memberIds->unique() as $memberId) {
                DB::table('savings_box_accounts')->insertOrIgnore([
                    'savings_box_id' => $box->id,
                    'user_id' => $memberId,
                    'principal_cents' => 0,
                    'principal_pending_cents' => 0,
                    'earned_interest_cents' => 0,
                    'interest_remainder_microcents' => 0,
                    'last_interest_accrued_on' => $now->toDateString(),
                    'interest_accrued_until_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_box_accounts');
    }
};
