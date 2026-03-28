<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TimeOffController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\AnnouncementController;

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
    Route::get('/employees/{id}/attendance-month', [AuthController::class, 'employeeAttendanceMonth']);
    Route::get('/employees/{id}/documents', [AuthController::class, 'employeeDocuments']);
    Route::get('/employees/{employeeId}/documents/{docId}/download', [AuthController::class, 'employeeDocumentDownload']);
    Route::post('/employees', [AuthController::class, 'createEmployee']);
    Route::post('/employees/{id}', [AuthController::class, 'updateEmployee']);
    Route::put('/employees/{id}', [AuthController::class, 'updateEmployee']);
    Route::delete('/employees/{id}', [AuthController::class, 'deleteEmployee']);
    Route::get('/permissions/catalog', [AuthController::class, 'permissionsCatalog']);
    Route::put('/permissions/teams/{id}', [AuthController::class, 'updateTeamPermissions']);
    Route::get('/settings/schedules', [AuthController::class, 'scheduleTemplates']);
    Route::post('/settings/schedules', [AuthController::class, 'createScheduleTemplate']);
    Route::put('/settings/schedules/{id}', [AuthController::class, 'updateScheduleTemplate']);
    Route::delete('/settings/schedules/{id}', [AuthController::class, 'deleteScheduleTemplate']);

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
    Route::get('/attendance/who-is-in', [AuthController::class, 'attendanceWhoIsIn']);
    Route::post('/attendance/requests', [AuthController::class, 'requestAttendanceAdjustment']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/layout', [DashboardController::class, 'dashboardLayout']);
    Route::put('/dashboard/layout', [DashboardController::class, 'saveDashboardLayout']);
    // upcoming birthdays for dashboard UI
    Route::get('/birthdays', [DashboardController::class, 'birthdays']);
    // workers currently "en_trabajo"
    Route::get('/working', [DashboardController::class, 'working']);
    Route::get('/team/me', [DashboardController::class, 'team']);
    Route::get('/holidays/upcoming', [DashboardController::class, 'upcomingHolidays']);
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/teams', [AnnouncementController::class, 'teams']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::get('/documents/summary', [DashboardController::class, 'documentsSummary']);
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

    // time-off / vacations calendar
    Route::get('/timeoff/calendar', [TimeOffController::class, 'calendar']);
    Route::post('/timeoff/events', [TimeOffController::class, 'create']);
    Route::put('/timeoff/events/{id}', [TimeOffController::class, 'update']);
    Route::get('/timeoff/requests', [TimeOffController::class, 'requests']);
    Route::post('/timeoff/requests/{id}/review', [TimeOffController::class, 'review']);

    // documents center
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents/upload', [DocumentController::class, 'upload']);
    Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
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
