<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\AuditLogController as AdminAudit;
use App\Http\Controllers\Admin\CompanyController as AdminCompanies;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ErrorLogController as AdminErrors;
use App\Http\Controllers\Admin\UserController as AdminUsers;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TimeOffController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TeamController;

// apply a proper middleware class that always attaches the CORS headers
// (including on redirects such as the unauthorised login redirect). this
// covers both public and protected routes. the catch-all OPTIONS route
// below still explicitly returns the headers for preflight.

Route::middleware(\App\Http\Middleware\Cors::class)->group(function () {

    // brute-force protection (limiters defined in AppServiceProvider)
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // protected routes: auth first (so the api limiter can key by user id),
    // then bind the request to the user's company (multi-tenant filter)
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureTenant::class, 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/impersonation/stop', [AuthController::class, 'stopImpersonation']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::put('/user/schedule', [ScheduleController::class, 'updateSchedule']);
    Route::get('/employees', [EmployeeController::class, 'employees']);
    Route::get('/employees/{id}', [EmployeeController::class, 'getEmployee']);
    Route::get('/employees/{id}/attendance-month', [EmployeeController::class, 'employeeAttendanceMonth']);
    Route::get('/employees/{id}/documents', [EmployeeController::class, 'employeeDocuments']);
    Route::get('/employees/{employeeId}/documents/{docId}/download', [EmployeeController::class, 'employeeDocumentDownload']);
    Route::post('/employees', [EmployeeController::class, 'createEmployee']);
    Route::post('/employees/{id}', [EmployeeController::class, 'updateEmployee']);
    Route::put('/employees/{id}', [EmployeeController::class, 'updateEmployee']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'deleteEmployee']);
    // roles & permissions (admin only)
    Route::get('/permissions/catalog', [RoleController::class, 'catalog']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions']);
    Route::get('/settings/schedules', [ScheduleController::class, 'scheduleTemplates']);
    Route::post('/settings/schedules', [ScheduleController::class, 'createScheduleTemplate']);
    Route::put('/settings/schedules/{id}', [ScheduleController::class, 'updateScheduleTemplate']);
    Route::delete('/settings/schedules/{id}', [ScheduleController::class, 'deleteScheduleTemplate']);
    // Teams CRUD
    Route::get('/settings/teams', [TeamController::class, 'listTeams']);
    Route::post('/settings/teams', [TeamController::class, 'createTeam']);
    Route::put('/settings/teams/{id}', [TeamController::class, 'updateTeam']);
    Route::delete('/settings/teams/{id}', [TeamController::class, 'deleteTeam']);

    // attendance control
    Route::post('/attendance/start', [AttendanceController::class, 'startAttendance']);
    Route::post('/attendance/pause', [AttendanceController::class, 'pauseAttendance']);
    Route::post('/attendance/resume', [AttendanceController::class, 'resumeAttendance']);
    Route::get('/attendance/status', [AttendanceController::class, 'attendanceStatus']);
    Route::get('/attendance/current', [AttendanceController::class, 'currentAttendance']);
    Route::post('/attendance/stop', [AttendanceController::class, 'stopAttendance']);
    Route::get('/attendance/info', [AttendanceController::class, 'attendanceInfo']);
    Route::get('/attendance/day', [AttendanceController::class, 'attendanceDay']);
    Route::get('/attendance/month', [AttendanceController::class, 'attendanceMonth']);
    Route::get('/attendance/who-is-in', [AttendanceController::class, 'attendanceWhoIsIn']);
    Route::post('/attendance/requests', [AttendanceController::class, 'requestAttendanceAdjustment']);

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
    Route::post('/announcements/{id}/read', [AnnouncementController::class, 'markRead']);
    Route::get('/documents/summary', [DashboardController::class, 'documentsSummary']);
    // vacation info for the logged-in user
    Route::get('/vacations/me', [DashboardController::class, 'vacationsMe']);
    // mark notification read
    // legacy mark-notification-read (kept for compatibility)
    Route::post('/notification/{id}/read', [DashboardController::class, 'markNotificationRead']);

    // chat-style notifications endpoints
    Route::get('/notifications/conversations', [NotificationController::class, 'conversations']);
    Route::get('/notifications/conversation/{userId}', [NotificationController::class, 'conversation']);
    Route::post('/notifications/send', [NotificationController::class, 'send']);
    Route::get('/notifications/users', [NotificationController::class, 'users']);

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

    // ---- super admin panel (platform owner only, no tenant filter) ----
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureSuperAdmin::class, 'throttle:api'])
        ->prefix('admin')
        ->group(function () {
            Route::get('/dashboard', [AdminDashboard::class, 'index']);

            Route::get('/companies', [AdminCompanies::class, 'index']);
            Route::post('/companies', [AdminCompanies::class, 'store']);
            Route::get('/companies/{id}', [AdminCompanies::class, 'show']);
            Route::put('/companies/{id}', [AdminCompanies::class, 'update']);
            Route::post('/companies/{id}/suspend', [AdminCompanies::class, 'suspend']);
            Route::post('/companies/{id}/reactivate', [AdminCompanies::class, 'reactivate']);
            Route::delete('/companies/{id}', [AdminCompanies::class, 'destroy']);
            Route::post('/companies/{id}/impersonate', [AdminCompanies::class, 'impersonate']);

            Route::get('/users', [AdminUsers::class, 'index']);
            Route::post('/users/{id}/reset-password', [AdminUsers::class, 'resetPassword']);
            Route::post('/users/{id}/disable', [AdminUsers::class, 'disable']);
            Route::post('/users/{id}/enable', [AdminUsers::class, 'enable']);

            Route::get('/errors', [AdminErrors::class, 'index']);
            Route::get('/errors/{id}', [AdminErrors::class, 'show']);
            Route::post('/errors/{id}/resolve', [AdminErrors::class, 'resolve']);
            Route::post('/errors/{id}/reopen', [AdminErrors::class, 'reopen']);

            Route::get('/audit', [AdminAudit::class, 'index']);
        });
});

// health endpoint (public) used by external checks
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// simple OPTIONS catch-all for CORS preflight (dev convenience)
// also ensure our Cors middleware runs so we don't rely solely on this
// hard-coded header list.
Route::options('{any}', function (Request $request) {
    $response = response('', 200)->header('Vary', 'Origin');
    $origin = \App\Http\Middleware\Cors::resolveOrigin($request);
    if ($origin !== null) {
        $response->header('Access-Control-Allow-Origin', $origin);
    }

    return $response
        ->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With')
        ->header('Access-Control-Max-Age', '600');
})->where('any', '.*')->middleware(\App\Http\Middleware\Cors::class);
