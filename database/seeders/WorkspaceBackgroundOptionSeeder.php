<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WorkspaceBackgroundOption;
use Illuminate\Database\Seeder;

/**
 * The curated workspace-background catalog (Plan: "Workspace Background
 * Personalization" + its "Expanded Curated Catalog" follow-up): two
 * presets per type (flat_color, gradient, image) so the picker offers a
 * real gallery instead of one freeform slot per type. `updateOrCreate()`
 * keyed on `key` so re-running the seeder (e.g. `migrate:fresh --seed` in
 * CI) never duplicates rows, and an operator's `enabled` toggle survives a
 * re-seed of everything else. `gradient_meadow`'s colors are deliberately
 * identical to `DEFAULT_GRADIENT_FROM`/`DEFAULT_GRADIENT_TO` in
 * `resources/js/components/settings/workspace-background-section.tsx` —
 * this is "today's gradient", kept as one of the two gradient presets.
 */
class WorkspaceBackgroundOptionSeeder extends Seeder
{
    /**
     * Seed the workspace background option catalog.
     */
    public function run(): void
    {
        $options = [
            ['key' => 'flat_color_amethyst', 'type' => 'flat_color', 'label' => 'Amethyst', 'sort_order' => 0, 'default_config' => ['color' => '#8b6fd6']],
            ['key' => 'flat_color_terracotta', 'type' => 'flat_color', 'label' => 'Terracotta', 'sort_order' => 1, 'default_config' => ['color' => '#c96a4e']],
            ['key' => 'gradient_meadow', 'type' => 'gradient', 'label' => 'Meadow', 'sort_order' => 2, 'default_config' => ['from' => '#78b691', 'to' => '#e8dca1']],
            ['key' => 'gradient_twilight', 'type' => 'gradient', 'label' => 'Twilight', 'sort_order' => 3, 'default_config' => ['from' => '#4a3f7a', 'to' => '#d98fb3']],
            ['key' => 'image_aurora_waves', 'type' => 'image', 'label' => 'Aurora Waves', 'sort_order' => 4, 'default_config' => ['url' => '/images/workspace-backgrounds/aurora-waves.svg']],
            ['key' => 'image_dune_drift', 'type' => 'image', 'label' => 'Dune Drift', 'sort_order' => 5, 'default_config' => ['url' => '/images/workspace-backgrounds/dune-drift.svg']],
        ];

        foreach ($options as $option) {
            WorkspaceBackgroundOption::query()->updateOrCreate(
                ['key' => $option['key']],
                $option,
            );
        }
    }
}
