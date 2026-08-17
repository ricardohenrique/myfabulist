<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\WorkspaceBackgroundOption;
use App\Repositories\Contracts\WorkspaceBackgroundOptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentWorkspaceBackgroundOptionRepository implements WorkspaceBackgroundOptionRepositoryInterface
{
    /**
     * @return Collection<int, WorkspaceBackgroundOption>
     */
    public function enabled(): Collection
    {
        return WorkspaceBackgroundOption::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findByKey(string $key): ?WorkspaceBackgroundOption
    {
        return WorkspaceBackgroundOption::query()
            ->where('key', $key)
            ->first();
    }

    public function findById(int $id): ?WorkspaceBackgroundOption
    {
        return WorkspaceBackgroundOption::query()->find($id);
    }

    public function default(): ?WorkspaceBackgroundOption
    {
        return WorkspaceBackgroundOption::query()
            ->where('is_default', true)
            ->first();
    }
}
