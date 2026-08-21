<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('completion_sounds', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('file_path');
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('completion_sounds')->insert([
            [
                'key' => 'sound_effect_01',
                'label' => 'Sound 1',
                'file_path' => 'sound-effect-01.mp3',
                'enabled' => true,
                'is_default' => true,
                'sort_order' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'sound_effect_02',
                'label' => 'Sound 2',
                'file_path' => 'sound-effect-02.mp3',
                'enabled' => true,
                'is_default' => false,
                'sort_order' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'sound_effect_03',
                'label' => 'Sound 3',
                'file_path' => 'sound-effect-03.mp3',
                'enabled' => true,
                'is_default' => false,
                'sort_order' => 2,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('completion_sounds');
    }
};
