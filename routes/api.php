<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\SalesReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/status', [AiController::class, 'status']);
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->whereNumber('id');
    Route::get('/deals', [DealController::class, 'index']);
    Route::get('/deals/{id}', [DealController::class, 'show'])->whereNumber('id');
    Route::get('/reports/sales', SalesReportController::class);
    Route::middleware('throttle:ai')->group(function (): void {
        Route::post('/customers/{id}/ai-summary', [AiController::class, 'customer'])->whereNumber('id');
        Route::post('/assistant/messages', [AiController::class, 'ask']);
        Route::post('/reports/sales/ai-summary', [AiController::class, 'report']);
    });
});
