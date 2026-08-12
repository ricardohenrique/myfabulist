<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaskListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;

/**
 * `folder_id`/`position` are not real `task_lists` columns (Plan 1, Step 2
 * dropped them) — they are per-viewer placement, sourced from the acting
 * user's own `task_list_members` row. Every read path that needs it joins
 * via `scopeJoinMemberPlacement()` below (`allForUser()`,
 * `findAccessibleFor()`/`findDefaultFor()`/`findDeletedForUser()`, and route
 * binding — see `App\Providers\AppServiceProvider::configureRouteBindings()`)
 * or attaches it via `withPlacement()` after `create()`/`update()`. Because
 * of that, `position`
 * is genuinely nullable on any instance that reached the caller through a
 * path that doesn't populate it (there is no such path left in this
 * codebase today, but a future direct `TaskList::find()` would produce
 * exactly that null) — the docblock below reflects that honestly rather
 * than asserting a guarantee the type system can't back up. Do not
 * `forceFill()`/`fill()` either attribute here — task_lists has no such
 * column to write to.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $folder_id
 * @property string $name
 * @property bool $is_default
 * @property int|null $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Folder|null $folder
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, TaskListMember> $members
 * @property-read int|null $tasks_count
 * @property-read int|null $active_tasks_count
 */
#[Fillable(['name', 'is_default'])]
class TaskList extends Model
{
    /** @use HasFactory<TaskListFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            // Both arrive as raw, join-aliased values from
            // task_list_members, not native task_lists columns — casting
            // folder_id (not just position) keeps TaskListService::update()'s
            // `$folder?->id === $taskList->folder_id` strict comparison safe
            // even if a driver ever returns the joined column as a numeric
            // string; without this, that comparison would silently always
            // be false ("always reposition") instead of loudly wrong.
            'folder_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Every membership row for this list — pending invitations, accepted
     * members (the owner included, per Step 1's backfill), and retained
     * declines. `status = 'accepted'` is the access predicate authorization
     * is built on starting in Step 4; placement is read via
     * `scopeJoinMemberPlacement()` below, not this relation.
     *
     * @return HasMany<TaskListMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TaskListMember::class);
    }

    /**
     * Attach the viewer's placement onto this in-memory instance without
     * marking it dirty. `folder_id`/`position` are not real `task_lists`
     * columns (Plan 1, Step 2), so a plain `setAttribute()` would leave
     * them in `getDirty()` forever — the very next unrelated `->save()` on
     * this same instance (a rename, a soft delete, ...) would then try to
     * write a "folder_id"/"position" column that does not exist and blow up
     * with a "no such column" query error. `syncOriginalAttributes()`
     * immediately marks both clean again, so `$list->folder_id`/
     * `$list->position` read back correctly while every future `save()`
     * call on this instance stays limited to real columns.
     */
    public function withPlacement(?int $folderId, int $position): static
    {
        $this->setAttribute('folder_id', $folderId);
        $this->setAttribute('position', $position);
        $this->syncOriginalAttributes(['folder_id', 'position']);

        return $this;
    }

    /**
     * The one place that owns the "join task_list_members, alias
     * folder_id/position onto the TaskList row" shape — every repository
     * read path that needs a viewer's placement chains this local scope
     * instead of hand-rolling the join again. Still a query being *built*,
     * not executed — the caller (a repository) is the one that terminates
     * it with `->get()`/`->find()`/etc., so this stays consistent with
     * "repositories are the only layer that queries the database"; scopes
     * are the sanctioned place for reusable query logic on a model.
     *
     * `$requireAccess = true` (used by `allForUser()` and the other
     * `find*` reads, all already scoped to lists this viewer owns) inner-
     * joins, so a list with no matching accepted membership row drops out
     * entirely — the stricter, correct behaviour for "this user's own
     * lists". `$requireAccess = false` (used only by route-model-binding
     * resolution) left-joins instead: a list this viewer cannot access
     * must still resolve, with a null placement, so `TaskListPolicy` gets
     * the chance to deny it with 403 rather than the join itself turning a
     * cross-user request into a 404.
     *
     * @param  Builder<TaskList>  $query
     * @return Builder<TaskList>
     */
    public function scopeJoinMemberPlacement(Builder $query, int $viewerId, bool $requireAccess = true): Builder
    {
        $joinMethod = $requireAccess ? 'join' : 'leftJoin';

        return $query
            ->{$joinMethod}('task_list_members', function (JoinClause $join) use ($viewerId) {
                $join->on('task_list_members.task_list_id', '=', $this->qualifyColumn('id'))
                    ->where('task_list_members.user_id', $viewerId)
                    ->where('task_list_members.status', 'accepted');
            })
            ->select($this->qualifyColumn('*'), 'task_list_members.folder_id as folder_id', 'task_list_members.position as position');
    }
}
