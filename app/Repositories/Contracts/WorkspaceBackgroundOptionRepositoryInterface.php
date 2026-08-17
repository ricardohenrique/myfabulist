<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\WorkspaceBackgroundOption;
use Illuminate\Database\Eloquent\Collection;

interface WorkspaceBackgroundOptionRepositoryInterface
{
    /**
     * Every currently selectable option, ordered for display. Does not
     * include disabled options — `WorkspaceBackgroundService` is the one
     * that re-adds a user's own disabled selection back into the list they
     * see (Step 1/2's "disabling hides it from new selections only" rule).
     *
     * @return Collection<int, WorkspaceBackgroundOption>
     */
    public function enabled(): Collection;

    /**
     * Look up an option by its stable `key` (e.g. 'flat_color'), regardless
     * of whether it is currently enabled.
     */
    public function findByKey(string $key): ?WorkspaceBackgroundOption;

    /**
     * Look up an option by id, regardless of whether it is currently
     * enabled — used to resolve a user's already-selected option even after
     * it has since been disabled.
     */
    public function findById(int $id): ?WorkspaceBackgroundOption;

    /**
     * The platform's designated default option (`is_default`) — the one new
     * users start on and "Use default" reverts to. Null only if the catalog
     * is misconfigured (no row currently flagged as default).
     */
    public function default(): ?WorkspaceBackgroundOption;
}
