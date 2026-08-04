<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive and inert: adds a nullable `deleted_at` column to `tasks` and
     * `task_lists` so both models can adopt Laravel's SoftDeletes trait in a
     * later, separate step. Nothing reads or writes this column yet, so this
     * migration changes no runtime behaviour (Plan 4, Step 1).
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('task_lists', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('task_lists', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
