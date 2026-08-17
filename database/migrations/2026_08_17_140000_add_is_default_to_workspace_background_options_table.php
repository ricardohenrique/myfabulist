<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspace_background_options', function (Blueprint $table) {
            // Marks the one catalog row every new user starts on
            // (WorkspaceBackgroundService::assignDefaultTo(), fired by
            // AssignDefaultWorkspaceBackground on registration) and the one
            // "Use default" reverts to. Exactly one row should carry
            // `true` — an admin changes the platform default by moving the
            // flag in the database, never by editing application code.
            $table->boolean('is_default')->default(false)->after('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_background_options', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
