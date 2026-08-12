<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaskStarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single user's star on a single task. One row per (task, user) pair —
 * Plan 1, Step 3's replacement for the old `tasks.is_starred` column, so
 * starring is per-viewer instead of a single shared flag on the task.
 *
 * No `updated_at`: a star has no "updated" concept, only created (starred)
 * or deleted (unstarred) — `public $timestamps = false` disables Eloquent's
 * usual pair, and `created_at` is cast/exposed as a plain read-only
 * timestamp instead.
 *
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property-read Task $task
 * @property-read User $user
 */
#[Fillable([])]
class TaskStar extends Model
{
    /** @use HasFactory<TaskStarFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
