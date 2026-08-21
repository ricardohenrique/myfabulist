<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null is an explicit "No sound" preference. New registrations
            // receive the catalog default through AssignDefaultCompletionSound.
            $table->foreignId('completion_sound_id')
                ->nullable()
                ->after('workspace_background_config')
                ->constrained('completion_sounds')
                ->nullOnDelete();
        });

        $defaultSoundId = DB::table('completion_sounds')
            ->where('is_default', true)
            ->value('id');

        if ($defaultSoundId !== null) {
            DB::table('users')->update(['completion_sound_id' => $defaultSoundId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completion_sound_id');
        });
    }
};
