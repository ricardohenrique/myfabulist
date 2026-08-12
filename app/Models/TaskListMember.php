<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TaskListMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A list membership row. One row per (task_list, user) pair — a pending
 * invitation, an accepted member (including the owner, per Plan 1's Step 1
 * backfill), or a retained decline. `status = 'accepted'` is the sole access
 * predicate future steps will build authorization on; `folder_id`/`position`
 * carry this member's own sidebar placement for the list.
 *
 * @property int $id
 * @property int $task_list_id
 * @property int $user_id
 * @property string $status
 * @property int|null $folder_id
 * @property int $position
 * @property int|null $invited_by_user_id
 * @property CarbonImmutable|null $invited_at
 * @property CarbonImmutable|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TaskList $taskList
 * @property-read User $user
 * @property-read Folder|null $folder
 * @property-read User|null $invitedBy
 */
#[Fillable(['status', 'folder_id', 'position', 'invited_by_user_id', 'invited_at', 'responded_at'])]
class TaskListMember extends Model
{
    /** @use HasFactory<TaskListMemberFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'invited_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<TaskList, $this>
     */
    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
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
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
