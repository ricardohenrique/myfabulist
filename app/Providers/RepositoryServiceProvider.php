<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\FolderRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\EloquentFolderRepository;
use App\Repositories\EloquentTaskListRepository;
use App\Repositories\EloquentTaskRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FolderRepositoryInterface::class, EloquentFolderRepository::class);
        $this->app->bind(TaskListRepositoryInterface::class, EloquentTaskListRepository::class);
        $this->app->bind(TaskRepositoryInterface::class, EloquentTaskRepository::class);
    }
}
