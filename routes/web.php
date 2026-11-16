<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['name' => 'Orbit CRM API', 'health' => '/up']);
});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::post('/session/refresh', [AuthController::class, 'refresh'])->middleware(['auth', 'throttle:refresh']);
