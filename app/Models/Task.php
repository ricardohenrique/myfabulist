<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $task_list_id
 * @property string $title
 * @property string|null $note
 * @property bool $is_completed
 * @property CarbonImmutable|null $completed_at
 * @property bool $is_starred
 * @property CarbonImmutable|null $due_date
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read TaskList $taskList
 * @property-read Collection<int, TaskComment> $comments
 * @property-read Collection<int, Subtask> $subtasks
 */
#[Fillable(['title', 'note', 'is_starred', 'due_date', 'position'])]
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
     * @return BelongsTo<TaskList, $this>
     */
    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
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
}
