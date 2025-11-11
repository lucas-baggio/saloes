<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanLimitTestController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SubServiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Health check route - verifica se a aplicação e banco estão funcionando
Route::get('health', [HealthCheckController::class, 'check']);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('resend-verification', [AuthController::class, 'resendVerificationEmail']);
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

    // Planos
    Route::get('plans', [PlanController::class, 'index']);
    Route::get('plans/current', [PlanController::class, 'current']); // Deve vir ANTES de plans/{plan}
    Route::get('plans/{plan}', [PlanController::class, 'show']);
    Route::post('plans/subscribe', [PlanController::class, 'subscribe']);
    Route::post('plans/cancel', [PlanController::class, 'cancel']);
    Route::post('plans', [PlanController::class, 'store']); // Criar plano (para desenvolvimento)

    // Pagamentos
    Route::post('payments/process', [PaymentController::class, 'process']);
    Route::get('payments/{paymentId}/status', [PaymentController::class, 'getStatus']);
});

// Webhook do Mercado Pago (sem autenticação)
Route::post('payments/webhook', [PaymentController::class, 'webhook']);

// Rotas de teste (apenas em desenvolvimento)
if (app()->environment('local', 'testing')) {
    Route::prefix('test')->group(function () {
        Route::get('plan-limits/{userId}', [PlanLimitTestController::class, 'testLimits']);
        Route::post('create-test-user', [PlanLimitTestController::class, 'createTestUser']);
    });
}

