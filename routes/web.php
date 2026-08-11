<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\StarredController;
use App\Http\Controllers\StaticPrototypeController;
use App\Http\Controllers\TaskListController;
use App\Http\Controllers\Web\CompleteTaskController;
use App\Http\Controllers\Web\FolderController;
use App\Http\Controllers\Web\FolderOrderController;
use App\Http\Controllers\Web\MoveTaskController;
use App\Http\Controllers\Web\RestoreTaskController;
use App\Http\Controllers\Web\StarTaskController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\TaskListController as WebTaskListController;
use App\Http\Controllers\Web\TaskListOrderController;
use App\Http\Controllers\Web\TaskListTaskController;
use App\Http\Controllers\Web\TaskOrderController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::middleware('guest')->group(function () {
    Route::get('login', fn () => Inertia::render('auth/login'))->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::get('register', fn () => Inertia::render('auth/register'))->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::get('/', HomeController::class)->name('home');

if (app()->environment(['local', 'testing'])) {
    Route::get('prototype/{view?}', StaticPrototypeController::class)
        ->where('view', 'inbox|list|starred|empty|complete')
        ->name('prototype.show');
}

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('inbox', InboxController::class)->name('inbox');
    Route::get('starred', StarredController::class)->name('starred');
    Route::get('lists/{list}', TaskListController::class)->whereNumber('list')->name('lists.show');

    Route::put('folders/order', FolderOrderController::class)->name('folders.order');
    Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    Route::put('folders/{folder}', [FolderController::class, 'update'])->whereNumber('folder')->name('folders.update');
    Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->whereNumber('folder')->name('folders.destroy');

    Route::put('lists/order', TaskListOrderController::class)->name('lists.order');
    Route::post('lists', [WebTaskListController::class, 'store'])->name('lists.store');
    Route::put('lists/{list}', [WebTaskListController::class, 'update'])->whereNumber('list')->name('lists.update');
    Route::delete('lists/{list}', [WebTaskListController::class, 'destroy'])->whereNumber('list')->name('lists.destroy');

    Route::post('lists/{list}/tasks', [TaskListTaskController::class, 'store'])->whereNumber('list')->name('lists.tasks.store');
    Route::put('lists/{list}/task-order', TaskOrderController::class)->whereNumber('list')->name('lists.task-order');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->whereNumber('task')->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->whereNumber('task')->name('tasks.destroy');
    Route::post('tasks/{task}/complete', CompleteTaskController::class)->whereNumber('task')->name('tasks.complete');
    Route::post('tasks/{task}/restore', RestoreTaskController::class)->whereNumber('task')->name('tasks.restore');
    Route::put('tasks/{task}/star', StarTaskController::class)->whereNumber('task')->name('tasks.star');
    Route::post('tasks/{task}/move', MoveTaskController::class)->whereNumber('task')->name('tasks.move');
});
