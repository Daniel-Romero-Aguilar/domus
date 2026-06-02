<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('accepted_by_user_id')
                ->nullable()
                ->after('parent_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('completed_by_user_id')
                ->nullable()
                ->after('accepted_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by_user_id');
            $table->dropConstrainedForeignId('accepted_by_user_id');
        });
    }
};
