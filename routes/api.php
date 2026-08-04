<?php

use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\FolderOrderController;
use App\Http\Controllers\Api\V1\InboxController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every /api/v1 route lives inside this single authenticated group
| (auth:sanctum + verified). Never register a route outside of it —
| tests/Feature/Api/V1/ApiFoundationTest.php asserts every api/v1 route
| carries the auth:sanctum middleware (R2).
|
| Response envelope:
|   success:    {"data": ...}                                  (Resource default)
|   error:      {"message": "...", "error_code": "..."}         (DomainException)
|   validation: {"message": "...", "errors": {...}}              (Laravel default)
|
*/

Route::middleware(['auth:sanctum', 'verified'])->prefix('v1')->name('api.v1.')->group(function () {
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
});
