<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * `is_starred` is not a real `tasks` column (Plan 1, Step 3 dropped it) —
 * it is a per-viewer computed value, sourced from whether the acting user
 * has a `task_stars` row for this task. Every read path that needs it
 * chains `scopeWithStarredFor()` below (`activeForList()`,
 * `completedForList()`, `starredForUser()`, and route binding — see
 * `App\Providers\AppServiceProvider::configureRouteBindings()`) or attaches
 * it via `withStarred()` after `star()`/`unstar()`/`update()`. Because of
 * that, `is_starred` is genuinely nullable on any instance that reached the
 * caller through a path that doesn't populate it — the docblock below
 * reflects that honestly rather than asserting a guarantee the type system
 * can't back up (the same reasoning `App\Models\TaskList` applies to its
 * own `position`). Do not `forceFill()`/`fill()` this attribute either —
 * `tasks` has no such column to write to.
 *
 * @property int $id
 * @property int $user_id
 * @property int $task_list_id
 * @property string $title
 * @property string|null $note
 * @property bool $is_completed
 * @property CarbonImmutable|null $completed_at
 * @property bool|null $is_starred
 * @property CarbonImmutable|null $due_date
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read TaskList $taskList
 * @property-read Collection<int, TaskComment> $comments
 * @property-read Collection<int, Subtask> $subtasks
 * @property-read Collection<int, TaskStar> $stars
 */
#[Fillable(['title', 'note', 'due_date', 'position'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
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
            'is_completed' => 'boolean',
            'completed_at' => 'immutable_datetime',
            'is_starred' => 'boolean',
            'due_date' => 'immutable_date',
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
     * The user who created this task — identical relation to `user()`
     * above, added purely so new call sites can document intent (Plan 1
     * ("Shared Lists and Collaboration"), Architecture Decision 4:
     * `tasks.user_id` is reframed as the *creator*, attribution only, not
     * an authorization boundary — that lives entirely in `TaskPolicy`,
     * which resolves through list membership, never `user_id`). `user()`
     * stays as-is so existing call sites are untouched; this is purely
     * additive.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<TaskList, $this>
     */
    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    /**
     * Every star this task has received, one row per user who starred it.
     * `scopeWithStarredFor()` below is the sanctioned way to read "is this
     * starred *for a given viewer*" — this relation exists for the pivot
     * write path (`star()`/`unstar()`) and the `withExists()` alias to
     * build on, not for `TaskResource`/`WorkspacePresenter` to load and
     * inspect directly.
     *
     * @return HasMany<TaskStar, $this>
     */
    public function stars(): HasMany
    {
        return $this->hasMany(TaskStar::class);
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)
            ->oldest()
            ->orderBy('id');
    }

    /**
     * A subtask never has its own subtasks (no nesting) — this is the only
     * relation Subtask exposes, structurally ruling out a second level.
     *
     * @return HasMany<Subtask, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)
            ->oldest()
            ->orderBy('id');
    }

    /**
     * Overdue/today/upcoming derived purely from `due_date` (D3), a pure
     * read accessor with no queries — the `User::profilePhotoUrl` precedent.
     * Comparisons use today() (start of day, app timezone) rather than
     * now(), so "due today" cannot flicker to "overdue" as the clock moves
     * through the day (R3). `due_date` is a date-only column; per-user
     * timezones are out of scope for this accessor.
     *
     * A completed task never reports "overdue" — the row is already muted,
     * so it should not additionally scream red (D3).
     */
    public function dueDateStatus(): ?string
    {
        if ($this->due_date === null) {
            return null;
        }

        $today = today();

        return match (true) {
            $this->due_date->isSameDay($today) => 'today',
            $this->due_date->greaterThan($today) => 'upcoming',
            $this->is_completed => null,
            default => 'overdue',
        };
    }

    /**
     * Attach the viewer's star state onto this in-memory instance without
     * marking it dirty. `is_starred` is not a real `tasks` column (Plan 1,
     * Step 3), so a plain `setAttribute()` would leave it in `getDirty()`
     * forever — the very next unrelated `->save()` on this same instance
     * (a rename, a complete, ...) would then try to write an "is_starred"
     * column that does not exist and blow up with a "no such column" query
     * error. `syncOriginalAttributes()` immediately marks it clean again, so
     * `$task->is_starred` reads back correctly while every future `save()`
     * call on this instance stays limited to real columns. Mirrors
     * `App\Models\TaskList::withPlacement()` exactly.
     */
    public function withStarred(bool $isStarred): static
    {
        $this->setAttribute('is_starred', $isStarred);
        $this->syncOriginalAttributes(['is_starred']);

        return $this;
    }

    /**
     * The one place that owns the "exists-subquery alias `is_starred` onto
     * the Task row, for this viewer" shape — every repository read path
     * that needs a viewer's star state chains this local scope instead of
     * hand-rolling the `withExists()` call again. An exists-subquery
     * rather than a loaded relation (Architecture Decision 3): one extra
     * correlated subquery per query, not a query per row, so this can never
     * trip `Model::preventLazyLoading()` or add an N+1.
     *
     * Unlike `TaskList::scopeJoinMemberPlacement()`, this never restricts
     * which rows come back — it only adds a computed column — so it is safe
     * to apply to a route-bound task the viewer may or may not be allowed
     * to see; `TaskPolicy` is still the one that denies access.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeWithStarredFor(Builder $query, int $viewerId): Builder
    {
        return $query->withExists(['stars as is_starred' => fn (Builder $stars) => $stars->where('user_id', $viewerId)]);
    }
}
