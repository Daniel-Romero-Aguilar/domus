<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL can survive an interrupted migration; resume without dropping data.
        $columns = Schema::getColumnListing('billing_demo_subscriptions');
        Schema::table('billing_demo_subscriptions', function (Blueprint $table) use ($columns) {
            if (! in_array('first_verified_at', $columns, true)) {
                $table->dateTime('first_verified_at')->nullable();
            }
            if (! in_array('superseded_at', $columns, true)) {
                $table->dateTime('superseded_at')->nullable();
            }
            if (! in_array('linked_purchase_token_hash', $columns, true)) {
                $table->char('linked_purchase_token_hash', 64)->nullable()->index();
            }
            if (! in_array('sync_attempts', $columns, true)) {
                $table->unsignedSmallInteger('sync_attempts')->default(0);
            }
            if (! in_array('sync_started_at', $columns, true)) {
                $table->dateTime('sync_started_at')->nullable();
            }
            if (! in_array('next_sync_at', $columns, true)) {
                $table->dateTime('next_sync_at')->nullable()->index();
            }
            if (! in_array('sync_stopped_at', $columns, true)) {
                $table->dateTime('sync_stopped_at')->nullable();
            }
            if (! in_array('last_sync_error', $columns, true)) {
                $table->string('last_sync_error', 60)->nullable();
            }
        });

        DB::table('billing_demo_subscriptions')
            ->whereIn('google_state', ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD', 'SUBSCRIPTION_STATE_CANCELED'])
            ->whereNotNull('acknowledged')
            ->whereNull('first_verified_at')
            ->update(['first_verified_at' => DB::raw('created_at')]);

        // Bootstrap recent purchases only; historical failures must not become recurring work.
        DB::table('billing_demo_subscriptions')
            ->whereIn('google_state', ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD'])
            ->where('expires_at', '>=', now('UTC')->subDays(config('billing_demo.retry_window_days')))
            ->where('acknowledged', true)
            ->whereNull('next_sync_at')->whereNull('sync_stopped_at')
            ->update(['next_sync_at' => DB::raw('expires_at')]);

        DB::table('billing_demo_subscriptions')
            ->whereNotNull('first_verified_at')->where('acknowledged', false)
            ->where('first_verified_at', '>=', now('UTC')->subDays(3))
            ->whereNull('next_sync_at')->whereNull('sync_stopped_at')
            ->update(['next_sync_at' => now('UTC')]);
    }

    public function down(): void
    {
        Schema::table('billing_demo_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['next_sync_at']);
            $table->dropIndex(['linked_purchase_token_hash']);
            $table->dropColumn(['first_verified_at', 'superseded_at', 'linked_purchase_token_hash', 'sync_attempts', 'sync_started_at', 'next_sync_at', 'sync_stopped_at', 'last_sync_error']);
        });
    }
};
