<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_use_case', 32)->nullable()->after('workspace_background_config');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_use_case');
        });

        // The prompt belongs only to accounts created after this feature is
        // deployed. Existing accounts have already completed onboarding.
        DB::table('users')->update(['onboarding_completed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_use_case', 'onboarding_completed_at']);
        });
    }
};
