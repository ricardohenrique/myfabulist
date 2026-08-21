<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CompletionSound;
use Illuminate\Database\Seeder;

class CompletionSoundSeeder extends Seeder
{
    public function run(): void
    {
        $sounds = [
            ['key' => 'sound_effect_01', 'label' => 'Sound 1', 'file_path' => 'sound-effect-01.mp3', 'sort_order' => 0, 'is_default' => true],
            ['key' => 'sound_effect_02', 'label' => 'Sound 2', 'file_path' => 'sound-effect-02.mp3', 'sort_order' => 1, 'is_default' => false],
            ['key' => 'sound_effect_03', 'label' => 'Sound 3', 'file_path' => 'sound-effect-03.mp3', 'sort_order' => 2, 'is_default' => false],
        ];

        foreach ($sounds as $sound) {
            CompletionSound::query()->updateOrCreate(['key' => $sound['key']], $sound);
        }
    }
}
