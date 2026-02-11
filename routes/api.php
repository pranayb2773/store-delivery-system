<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::post('/stores', [StoreController::class, 'store'])->name('stores.create');

    Route::get('/stores/can-deliver', [StoreController::class, 'canDeliver'])->name('stores.can-deliver');

    Route::get('/stores/nearby', [StoreController::class, 'nearbyStores'])->name('stores.nearby');
});
