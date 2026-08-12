<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Folder;
use App\Models\TaskList;
use App\Models\TaskListMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskList>
 */
class TaskListFactory extends Factory
{
    protected $model = TaskList::class;

    /**
     * Define the model's default state. `folder_id`/`position` are no
     * longer real `task_lists` columns (Plan 1, Step 2) — they never
     * belong in this array. Placement is entirely the job of `configure()`
     * and the `inFolder()`/`atPosition()` states below, which write to
     * `task_list_members` instead.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the list belongs to the given folder, at the given
     * position (default 0 — but only actually *written* when $position is
     * explicitly passed; see below). Writes the owner's membership row
     * rather than a `task_lists` column — see `configure()`'s docblock
     * below.
     *
     * Deliberately leaves `position` untouched in the database when
     * `$position === null`, rather than unconditionally writing `0`: since
     * `afterCreating` callbacks are last-write-wins on the same row,
     * `->atPosition(5)->inFolder($folder)` and `->inFolder($folder)->
     * atPosition(5)` would otherwise disagree — whichever callback runs
     * last would silently overwrite the other's position, making chain
     * order matter in a way nothing about either method's name suggests it
     * should. With this guard, only a state that actually specifies a
     * position can change it, so chain order stops mattering for position
     * specifically (`inFolder()` alone still always writes `folder_id`,
     * which has no such ambiguity — there's only one folder state).
     *
     * Note for bulk creation:
     * `TaskList::factory()->count(N)->inFolder($folder)->create()` gives
     * all N lists the *same* position (0, since none of them pass an
     * explicit `$position`) — this factory has no notion of "the Nth list
     * in this batch", unlike `Sequence`. That is harmless for a test that
     * only counts results (as `NavigationServiceTest` does today) but would
     * silently produce wrong data for a future test that asserts ordering
     * across a bulk-created batch — pass an explicit `$position` per list
     * (or call `atPosition()` per instance) if that is ever needed.
     */
    public function inFolder(Folder $folder, ?int $position = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $folder->user_id,
        ])->afterCreating(function (TaskList $taskList) use ($folder, $position) {
            $update = ['folder_id' => $folder->id];

            if ($position !== null) {
                $update['position'] = $position;
            }

            TaskListMember::query()
                ->where('task_list_id', $taskList->id)
                ->where('user_id', $taskList->user_id)
                ->update($update);

            // Mirrors EloquentTaskListRepository::create()/update()'s own
            // withPlacement() call — without this, the TaskList instance
            // ->create() hands back would still read folder_id/position as
            // null (configure()'s defaults), even though its membership row
            // was just updated to this folder/position underneath it. Using
            // withPlacement() rather than a raw setAttribute() also matters
            // here: a bare setAttribute() would leave folder_id/position
            // "dirty" forever, and the very next ->save() this instance
            // sees anywhere in a test (a rename, a soft delete, ...) would
            // then try to write those two non-existent columns and fail.
            // $taskList->position already reflects whatever configure() or
            // an earlier-registered atPosition() callback set it to, since
            // afterCreating callbacks run in registration order.
            $taskList->withPlacement($folder->id, $position ?? $taskList->position);
        });
    }

    /**
     * Indicate the owner's sidebar position for this list, independent of
     * which folder (or no folder) it lands in.
     */
    public function atPosition(int $position): static
    {
        return $this->afterCreating(function (TaskList $taskList) use ($position) {
            TaskListMember::query()
                ->where('task_list_id', $taskList->id)
                ->where('user_id', $taskList->user_id)
                ->update(['position' => $position]);

            $taskList->withPlacement($taskList->folder_id, $position);
        });
    }

    /**
     * Indicate that the list is the user's default Inbox. `folder_id`
     * stays null and `position` stays 0 — already `configure()`'s default.
     */
    public function inbox(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Inbox',
            'is_default' => true,
        ]);
    }

    /**
     * Plan 1 ("Shared Lists and Collaboration"), Step 2: every factory-
     * created list gets the same accepted owner membership row the
     * repository's `create()`/`createDefaultFor()` produce for real list
     * creation, at the same defaults (`folder_id = null`, `position = 0`).
     * `afterCreating` (not `afterMaking`) because the membership row needs
     * the list's own persisted `id`.
     *
     * `inFolder()`/`atPosition()` above register a *second* `afterCreating`
     * callback each — `afterCreating` callbacks accumulate across a factory
     * chain and run in registration order, so `configure()`'s callback
     * (registered first, since it runs on every `TaskList::factory()` call
     * regardless of which states are chained afterwards) always inserts the
     * row before `inFolder()`/`atPosition()` try to update it. Those states
     * therefore UPDATE this same row — never a second INSERT, which would
     * violate the unique (task_list_id, user_id) constraint. Verified with
     * `TaskList::factory()->inFolder($folder, 3)->create()` and
     * `TaskList::factory()->atPosition(2)->create()` while writing this.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (TaskList $taskList) {
            TaskListMember::factory()->forTaskList($taskList)->create([
                'folder_id' => null,
                'position' => 0,
            ]);

            // See the comment in inFolder() above for why this matters:
            // the TaskList instance ->create() returns must carry the same
            // placement its own membership row just got, without a re-fetch,
            // and without leaving folder_id/position "dirty" for some later
            // unrelated ->save() to trip over.
            $taskList->withPlacement(null, 0);
        });
    }
}
