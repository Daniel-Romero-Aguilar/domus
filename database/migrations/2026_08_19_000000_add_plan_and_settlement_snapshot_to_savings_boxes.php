<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_boxes', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_months')->nullable()->after('name');
            $table->unsignedInteger('duration_days')->nullable()->after('duration_months');
            $table->unsignedSmallInteger('cycle_days')->nullable()->after('duration_days');
            $table->unsignedInteger('cycle_count')->nullable()->after('cycle_days');
            $table->string('plan_version', 32)->nullable()->after('cycle_count');
            $table->unsignedBigInteger('max_per_cycle_cents')->nullable()->after('plan_version');
            $table->timestamp('starts_at')->nullable()->after('max_per_cycle_cents');
            $table->timestamp('cutoff_at')->nullable()->after('starts_at');
            $table->decimal('benefit_percent', 8, 2)->nullable()->after('annual_gain_percent');
            $table->string('benefit_mode', 32)->nullable()->after('benefit_percent');
            $table->timestamp('settled_at')->nullable()->after('status');

            $table->index(['status', 'cutoff_at'], 'savings_boxes_status_cutoff_index');
        });

        Schema::table('savings_box_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('settled_principal_cents')->default(0)->after('earned_interest_cents');
            $table->unsignedBigInteger('settled_benefit_cents')->default(0)->after('settled_principal_cents');
            $table->unsignedBigInteger('settled_total_cents')->default(0)->after('settled_benefit_cents');
            $table->timestamp('settled_at')->nullable()->after('interest_accrued_until_at');
        });

        Schema::table('savings_box_movements', function (Blueprint $table) {
            $table->unsignedInteger('cycle_number')->nullable()->after('movement_type');
            $table->index(['savings_box_account_id', 'cycle_number'], 'savings_movements_account_cycle_index');
        });

        DB::table('savings_boxes')
            ->orderBy('id')
            ->chunkById(100, function ($boxes): void {
                foreach ($boxes as $box) {
                    $startsAt = Carbon::parse($box->created_at ?? now());
                    $cutoffAt = Carbon::parse($box->delivery_date)->endOfDay();

                    DB::table('savings_boxes')
                        ->where('id', $box->id)
                        ->update([
                            'starts_at' => $startsAt,
                            'cutoff_at' => $cutoffAt,
                            'plan_version' => 'legacy_v1',
                            'benefit_percent' => $box->annual_gain_percent,
                            'benefit_mode' => 'legacy_accrual',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('savings_box_movements', function (Blueprint $table) {
            $table->dropIndex('savings_movements_account_cycle_index');
            $table->dropColumn('cycle_number');
        });

        Schema::table('savings_box_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'settled_principal_cents',
                'settled_benefit_cents',
                'settled_total_cents',
                'settled_at',
            ]);
        });

        Schema::table('savings_boxes', function (Blueprint $table) {
            $table->dropIndex('savings_boxes_status_cutoff_index');
            $table->dropColumn([
                'duration_months',
                'duration_days',
                'cycle_days',
                'cycle_count',
                'plan_version',
                'max_per_cycle_cents',
                'starts_at',
                'cutoff_at',
                'benefit_percent',
                'benefit_mode',
                'settled_at',
            ]);
        });
    }
};
