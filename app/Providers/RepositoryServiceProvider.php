<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\CompletionSoundRepositoryInterface;
use App\Repositories\Contracts\FolderRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\SubtaskRepositoryInterface;
use App\Repositories\Contracts\TaskCommentRepositoryInterface;
use App\Repositories\Contracts\TaskListMemberRepositoryInterface;
use App\Repositories\Contracts\TaskListRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceBackgroundOptionRepositoryInterface;
use App\Repositories\EloquentCompletionSoundRepository;
use App\Repositories\EloquentFolderRepository;
use App\Repositories\EloquentNotificationRepository;
use App\Repositories\EloquentSubtaskRepository;
use App\Repositories\EloquentTaskCommentRepository;
use App\Repositories\EloquentTaskListMemberRepository;
use App\Repositories\EloquentTaskListRepository;
use App\Repositories\EloquentTaskRepository;
use App\Repositories\EloquentUserRepository;
use App\Repositories\EloquentWorkspaceBackgroundOptionRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompletionSoundRepositoryInterface::class, EloquentCompletionSoundRepository::class);
        $this->app->bind(FolderRepositoryInterface::class, EloquentFolderRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(TaskListRepositoryInterface::class, EloquentTaskListRepository::class);
        $this->app->bind(TaskCommentRepositoryInterface::class, EloquentTaskCommentRepository::class);
        $this->app->bind(TaskRepositoryInterface::class, EloquentTaskRepository::class);
        $this->app->bind(SubtaskRepositoryInterface::class, EloquentSubtaskRepository::class);
        $this->app->bind(TaskListMemberRepositoryInterface::class, EloquentTaskListMemberRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(WorkspaceBackgroundOptionRepositoryInterface::class, EloquentWorkspaceBackgroundOptionRepository::class);
    }
}
