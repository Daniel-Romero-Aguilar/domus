<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('savings_boxes', 'child_user_id')) {
                $table->dropForeign(['child_user_id']);
                $table->dropIndex(['child_user_id']);
                $table->dropColumn('child_user_id');
            }

            if (! Schema::hasColumn('savings_boxes', 'audience')) {
                $table->string('audience', 24)->default('all')->after('annual_gain_percent');
                $table->index(['parent_user_id', 'audience']);
            }
        });

        if (! Schema::hasTable('savings_box_members')) {
            Schema::create('savings_box_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('savings_box_id')->constrained('savings_boxes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['savings_box_id', 'user_id']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_box_members');

        Schema::table('savings_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('savings_boxes', 'audience')) {
                $table->dropIndex(['parent_user_id', 'audience']);
                $table->dropColumn('audience');
            }

            if (! Schema::hasColumn('savings_boxes', 'child_user_id')) {
                $table->foreignId('child_user_id')->nullable()->after('parent_user_id')->constrained('users')->nullOnDelete();
                $table->index('child_user_id');
            }
        });
    }
};
