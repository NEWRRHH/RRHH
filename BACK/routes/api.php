<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;

// apply a proper middleware class that always attaches the CORS headers
// (including on redirects such as the unauthorised login redirect). this
// covers both public and protected routes. the catch-all OPTIONS route
// below still explicitly returns the headers for preflight.

Route::middleware(\App\Http\Middleware\Cors::class)->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::put('/user/schedule', [AuthController::class, 'updateSchedule']);
    Route::get('/employees', [AuthController::class, 'employees']);
    Route::get('/employees/{id}', [AuthController::class, 'getEmployee']);
    Route::put('/employees/{id}', [AuthController::class, 'updateEmployee']);
    Route::delete('/employees/{id}', [AuthController::class, 'deleteEmployee']);

    // attendance control
    Route::post('/attendance/start', [AuthController::class, 'startAttendance']);
    Route::post('/attendance/pause', [AuthController::class, 'pauseAttendance']);
    Route::post('/attendance/resume', [AuthController::class, 'resumeAttendance']);
    Route::get('/attendance/status', [AuthController::class, 'attendanceStatus']);
    Route::get('/attendance/current', [AuthController::class, 'currentAttendance']);
    Route::post('/attendance/stop', [AuthController::class, 'stopAttendance']);
    Route::get('/attendance/info', [AuthController::class, 'attendanceInfo']);
    Route::get('/attendance/day', [AuthController::class, 'attendanceDay']);
    Route::get('/attendance/month', [AuthController::class, 'attendanceMonth']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    // upcoming birthdays for dashboard UI
    Route::get('/birthdays', [DashboardController::class, 'birthdays']);
    // workers currently "en_trabajo"
    Route::get('/working', [DashboardController::class, 'working']);
    // vacation info for the logged-in user
    Route::get('/vacations/me', [DashboardController::class, 'vacationsMe']);
    // mark notification read
    // legacy mark-notification-read (kept for compatibility)
    Route::post('/notification/{id}/read', [DashboardController::class, 'markNotificationRead']);

    // chat-style notifications endpoints
    Route::get('/notifications/conversations', [\App\Http\Controllers\Api\NotificationController::class, 'conversations']);
    Route::get('/notifications/conversation/{userId}', [\App\Http\Controllers\Api\NotificationController::class, 'conversation']);
    Route::post('/notifications/send', [\App\Http\Controllers\Api\NotificationController::class, 'send']);
    Route::get('/notifications/users', [\App\Http\Controllers\Api\NotificationController::class, 'users']);
    });
});

// health endpoint (public) used by external checks
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// simple OPTIONS catch-all for CORS preflight (dev convenience)
// also ensure our Cors middleware runs so we don't rely solely on this
// hard‑coded header list.
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
})->where('any', '.*')->middleware(\App\Http\Middleware\Cors::class);
