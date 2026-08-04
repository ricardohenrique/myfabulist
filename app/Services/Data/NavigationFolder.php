<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Models\Folder;
use App\Models\TaskList;
use Illuminate\Database\Eloquent\Collection;

/**
 * One folder row in the sidebar tree, paired with the lists that belong to
 * it (in position order). Always has a `lists` collection, even when empty
 * — a folder with no lists yields an empty Collection, never a missing key
 * (Step 1 requirement).
 */
final readonly class NavigationFolder
{
    /**
     * @param  Collection<int, TaskList>  $lists
     */
    public function __construct(
        public Folder $folder,
        public Collection $lists,
    ) {}
}
