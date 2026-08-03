<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\StarredController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inbox', InboxController::class)->name('inbox');
    Route::get('starred', StarredController::class)->name('starred');
});

require __DIR__.'/settings.php';
