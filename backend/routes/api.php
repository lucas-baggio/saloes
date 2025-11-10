<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SubServiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/revenue-chart', [DashboardController::class, 'revenueChart']);
    Route::get('dashboard/top-services', [DashboardController::class, 'topServices']);

    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('establishments', EstablishmentController::class);
    Route::apiResource('services', ServiceController::class);
    Route::apiResource('sub-services', SubServiceController::class);
    Route::apiResource('schedulings', SchedulingController::class);
});

