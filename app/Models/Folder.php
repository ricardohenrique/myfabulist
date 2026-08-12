<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, TaskList> $taskLists
 */
#[Fillable(['name', 'position'])]
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
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
     * A list's placement now lives on its task_list_members row, not on a
     * task_lists.folder_id column (Plan 1, Step 2 dropped it), so this is a
     * belongsToMany through task_list_members rather than a plain hasMany:
     * task_list_members.folder_id is the pivot column pointing back at this
     * folder, task_list_members.task_list_id is the pivot column pointing
     * at the related list. No extra `task_list_members.user_id =
     * folders.user_id` constraint is needed — a member can only ever place
     * a list into a folder they themselves own (`TaskListService::
     * resolveFolder()` scopes folder resolution to the acting user), so a
     * given folder_id can only ever appear on that one owner's membership
     * rows in the first place; constraining by folder_id alone is already
     * exactly as scoped as constraining by folder_id + user_id.
     *
     * The extra `select()` aliases the pivot's folder_id/position as
     * top-level attributes on each TaskList (alongside Eloquent's own
     * automatic `pivot_*` columns) so FolderResource/TaskListResource can
     * keep reading `$list->folder_id`/`$list->position` directly, exactly
     * like `EloquentTaskListRepository::allForUser()`'s join does.
     *
     * @return BelongsToMany<TaskList, $this>
     */
    public function taskLists(): BelongsToMany
    {
        return $this->belongsToMany(TaskList::class, 'task_list_members', 'folder_id', 'task_list_id')
            ->wherePivot('status', 'accepted')
            ->select(['task_lists.*', 'task_list_members.folder_id as folder_id', 'task_list_members.position as position'])
            ->orderBy('task_list_members.position')
            ->orderBy('task_lists.id');
    }
}
