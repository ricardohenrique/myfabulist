<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(WorkspaceBackgroundOptionSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->seedAcceptanceCriteriaWalkthrough($user);
    }

    /**
     * Seed the folder/list/task walkthrough described in the acceptance criteria:
     * an Inbox, a "Work" folder containing a "Website launch" list with five
     * tasks (one completed, one starred), and an ungrouped "Groceries" list.
     */
    private function seedAcceptanceCriteriaWalkthrough(User $user): void
    {
        TaskList::factory()->inbox()->create([
            'user_id' => $user->id,
        ]);

        $workFolder = $user->folders()->create([
            'name' => 'Work',
            'position' => 0,
        ]);

        $websiteLaunch = TaskList::factory()
            ->inFolder($workFolder, 0)
            ->create([
                'name' => 'Website launch',
            ]);

        Task::factory()
            ->forTaskList($websiteLaunch)
            ->count(3)
            ->sequence(
                ['title' => 'Draft the homepage copy', 'position' => 0],
                ['title' => 'Choose a hosting provider', 'position' => 1],
                ['title' => 'Configure the domain DNS', 'position' => 2],
            )
            ->create();

        Task::factory()
            ->forTaskList($websiteLaunch)
            ->completed()
            ->create([
                'title' => 'Set up the project repository',
                'position' => 3,
            ]);

        Task::factory()
            ->forTaskList($websiteLaunch)
            ->starred()
            ->create([
                'title' => 'Launch day announcement',
                'position' => 4,
            ]);

        TaskList::factory()
            ->atPosition(1)
            ->create([
                'user_id' => $user->id,
                'name' => 'Groceries',
            ]);
    }
}
