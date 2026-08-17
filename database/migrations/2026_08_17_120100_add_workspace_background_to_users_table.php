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
        Schema::table('users', function (Blueprint $table) {
            // Both nullable, and both default to null (UserFactory sets
            // neither) — "no preference set" is the default for every
            // existing and new user, so the workspace renders exactly as it
            // does today until a user explicitly picks a background.
            $table->foreignId('workspace_background_option_id')
                ->nullable()
                ->after('profile_photo_path')
                ->constrained('workspace_background_options')
                ->nullOnDelete();
            $table->json('workspace_background_config')->nullable()->after('workspace_background_option_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_background_option_id');
            $table->dropColumn('workspace_background_config');
        });
    }
};
