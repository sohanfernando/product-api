<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\RequireStatefulRequest;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireStatefulRequest::class)->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::get('/user', [ProfileController::class, 'show']);
        Route::apiResource('products', ProductController::class)->whereNumber('product');
    });
});
