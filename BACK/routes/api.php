<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    // upcoming birthdays for dashboard UI
    Route::get('/birthdays', [DashboardController::class, 'birthdays']);
    // workers currently "en_trabajo"
    Route::get('/working', [DashboardController::class, 'working']);
});

// health endpoint (public) used by external checks
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// simple OPTIONS catch-all for CORS preflight (dev convenience)
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
})->where('any', '.*');
