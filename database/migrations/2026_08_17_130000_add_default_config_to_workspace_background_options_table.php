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
            // A ready-to-use preset value in the exact same shape
            // `WorkspaceBackgroundService`'s per-type validators produce
            // (`{"color": "#hex"}`, `{"from": "#hex", "to": "#hex"}`,
            // `{"url": "/images/..."}`). Null for an option with no curated
            // preset — those still require the caller to submit a config,
            // exactly as before this column existed.
            $table->json('default_config')->nullable()->after('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_background_options', function (Blueprint $table) {
            $table->dropColumn('default_config');
        });
    }
};
