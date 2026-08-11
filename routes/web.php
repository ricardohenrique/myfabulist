<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\StarredController;
use App\Http\Controllers\StaticPrototypeController;
use App\Http\Controllers\TaskListController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

if (app()->environment(['local', 'testing'])) {
    Route::get('prototype/{view?}', StaticPrototypeController::class)
        ->where('view', 'inbox|list|starred|empty|complete')
        ->name('prototype.show');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inbox', InboxController::class)->name('inbox');
    Route::get('starred', StarredController::class)->name('starred');
    Route::get('lists/{list}', TaskListController::class)->whereNumber('list')->name('lists.show');
});

require __DIR__.'/settings.php';
