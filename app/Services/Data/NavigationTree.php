<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Models\TaskList;
use Illuminate\Database\Eloquent\Collection;

/**
 * The sidebar's read model: the Inbox, folders (each carrying its own
 * lists), and lists that live outside any folder. Assembled once by
 * NavigationService::treeFor() and rendered as-is by the Sidebar component
 * — no grouping or sorting happens in Blade.
 */
final readonly class NavigationTree
{
    /**
     * @param  array<int, NavigationFolder>  $folders
     * @param  Collection<int, TaskList>  $ungroupedLists
     */
    public function __construct(
        public TaskList $inbox,
        public array $folders,
        public Collection $ungroupedLists,
    ) {}
}
