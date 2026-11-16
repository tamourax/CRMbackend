<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CrmController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/status', [AiController::class, 'status']);
    Route::get('/customers', [CrmController::class, 'customers']);
    Route::get('/customers/{id}', [CrmController::class, 'customer'])->whereNumber('id');
    Route::get('/deals', [CrmController::class, 'deals']);
    Route::get('/deals/{id}', [CrmController::class, 'deal'])->whereNumber('id');
    Route::get('/reports/sales', [CrmController::class, 'report']);
    Route::middleware('throttle:ai')->group(function (): void {
        Route::post('/customers/{id}/ai-summary', [AiController::class, 'customer'])->whereNumber('id');
        Route::post('/assistant/messages', [AiController::class, 'ask']);
        Route::post('/reports/sales/ai-summary', [AiController::class, 'report']);
    });
});
