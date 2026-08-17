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
        Schema::create('workspace_background_options', function (Blueprint $table) {
            $table->id();
            // Stable, application-referenced identifier ('flat_color',
            // 'image', 'gradient') — application code resolves rows by this,
            // never by the fragile auto-increment id.
            $table->string('key')->unique();
            // Kept distinct from `key` so a future catalog can offer several
            // curated rows that share one rendering `type` (e.g. more than
            // one gradient) without changing the service's per-type
            // validator lookup, which is keyed by `type`.
            $table->string('type');
            $table->string('label');
            // Hides the row from *new* selections only — a user already on
            // a disabled option keeps it (WorkspaceBackgroundService).
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_background_options');
    }
};
