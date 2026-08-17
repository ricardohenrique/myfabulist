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
 * CI) never duplicates rows, and an operator's `enabled`/`is_default`
 * toggles survive a re-seed of everything else.
 *
 * `gradient_twilight` is the platform default (`is_default`) — matches the
 * brand identity, so it's what every new user starts on
 * (WorkspaceBackgroundService::assignDefaultTo(), fired by
 * AssignDefaultWorkspaceBackground on registration) and what "Use default"
 * in the picker reverts to. Exactly one row should ever carry
 * `is_default => true`.
 */
class WorkspaceBackgroundOptionSeeder extends Seeder
{
    /**
     * Seed the workspace background option catalog.
     */
    public function run(): void
    {
        $options = [
            ['key' => 'flat_color_amethyst', 'type' => 'flat_color', 'label' => 'Amethyst', 'sort_order' => 0, 'is_default' => false, 'default_config' => ['color' => '#8b6fd6', 'workspace_header' => '#6c53ad', 'task_composer' => '#7a5fc0']],
            ['key' => 'flat_color_terracotta', 'type' => 'flat_color', 'label' => 'Terracotta', 'sort_order' => 1, 'is_default' => false, 'default_config' => ['color' => '#c96a4e', 'workspace_header' => '#a8523f', 'task_composer' => '#b85f48']],
            ['key' => 'gradient_meadow', 'type' => 'gradient', 'label' => 'Meadow', 'sort_order' => 2, 'is_default' => false, 'default_config' => ['from' => '#78b691', 'to' => '#e8dca1', 'workspace_header' => '#47976f', 'task_composer' => '#408f66']],
            ['key' => 'gradient_twilight', 'type' => 'gradient', 'label' => 'Twilight', 'sort_order' => 3, 'is_default' => true, 'default_config' => ['from' => '#4a3f7a', 'to' => '#d98fb3', 'workspace_header' => '#382f5c', 'task_composer' => '#4a3f7a']],
            ['key' => 'image_aurora_waves', 'type' => 'image', 'label' => 'Aurora Waves', 'sort_order' => 4, 'is_default' => false, 'default_config' => ['url' => '/images/workspace-backgrounds/aurora-waves.svg', 'workspace_header' => '#123f3a', 'task_composer' => '#0f332f']],
            ['key' => 'image_dune_drift', 'type' => 'image', 'label' => 'Dune Drift', 'sort_order' => 5, 'is_default' => false, 'default_config' => ['url' => '/images/workspace-backgrounds/dune-drift.svg', 'workspace_header' => '#4a2430', 'task_composer' => '#3a1c26']],
        ];

        foreach ($options as $option) {
            WorkspaceBackgroundOption::query()->updateOrCreate(
                ['key' => $option['key']],
                $option,
            );
        }
    }
}
