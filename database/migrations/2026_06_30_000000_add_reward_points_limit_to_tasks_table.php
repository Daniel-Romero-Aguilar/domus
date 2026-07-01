<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $constraintName = 'tasks_reward_points_max_100';

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE tasks ADD CONSTRAINT {$this->constraintName} CHECK (reward_points <= 100)");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            $version = strtolower((string) (DB::selectOne('select version() as version')->version ?? ''));
            $dropSql = str_contains($version, 'mariadb')
                ? "ALTER TABLE tasks DROP CONSTRAINT {$this->constraintName}"
                : "ALTER TABLE tasks DROP CHECK {$this->constraintName}";

            DB::statement($dropSql);

            return;
        }

        DB::statement("ALTER TABLE tasks DROP CONSTRAINT {$this->constraintName}");
    }
};
