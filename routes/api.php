<?php

use App\Http\Controllers\Api\V1\CompleteTaskController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\FolderOrderController;
use App\Http\Controllers\Api\V1\InboxController;
use App\Http\Controllers\Api\V1\MoveTaskController;
use App\Http\Controllers\Api\V1\RestoreTaskController;
use App\Http\Controllers\Api\V1\StarredTaskController;
use App\Http\Controllers\Api\V1\TaskCommentController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskListController;
use App\Http\Controllers\Api\V1\TaskListOrderController;
use App\Http\Controllers\Api\V1\TaskListTaskController;
use App\Http\Controllers\Api\V1\TaskOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every /api/v1 route lives inside this single authenticated group
| (auth:sanctum). Never register a route outside of it —
| tests/Feature/Api/V1/ApiFoundationTest.php asserts every api/v1 route
| carries the auth:sanctum middleware (R2).
|
| Response envelope:
|   success:    {"data": ...}                                  (Resource default)
|   error:      {"message": "...", "error_code": "..."}         (DomainException)
|   validation: {"message": "...", "errors": {...}}              (Laravel default)
|
*/

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function () {
    Route::get('user', function (Request $request) {
        return $request->user();
    })->name('user');

    Route::get('inbox', InboxController::class)->name('inbox');

    // "folders/order" must be registered before the "folders/{folder}"
    // resource routes below, or "order" is swallowed as a folder id (R12).
    Route::put('folders/order', FolderOrderController::class)->name('folders.order');

    Route::apiResource('folders', FolderController::class)
        ->parameters(['folders' => 'folder'])
        ->where(['folder' => '[0-9]+']);

    // "lists/order" must be registered before the "lists/{list}" resource
    // routes below, or "order" is swallowed as a list id (R12).
    Route::put('lists/order', TaskListOrderController::class)->name('lists.order');

    Route::apiResource('lists', TaskListController::class)
        ->parameters(['lists' => 'list'])
        ->where(['list' => '[0-9]+']);

    Route::get('lists/{list}/tasks', [TaskListTaskController::class, 'index'])
        ->name('lists.tasks.index')
        ->whereNumber('list');
    Route::post('lists/{list}/tasks', [TaskListTaskController::class, 'store'])
        ->name('lists.tasks.store')
        ->whereNumber('list');
    Route::put('lists/{list}/task-order', TaskOrderController::class)
        ->name('lists.task-order')
        ->whereNumber('list');

    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index'])->name('tasks.comments.index');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('tasks/{task}/complete', CompleteTaskController::class)->name('tasks.complete');
    Route::post('tasks/{task}/restore', RestoreTaskController::class)->name('tasks.restore');
    Route::post('tasks/{task}/move', MoveTaskController::class)->name('tasks.move');

    Route::get('starred', StarredTaskController::class)->name('starred');
});
