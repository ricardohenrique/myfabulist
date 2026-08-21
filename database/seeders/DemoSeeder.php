<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Exceptions\DomainException;
use App\Models\Folder;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\CompletionSoundService;
use App\Services\ListSharingService;
use App\Services\WorkspaceBackgroundService;
use Carbon\CarbonInterface;
use Database\Factories\TaskFactory;
use Illuminate\Console\Command;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Builds a realistic, multi-user demo dataset (Folders -> Lists -> Tasks ->
 * Subtasks) used only by `php artisan db:fresh-seed`. `DatabaseSeeder` — the minimal
 * acceptance-criteria fixture behind `php artisan db:seed` — is untouched
 * and unrelated (D3/A5).
 *
 * Every relational and positional invariant the repositories assume is
 * satisfied here: `task.user_id === task.taskList.user_id`,
 * `taskList.user_id === folder.user_id` when foldered, exactly one
 * `is_default` list per user, and contiguous `position` values within each
 * ordering bucket (D8). Composition happens through explicit per-user loops
 * over the existing factory states (`inbox()`, `inFolder()`, `forTaskList()`)
 * rather than nested `has()` chains, because those chains evaluate counts
 * once for the whole batch and cannot express "N random per user" (D4).
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Mirrors the inherited (untyped) `Seeder::$command`, but with an
     * accurate, PHPStan-visible nullable type. The base class's own
     * `@var \Illuminate\Console\Command` PHPDoc is not actually nullable,
     * even though the property is only ever set when Artisan resolves the
     * seeder (`migrate:fresh --seeder=`) — direct instantiation, as
     * `DemoSeederTest` does, never calls `setCommand()`.
     */
    private ?Command $seederCommand = null;

    private const DEFAULT_USER_COUNT = 20;

    private const FOLDERS_MIN = 3;

    private const FOLDERS_MAX = 5;

    private const FOLDERED_LISTS_MIN = 5;

    private const FOLDERED_LISTS_MAX = 10;

    private const STANDALONE_LISTS_MIN = 2;

    private const STANDALONE_LISTS_MAX = 4;

    private const TASKS_MIN = 10;

    private const TASKS_MAX = 20;

    /** Chance a task is completed (D6). */
    private const COMPLETED_CHANCE = 0.30;

    /** Chance a non-completed task is starred. */
    private const STARRED_CHANCE = 0.15;

    /** Chance a non-completed task carries a due date. */
    private const DUE_DATE_CHANCE = 0.35;

    /** Chance a non-completed task carries a note. */
    private const NOTE_CHANCE = 0.25;

    /** Every task gets between 0 and 5 subtasks (inclusive), independent of its own state. */
    private const SUBTASKS_MIN = 0;

    private const SUBTASKS_MAX = 5;

    /** Chance an individual subtask of a *non-completed* task is itself completed. */
    private const SUBTASK_COMPLETED_CHANCE = 0.3;

    /** Task `created_at`/`updated_at` are spread over the last N days so ordering by them is meaningful. */
    private const TIMESTAMP_SPREAD_DAYS = 60;

    /** Completed tasks completed within the last N days. */
    private const COMPLETED_WITHIN_DAYS = 14;

    /** D7 showcase: how far in the past the forced overdue task's due date lands. */
    private const SHOWCASE_OVERDUE_MAX_DAYS_AGO = 14;

    /** F23: how many lists gain a real, accepted cross-user collaborator. */
    private const SHARED_LISTS_MAX = 3;

    public function setCommand(Command $command): static
    {
        $this->seederCommand = $command;

        return parent::setCommand($command);
    }

    /**
     * @param  int  $userCount  Defaulted (D13) so `migrate:fresh --seeder=` gets 20 while
     *                          tests can call `run(3)` for a fast, focused run.
     */
    public function run(int $userCount = self::DEFAULT_USER_COUNT): void
    {
        // `db:fresh-seed` (SeedDemoDatabaseCommand) runs `migrate:fresh
        // --seeder=DemoSeeder`, which never touches `DatabaseSeeder` — so
        // without this call, the workspace-background catalog would never
        // exist on a freshly reset demo database.
        $this->call(WorkspaceBackgroundOptionSeeder::class);
        $this->call(CompletionSoundSeeder::class);

        $users = $this->createUsers($userCount);
        $this->assignDefaultBackgrounds($users);
        $this->assignDefaultCompletionSounds($users);

        $totals = ['folders' => 0, 'lists' => 0, 'tasks' => 0, 'subtasks' => 0];

        foreach ($users as $user) {
            // One transaction per user (D10): collapses ~190 inserts per
            // user into a handful of commits and keeps a mid-run failure
            // from leaving a partially-seeded user behind.
            $counts = DB::transaction(fn (): array => $this->seedUser($user));

            $totals['folders'] += $counts['folders'];
            $totals['lists'] += $counts['lists'];
            $totals['tasks'] += $counts['tasks'];
            $totals['subtasks'] += $counts['subtasks'];
        }

        // Sharing is inherently cross-user, so it cannot live inside
        // seedUser()'s per-user transaction above — every user and their
        // lists must already exist first (F23, Plan 1 Step 10).
        $this->shareLists($users);

        $this->reportSummary($users->count(), $totals);
    }

    /**
     * The `demoN@example.com` sequence continues from however many demo
     * users already exist rather than always restarting at 1, so calling
     * `run()` against a non-empty database is additive instead of colliding
     * on the unique email index — the reset guarantee belongs to
     * `db:fresh-seed`, not to the seeder itself.
     *
     * @return Collection<int, User>
     */
    private function createUsers(int $userCount): Collection
    {
        $startIndex = User::query()->where('email', 'like', 'demo%@example.com')->count();

        return User::factory()
            ->count($userCount)
            ->sequence(fn (Sequence $sequence): array => [
                'name' => 'Demo User '.($startIndex + $sequence->index + 1),
                'email' => sprintf('demo%d@example.com', $startIndex + $sequence->index + 1),
            ])
            ->create();
    }

    /**
     * Demo users are created via the factory above, which never fires
     * `Registered` — so without this, `AssignDefaultWorkspaceBackground`
     * never runs and every demo account would render today's hard-coded
     * CSS fallback instead of the platform default (Twilight), unlike a
     * real registered user.
     *
     * @param  Collection<int, User>  $users
     */
    private function assignDefaultBackgrounds(Collection $users): void
    {
        $backgrounds = app(WorkspaceBackgroundService::class);

        foreach ($users as $user) {
            $backgrounds->assignDefaultTo($user);
        }
    }

    /** @param Collection<int, User> $users */
    private function assignDefaultCompletionSounds(Collection $users): void
    {
        $sounds = app(CompletionSoundService::class);

        foreach ($users as $user) {
            $sounds->assignDefaultTo($user);
        }
    }

    /**
     * @return array{folders: int, lists: int, tasks: int, subtasks: int}
     */
    private function seedUser(User $user): array
    {
        $inbox = $this->createInbox($user);
        $folders = $this->createFolders($user);

        [$folderedNames, $standaloneNames] = $this->pickListNames();

        $folderedLists = $this->createFolderedLists($folders, $folderedNames);
        $standaloneLists = $this->createStandaloneLists($user, $standaloneNames);

        $inboxTasks = $this->seedTasksForList($inbox);
        $taskCount = count($inboxTasks);
        $subtaskCount = $this->subtaskCountFor($inboxTasks);

        foreach ([...$folderedLists, ...$standaloneLists] as $list) {
            $listTasks = $this->seedTasksForList($list);
            $taskCount += count($listTasks);
            $subtaskCount += $this->subtaskCountFor($listTasks);
        }

        // D7: guarantee every user's Inbox is fully populated, regardless
        // of how the random pass above happened to land.
        $this->applyShowcase($inboxTasks, $user);

        return [
            'folders' => count($folders),
            'lists' => 1 + count($folderedLists) + count($standaloneLists),
            'tasks' => $taskCount,
            'subtasks' => $subtaskCount,
        ];
    }

    /**
     * @param  array<int, Task>  $tasks
     */
    private function subtaskCountFor(array $tasks): int
    {
        return array_sum(array_map(
            fn (Task $task): int => $task->subtasks()->count(),
            $tasks,
        ));
    }

    private function createInbox(User $user): TaskList
    {
        return TaskList::factory()->inbox()->create([
            'user_id' => $user->id,
        ]);
    }

    /**
     * @return array<int, Folder>
     */
    private function createFolders(User $user): array
    {
        $names = DemoContent::folderNames(random_int(self::FOLDERS_MIN, self::FOLDERS_MAX));

        $folders = [];

        foreach (array_values($names) as $position => $name) {
            $folders[] = Folder::factory()->create([
                'user_id' => $user->id,
                'name' => $name,
                'position' => $position,
            ]);
        }

        return $folders;
    }

    /**
     * Foldered list count, standalone list count, and the (user-unique)
     * names for both, drawn from a single pick so no name repeats within
     * a user (D5).
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function pickListNames(): array
    {
        $folderedCount = random_int(self::FOLDERED_LISTS_MIN, self::FOLDERED_LISTS_MAX);
        $standaloneCount = random_int(self::STANDALONE_LISTS_MIN, self::STANDALONE_LISTS_MAX);

        $names = DemoContent::listNames($folderedCount + $standaloneCount);

        return [
            array_slice($names, 0, $folderedCount),
            array_slice($names, $folderedCount),
        ];
    }

    /**
     * Distributes lists across folders round-robin first (guaranteeing no
     * empty folder, since the minimum list count always meets the maximum
     * folder count) then randomly for the remainder (D9), with positions
     * assigned contiguously per folder as each list lands there (D8).
     *
     * @param  array<int, Folder>  $folders
     * @param  array<int, string>  $names
     * @return array<int, TaskList>
     */
    private function createFolderedLists(array $folders, array $names): array
    {
        $folderCount = count($folders);
        $positionInFolder = array_fill(0, $folderCount, 0);

        $lists = [];

        foreach (array_values($names) as $index => $name) {
            $folderIndex = $index < $folderCount ? $index : random_int(0, $folderCount - 1);
            $folder = $folders[$folderIndex];

            $lists[] = TaskList::factory()
                ->inFolder($folder, $positionInFolder[$folderIndex]++)
                ->create(['name' => $name]);
        }

        return $lists;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, TaskList>
     */
    private function createStandaloneLists(User $user, array $names): array
    {
        $lists = [];

        foreach (array_values($names) as $index => $name) {
            // The Inbox owns position 0 in the ungrouped bucket (D8/A2).
            $lists[] = TaskList::factory()->atPosition($index + 1)->create([
                'user_id' => $user->id,
                'name' => $name,
            ]);
        }

        return $lists;
    }

    /**
     * @return array<int, Task>
     */
    private function seedTasksForList(TaskList $taskList): array
    {
        $titles = DemoContent::taskTitles(random_int(self::TASKS_MIN, self::TASKS_MAX));

        $tasks = [];

        foreach (array_values($titles) as $position => $title) {
            $tasks[] = $this->createTask($taskList, $title, $position);
        }

        return $tasks;
    }

    private function createTask(TaskList $taskList, string $title, int $position): Task
    {
        $createdAt = $this->randomPastMoment(self::TIMESTAMP_SPREAD_DAYS);

        $factory = Task::factory()
            ->forTaskList($taskList)
            ->state([
                'title' => $title,
                'position' => $position,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

        if ($this->rollChance(self::COMPLETED_CHANCE)) {
            $task = $factory
                ->completedAt($this->randomPastMoment(self::COMPLETED_WITHIN_DAYS))
                ->create();
        } else {
            if ($this->rollChance(self::STARRED_CHANCE)) {
                $factory = $factory->starred();
            }

            if ($this->rollChance(self::DUE_DATE_CHANCE)) {
                $factory = $this->withRandomDueDate($factory);
            }

            if ($this->rollChance(self::NOTE_CHANCE)) {
                $factory = $factory->withNote(DemoContent::note());
            }

            $task = $factory->create();
        }

        $this->createSubtasksForTask($task);

        return $task;
    }

    /**
     * Every task gets 0–5 subtasks (D-subtasks), regardless of the task's
     * own completion state. A completed task's subtasks are all completed
     * too — an incomplete checklist under a finished task would read as a
     * seeding bug, not realistic data. An active task's subtasks complete
     * independently at SUBTASK_COMPLETED_CHANCE, so most lists show a mix
     * of ticked and open items.
     */
    private function createSubtasksForTask(Task $task): void
    {
        $count = random_int(self::SUBTASKS_MIN, self::SUBTASKS_MAX);

        if ($count === 0) {
            return;
        }

        foreach (DemoContent::subtaskTitles($count) as $title) {
            $isCompleted = $task->is_completed || $this->rollChance(self::SUBTASK_COMPLETED_CHANCE);

            Subtask::factory()
                ->forTask($task)
                ->state(['title' => $title, 'is_completed' => $isCompleted])
                ->create();
        }
    }

    private function withRandomDueDate(TaskFactory $factory): TaskFactory
    {
        return match (random_int(1, 3)) {
            1 => $factory->overdue(),
            2 => $factory->dueToday(),
            default => $factory->dueUpcoming(),
        };
    }

    /**
     * D7: force one starred, one overdue and one due-today task into the
     * given (already-created) Inbox tasks, so no demo account can land on
     * an empty Starred page or a due-date badge that never renders.
     * `seedTasksForList()` always produces at least TASKS_MIN (10) tasks,
     * so three are always available to claim.
     *
     * `$user` is taken explicitly rather than derived from `$starred->user_id`
     * (they are identical today, since every showcase task is created for
     * its own user) — explicit over implicit, per this project's stated
     * convention, and it is the star's owner that Plan 1, Step 3 actually
     * needs, not the task's creator, now that the two can diverge once
     * sharing ships.
     *
     * @param  array<int, Task>  $inboxTasks
     */
    private function applyShowcase(array $inboxTasks, User $user): void
    {
        [$starred, $overdue, $dueToday] = array_slice($inboxTasks, 0, 3);

        // star() is the single idempotent primitive for "this user has
        // starred this task" (Plan 1, Step 3) — calling it here rather than
        // re-checking task_stars by hand means the random per-task pass
        // above (which may have already starred this exact task for this
        // exact user via STARRED_CHANCE) can never diverge from the
        // repository's own definition of idempotent.
        app(TaskRepositoryInterface::class)->star($starred, $user);

        $overdue->forceFill([
            'is_completed' => false,
            'completed_at' => null,
            'due_date' => today()->subDays(random_int(1, self::SHOWCASE_OVERDUE_MAX_DAYS_AGO)),
        ])->save();

        $dueToday->forceFill([
            'is_completed' => false,
            'completed_at' => null,
            'due_date' => today(),
        ])->save();
    }

    /**
     * F23: the sharing pass. Guarantees a pending invitation for
     * `demo1@example.com` (so a developer logging in as that account has
     * something in the notification center) and puts a real, accepted
     * cross-user collaborator on a handful of other lists. Degrades to a
     * no-op whenever the population is too small to satisfy either
     * guarantee (`DemoSeederTest` calls `run(3)`, and `run(1)` is used
     * elsewhere) rather than throwing — this is demo flavour, not a
     * required invariant of the dataset.
     *
     * @param  Collection<int, User>  $users
     */
    private function shareLists(Collection $users): void
    {
        if ($users->count() < 2) {
            return;
        }

        $sharing = app(ListSharingService::class);

        $guaranteedListId = $this->guaranteeDemo1PendingInvitation($users, $sharing);
        $this->createAcceptedShares($users, $sharing, $guaranteedListId);
    }

    /**
     * `demo1@example.com` only exists once some earlier `run()` call has
     * created it — this is the *first* demo account, not necessarily a
     * member of *this* call's own `$users` batch (the sequence continues
     * across calls, per `createUsers()`'s own docblock) — so it is resolved
     * with its own query rather than assumed to be `$users->first()`.
     *
     * Returns the id of the list the guarantee actually landed on (or null
     * if it couldn't run at all) — `createAcceptedShares()` uses this to
     * avoid the one specific collision that would silently defeat this
     * guarantee, without excluding demo1 from the rest of the sharing pass.
     *
     * @param  Collection<int, User>  $users
     */
    private function guaranteeDemo1PendingInvitation(Collection $users, ListSharingService $sharing): ?int
    {
        $demo1 = User::query()->where('email', 'demo1@example.com')->first();

        if ($demo1 === null) {
            return null;
        }

        $inviter = $users->first(fn (User $user): bool => $user->id !== $demo1->id);

        if ($inviter === null) {
            return null;
        }

        $list = $this->randomShareableListFor($inviter);

        if ($list === null) {
            return null;
        }

        try {
            $sharing->invite($list, $inviter, $demo1->email);

            return $list->id;
        } catch (DomainException $exception) {
            // A re-run against a non-fresh database (or an unlucky random
            // pick) may find demo1 already a member of this exact list —
            // demo data flavour, not worth failing the whole seed over, but
            // silent failure here means F23's guarantee is quietly unmet,
            // so it goes to the seed output rather than nowhere.
            $this->warnSeeding("Could not guarantee a pending invitation for demo1@example.com: {$exception->getMessage()}");

            return null;
        }
    }

    /**
     * Puts a real, accepted collaborator on up to SHARED_LISTS_MAX lists,
     * pairing distinct owner/collaborator users so no one invites
     * themselves. Every pairing degrades silently (skip and warn, never
     * throw) if the owner happens to have no shareable list or the
     * invite/accept is rejected for any reason — see the class docblock.
     *
     * demo1@example.com is deliberately *not* excluded from this pass —
     * F23 wants that account reachable as a real accepted collaborator too,
     * not just a pending invitee, so excluding it entirely would defeat
     * half of what the demo data is supposed to exercise (the share
     * dialog's roster, the shared sidebar indicator, "Leave list"). Only
     * the exact collision that would silently erase
     * guaranteeDemo1PendingInvitation()'s own guarantee is excluded: this
     * pass inviting *demo1 specifically* to the *exact list* ($excludedListId)
     * that already holds demo1's guaranteed pending row — invite()'s
     * idempotent-pending branch would return that same row, and accept()
     * would immediately flip it from pending to accepted. Found via a
     * genuine order-dependent test flake, not a hypothetical.
     *
     * @param  Collection<int, User>  $users
     */
    private function createAcceptedShares(Collection $users, ListSharingService $sharing, ?int $excludedListId): void
    {
        $owners = $users->shuffle()->values();
        $pairCount = min(self::SHARED_LISTS_MAX, $owners->count());

        for ($index = 0; $index < $pairCount; $index++) {
            $owner = $owners[$index];
            $collaborator = $owners[($index + 1) % $owners->count()];

            if ($owner->id === $collaborator->id) {
                continue;
            }

            $list = $this->randomShareableListFor($owner);

            if ($list === null) {
                continue;
            }

            if ($excludedListId !== null && $list->id === $excludedListId && $collaborator->email === 'demo1@example.com') {
                continue;
            }

            try {
                $membership = $sharing->invite($list, $owner, $collaborator->email);
                $sharing->accept($membership, $collaborator);
            } catch (DomainException $exception) {
                $this->warnSeeding("Could not share list #{$list->id} with {$collaborator->email}: {$exception->getMessage()}");
            }
        }
    }

    /**
     * The seeder's one warning channel, mirroring `reportSummary()`'s own
     * optional-command access — silent everywhere except an actual Artisan
     * run, and never fatal (`AGENTS.md`/`.claude/rules/laravel.md`: "never
     * catch silently — always log or re-throw"; this is the seeder's
     * equivalent of surfacing the failure rather than swallowing it).
     */
    private function warnSeeding(string $message): void
    {
        $this->seederCommand?->warn($message);
    }

    /**
     * A random non-default list owned by $user — Inbox is never shareable
     * (`ListSharingService::invite()` already rejects it), so this simply
     * never offers it as a candidate.
     */
    private function randomShareableListFor(User $user): ?TaskList
    {
        return TaskList::query()
            ->where('user_id', $user->id)
            ->where('is_default', false)
            ->inRandomOrder()
            ->first();
    }

    private function randomPastMoment(int $maxDaysAgo): CarbonInterface
    {
        return now()
            ->subDays(random_int(0, $maxDaysAgo))
            ->subMinutes(random_int(0, 1_439));
    }

    private function rollChance(float $chance): bool
    {
        return random_int(1, 1_000) <= (int) round($chance * 1_000);
    }

    /**
     * @param  array{folders: int, lists: int, tasks: int, subtasks: int}  $totals
     */
    private function reportSummary(int $userCount, array $totals): void
    {
        $this->seederCommand?->getOutput()->writeln(sprintf(
            'Demo dataset seeded: %d users, %d folders, %d lists, %d tasks, %d subtasks.',
            $userCount,
            $totals['folders'],
            $totals['lists'],
            $totals['tasks'],
            $totals['subtasks'],
        ));
    }
}
