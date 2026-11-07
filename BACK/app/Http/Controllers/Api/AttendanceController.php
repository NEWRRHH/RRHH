<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\TimeOffRequestUpdated;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\ScheduleService;
use App\Support\WorkTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Clock-in / clock-out (fichajes) and attendance reports.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private ScheduleService $schedules,
        private AttendanceService $attendance,
    ) {
    }

    /**
     * Start a new attendance session for the authenticated user.
     * This is called manually (e.g. from a header button) instead of on login.
     */
    public function startAttendance(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $now = Carbon::now();
        $today = $now->toDateString();

        $alreadyClosedToday = Attendance::table()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->exists();

        if ($alreadyClosedToday) {
            return response()->json(['message' => 'Ya registraste entrada y salida hoy.'], 422);
        }

        $effectiveSchedule = $this->schedules->loadEffectiveScheduleForUser((int) $user->id);
        if ($effectiveSchedule && !empty($effectiveSchedule->start_time)) {
            $weekMap = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
            $dayLetter = $weekMap[$now->dayOfWeekIso] ?? 'L';
            $scheduleDays = $this->schedules->normalizeScheduleDays($effectiveSchedule->days ?? null);

            if (in_array($dayLetter, $scheduleDays, true)) {
                $scheduleStart = substr((string) $effectiveSchedule->start_time, 0, 5);
                $allowedFrom = Carbon::parse($today . ' ' . $scheduleStart)->subMinutes(15);
                if ($now->lt($allowedFrom)) {
                    return response()->json([
                        'message' => 'Solo puedes fichar entrada hasta 15 minutos antes de tu horario.',
                        'allowed_from' => $allowedFrom->format('H:i'),
                    ], 422);
                }
            }
        }

        // use the hash of the current bearer token as session token
        $token = $this->attendance->sessionTokenHash($request->bearerToken()) ?: $user->session_token;
        if (! $token) {
            // should not happen if the user is authenticated via Sanctum
            $token = hash('sha256', Str::random(40));
            $user->session_token = $token;
            $user->save();
        }

        // update or create an attendance record
        $open = Attendance::table()
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($open) {
            Attendance::table()->where('id', $open->id)->update([
                'session_token' => $token,
                'updated_at' => now(),
            ]);
        } else {
            Attendance::table()->insert([
                'user_id' => $user->id,
                'session_token' => $token,
                'date' => $now->format('Y/m/d'),
                'start_time' => $now->toTimeString(),
                'pause_time' => null,
                'resume_time' => null,
                'status' => 'en_trabajo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Check whether the current user already has an open attendance.
     */
    public function attendanceStatus(Request $request)
    {
        $user = $request->user();
        $working = false;
        if ($user) {
            $working = Attendance::table()
                ->where('user_id', $user->id)
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->exists();
        }
        return response()->json(['working' => $working]);
    }

    /**
     * Return the current open attendance record (if any).
     */
    public function currentAttendance(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(null);
        }
        $att = Attendance::table()
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();
        return response()->json($att);
    }

    /**
     * Stop the current attendance session without logging out.
     */
    public function stopAttendance(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $now = Carbon::now();
        // fetch the most recent open attendance for the user regardless of session token
        $att = Attendance::table()
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();
        if ($att) {
            // debug before computing
            \Log::info('stopAttendance debug', [
                'att_start' => $att->date . ' ' . $att->start_time,
                'now' => $now->toDateTimeString(),
                'app_tz' => config('app.timezone'),
                'server_tz' => date_default_timezone_get(),
            ]);
            // discount pause span from worked time
            $diffSeconds = $this->attendance->workedSecondsWithPause($att, $now);
            $hours = gmdate('H:i:s', $diffSeconds);
            Attendance::table()
                ->where('id', $att->id)
                ->update([
                    'end_time' => $now->toTimeString(),
                    'end_date' => $now->format('Y/m/d'),
                    'hours_worked' => $hours,
                    'status' => 'fuera_trabajo',
                    'updated_at' => now(),
                ]);
        } else {
            // no open attendance found: maybe token changed or already closed
        }
        return response()->json(['status' => 'stopped']);
    }

    /**
     * Save pause time (HH:MM:SS) for the current open attendance.
     */
    public function pauseAttendance(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $att = Attendance::table()
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $att) {
            return response()->json(['message' => 'No hay jornada activa'], 422);
        }

        $now = Carbon::now()->toTimeString();
        Attendance::table()
            ->where('id', $att->id)
            ->update([
                'pause_time' => $now,
                'updated_at' => now(),
            ]);

        return response()->json(['status' => 'paused', 'pause_time' => $now]);
    }

    /**
     * Save resume time (HH:MM:SS) for the current open attendance.
     */
    public function resumeAttendance(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $att = Attendance::table()
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $att) {
            return response()->json(['message' => 'No hay jornada activa'], 422);
        }

        $now = Carbon::now()->toTimeString();
        Attendance::table()
            ->where('id', $att->id)
            ->update([
                'resume_time' => $now,
                'updated_at' => now(),
            ]);

        return response()->json(['status' => 'resumed', 'resume_time' => $now]);
    }

    /**
     * Provide attendance + schedule info for the current user.
     */
    public function attendanceInfo(Request $request)
    {
        $user = $request->user();
        $info = [
            'working' => false,
            'attendance' => null,
            'schedule' => null,
            'schedule_template_ids' => [],
            'assigned_schedule_templates' => [],
        ];
        if ($user) {
            $info['working'] = Attendance::table()
                ->where('user_id', $user->id)
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->exists();
            $info['attendance'] = Attendance::table()
                ->where('user_id', $user->id)
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->orderBy('created_at', 'desc')
                ->first();

            $sched = $this->schedules->loadEffectiveScheduleForUser((int) $user->id);
            if ($sched) {
                $sched->days = $this->schedules->normalizeScheduleDays($sched->days ?? null);
            }
            $info['schedule'] = $sched;
            $info['schedule_template_ids'] = $this->schedules->getAssignedScheduleTemplateIds((int) $user->id);
            $info['assigned_schedule_templates'] = $this->schedules->loadAssignedScheduleTemplatesForUser((int) $user->id);
        }
        return response()->json($info);
    }

    /**
     * Daily attendance control view data.
     * team_id 1 or 2 can inspect all users; others only their own records.
     */
    public function attendanceDay(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $isPrivileged = $user->hasPermission('attendance.view_all');

        $requestedDate = $request->query('date');
        try {
            $date = $requestedDate ? Carbon::parse($requestedDate)->toDateString() : Carbon::today()->toDateString();
        } catch (\Exception $e) {
            $date = Carbon::today()->toDateString();
        }

        $teamFilter = $request->query('team_id');
        $selectedUserId = (int) ($request->query('user_id') ?: $user->id);
        if (! $isPrivileged) {
            $selectedUserId = (int) $user->id;
        }

        $usersQuery = User::table()
            ->leftJoin('teams', 'users.team_id', '=', 'teams.id')
            ->whereNull('users.deleted_at')
            ->select(
                'users.id',
                'users.team_id',
                'users.first_name',
                'users.last_name',
                'users.name',
                'users.photo',
                'users.profile_photo_path',
                'teams.name as team_name'
            )
            ->orderByRaw("COALESCE(users.first_name, users.name, '') asc");

        if ($isPrivileged) {
            if ($teamFilter !== null && $teamFilter !== '') {
                $usersQuery->where('users.team_id', (int) $teamFilter);
            }
        } else {
            $usersQuery->where('users.id', $user->id);
        }

        $users = $usersQuery->get()->map(function ($u) {
            $fullName = trim(($u->first_name ?? $u->name ?? '') . ' ' . ($u->last_name ?? ''));
            return [
                'id' => $u->id,
                'team_id' => $u->team_id,
                'team_name' => $u->team_name ?? 'Sin equipo',
                'full_name' => $fullName !== '' ? $fullName : ('Usuario #' . $u->id),
                'photo' => $u->photo ?? $u->profile_photo_path ?? null,
            ];
        })->values();

        if ($users->where('id', $selectedUserId)->isEmpty()) {
            $selectedUserId = (int) ($users->first()['id'] ?? $user->id);
        }

        $attendances = Attendance::table()
            ->where('user_id', $selectedUserId)
            ->whereDate('date', $date)
            ->orderBy('start_time', 'asc')
            ->get([
                'id',
                'date',
                'start_time',
                'pause_time',
                'resume_time',
                'end_time',
                'hours_worked',
                'status',
            ]);

        $teams = Team::table()
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'can_view_all' => $isPrivileged,
            'date' => $date,
            'team_filter' => $teamFilter !== null && $teamFilter !== '' ? (int) $teamFilter : null,
            'selected_user_id' => $selectedUserId,
            'users' => $users,
            'teams' => $teams,
            'attendances' => $attendances,
        ]);
    }

    /**
     * Monthly attendance control data (one row per day in month).
     * team_id 1 or 2 can inspect all users; others only their own records.
     */
    public function attendanceMonth(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $isPrivileged = $user->hasPermission('attendance.view_all');

        $monthInput = (string) ($request->query('month') ?: Carbon::today()->format('Y-m'));
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Exception $e) {
            $monthDate = Carbon::today()->startOfMonth();
        }

        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();
        $includeNonWorking = (bool) ((int) $request->query('include_non_working', 0));

        $teamFilter = $request->query('team_id');
        $selectedUserId = (int) ($request->query('user_id') ?: $user->id);
        if (! $isPrivileged) {
            $selectedUserId = (int) $user->id;
        }

        $usersQuery = User::table()
            ->leftJoin('teams', 'users.team_id', '=', 'teams.id')
            ->whereNull('users.deleted_at')
            ->select(
                'users.id',
                'users.team_id',
                'users.first_name',
                'users.last_name',
                'users.name',
                'users.photo',
                'users.profile_photo_path',
                'teams.name as team_name'
            )
            ->orderByRaw("COALESCE(users.first_name, users.name, '') asc");

        if ($isPrivileged) {
            if ($teamFilter !== null && $teamFilter !== '') {
                $usersQuery->where('users.team_id', (int) $teamFilter);
            }
        } else {
            $usersQuery->where('users.id', $user->id);
        }

        $users = $usersQuery->get()->map(function ($u) {
            $fullName = trim(($u->first_name ?? $u->name ?? '') . ' ' . ($u->last_name ?? ''));
            return [
                'id' => $u->id,
                'team_id' => $u->team_id,
                'team_name' => $u->team_name ?? 'Sin equipo',
                'full_name' => $fullName !== '' ? $fullName : ('Usuario #' . $u->id),
                'photo' => $u->photo ?? $u->profile_photo_path ?? null,
            ];
        })->values();

        if ($users->where('id', $selectedUserId)->isEmpty()) {
            $selectedUserId = (int) ($users->first()['id'] ?? $user->id);
        }

        $schedule = $this->schedules->loadEffectiveScheduleForUser((int) $selectedUserId);
        $scheduleDays = $this->schedules->normalizeScheduleDays($schedule->days ?? null);
        $dailyTargetMinutes = $this->schedules->scheduleDailyMinutes($schedule);

        $rawRows = Attendance::table()
            ->where('user_id', $selectedUserId)
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->orderBy('date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get([
                'id',
                'date',
                'start_time',
                'pause_time',
                'resume_time',
                'end_time',
                'hours_worked',
                'status',
                'created_at',
            ]);

        $rawEvents = Event::table()
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.user_id', $selectedUserId)
            ->whereNull('events.deleted_at')
            ->where(function ($q) {
                $q->whereNull('events.approval_status')
                    ->orWhere('events.approval_status', 'approved');
            })
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('events.start_at', [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()])
                    ->orWhereBetween('events.end_at', [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                        $q2->where('events.start_at', '<=', $monthStart->toDateTimeString())
                            ->where(function ($q3) use ($monthEnd) {
                                $q3->where('events.end_at', '>=', $monthEnd->toDateTimeString())
                                    ->orWhereNull('events.end_at');
                            });
                    });
            })
            ->orderBy('events.start_at', 'asc')
            ->get([
                'events.id',
                'events.title',
                'events.start_at',
                'events.end_at',
                'events.all_day',
                'events.color',
                'event_types.name as event_type_name',
            ]);

        $attendanceRequestTypeId = $this->attendance->attendanceRequestEventTypeId();
        $pendingAttendanceRequests = Event::table()
            ->where('user_id', $selectedUserId)
            ->where('event_type_id', $attendanceRequestTypeId)
            ->whereNull('deleted_at')
            ->where('approval_status', 'pending')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_at', [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()])
                    ->orWhereBetween('end_at', [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                        $q2->where('start_at', '<=', $monthStart->toDateTimeString())
                            ->where(function ($q3) use ($monthEnd) {
                                $q3->where('end_at', '>=', $monthEnd->toDateTimeString())
                                    ->orWhereNull('end_at');
                            });
                    });
            })
            ->orderBy('created_at', 'desc')
            ->get(['id', 'start_at', 'end_at']);

        $pendingAttendanceRequestsByDate = [];
        foreach ($pendingAttendanceRequests as $req) {
            $reqStart = Carbon::parse($req->start_at)->startOfDay();
            $reqEnd = $req->end_at ? Carbon::parse($req->end_at)->startOfDay() : $reqStart->copy();
            $cursorReq = $reqStart->copy();
            while ($cursorReq->lte($reqEnd)) {
                $dateKey = $cursorReq->toDateString();
                if (!isset($pendingAttendanceRequestsByDate[$dateKey])) {
                    $pendingAttendanceRequestsByDate[$dateKey] = (int) $req->id;
                }
                $cursorReq->addDay();
            }
        }

        $eventsByDate = [];
        $vacationsByDate = [];
        foreach ($rawEvents as $event) {
            $eventStart = Carbon::parse($event->start_at);
            $eventEnd = $event->end_at ? Carbon::parse($event->end_at) : $eventStart->copy();
            $cursorDate = $eventStart->copy()->startOfDay();
            $lastDate = $eventEnd->copy()->startOfDay();
            $eventTypeName = trim((string) ($event->event_type_name ?? ''));
            $isVacation = Str::contains(Str::lower($eventTypeName), 'vacacion');

            while ($cursorDate->lte($lastDate)) {
                $dateKey = $cursorDate->toDateString();
                $isFirstDay = $dateKey === $eventStart->toDateString();
                $isLastDay = $dateKey === $eventEnd->toDateString();
                $dayStartTime = $isFirstDay ? $eventStart->format('H:i:s') : '00:00:00';
                $dayEndTime = $isLastDay ? $eventEnd->format('H:i:s') : '23:59:59';
                if (!isset($eventsByDate[$dateKey])) {
                    $eventsByDate[$dateKey] = [];
                }
                $eventsByDate[$dateKey][] = [
                    'id' => $event->id,
                    'title' => $event->title,
                    'event_type_name' => $event->event_type_name,
                    'color' => $event->color,
                    'all_day' => (bool) $event->all_day,
                    'start_time' => substr($dayStartTime, 0, 5),
                    'end_time' => substr($dayEndTime, 0, 5),
                ];
                if ($isVacation) {
                    $vacationsByDate[$dateKey] = true;
                }
                $cursorDate->addDay();
            }
        }

        $receiptDocsByDate = Document::table()
            ->where('user_id', $selectedUserId)
            ->whereNull('deleted_at')
            ->where('category', 'receipt')
            ->whereDate('created_at', '>=', $monthStart->toDateString())
            ->whereDate('created_at', '<=', $monthEnd->toDateString())
            ->selectRaw('DATE(created_at) as doc_date')
            ->pluck('doc_date')
            ->filter()
            ->map(function ($d) {
                return (string) $d;
            })
            ->flip()
            ->toArray();

        // One record per date: keep the latest row for that day.
        $rowsByDate = [];
        foreach ($rawRows as $r) {
            if (!isset($rowsByDate[$r->date])) {
                $rowsByDate[$r->date] = $r;
            }
        }

        $rows = [];
        $absences = [];
        $targetMinutes = 0;
        $workedMinutes = 0;
        $todayKey = Carbon::today()->toDateString();
        $weekMap = [
            1 => 'L',
            2 => 'M',
            3 => 'X',
            4 => 'J',
            5 => 'V',
            6 => 'S',
            7 => 'D',
        ];
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $key = $cursor->toDateString();
            $att = $rowsByDate[$key] ?? null;
            $dayLetter = $weekMap[$cursor->dayOfWeekIso] ?? 'L';
            // a company holiday on a scheduled day is not a working day (no target, no absence)
            $isScheduledDay = in_array($dayLetter, $scheduleDays, true);
            $holidayName = $isScheduledDay ? $this->schedules->holidayName($cursor) : null;
            $isWorkingDay = $isScheduledDay && $holidayName === null;

            if ($isWorkingDay) {
                $targetMinutes += $dailyTargetMinutes;
            }

            $rowWorked = 0;
            if ($att && !empty($att->hours_worked)) {
                $rowWorked = WorkTime::timeStringToMinutes((string) $att->hours_worked);
            }
            $workedMinutes += $rowWorked;

            $hasAttendance = $att
                && (
                    !empty($att->start_time)
                    || !empty($att->end_time)
                    || !empty($att->hours_worked)
                );
            $hasVacation = isset($vacationsByDate[$key]);
            $hasReceipt = isset($receiptDocsByDate[$key]);
            $isPastOrToday = $key <= $todayKey;

            if ($isWorkingDay && $isPastOrToday && ! $hasAttendance && ! $hasVacation && ! $hasReceipt) {
                $absences[] = [
                    'date' => $key,
                    'weekday' => $dayLetter,
                    'reason' => 'sin_fichaje',
                ];
            }

            // Default table shows only configured working days (e.g. L-V).
            // Optionally include non-working days when requested by UI switch.
            if ($isScheduledDay || $includeNonWorking) {
                $rows[] = [
                    'date' => $key,
                    'weekday' => $dayLetter,
                    'start_time' => $att->start_time ?? null,
                    'pause_time' => $att->pause_time ?? null,
                    'resume_time' => $att->resume_time ?? null,
                    'end_time' => $att->end_time ?? null,
                    'hours_worked' => $att->hours_worked ?? null,
                    'status' => $att->status ?? null,
                    'is_working_day' => $isWorkingDay,
                    'holiday' => $holidayName,
                    'events' => $eventsByDate[$key] ?? [],
                    'attendance_request_pending' => isset($pendingAttendanceRequestsByDate[$key]),
                    'attendance_request_id' => $pendingAttendanceRequestsByDate[$key] ?? null,
                ];
            }
            $cursor->addDay();
        }

        $requestRows = Event::table()
            ->where('user_id', $selectedUserId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_at', [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()])
                    ->orWhereBetween('end_at', [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                        $q2->where('start_at', '<=', $monthStart->toDateTimeString())
                            ->where(function ($q3) use ($monthEnd) {
                                $q3->where('end_at', '>=', $monthEnd->toDateTimeString())
                                    ->orWhereNull('end_at');
                            });
                    });
            })
            ->get(['approval_status']);

        $requestsTotal = 0;
        $requestsPending = 0;
        $requestsApproved = 0;
        $requestsRejected = 0;
        foreach ($requestRows as $rq) {
            $requestsTotal++;
            $st = strtolower(trim((string) ($rq->approval_status ?? 'approved')));
            if ($st === 'pending') $requestsPending++;
            elseif ($st === 'rejected') $requestsRejected++;
            else $requestsApproved++;
        }

        $teams = Team::table()
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'can_view_all' => $isPrivileged,
            'month' => $monthDate->format('Y-m'),
            'team_filter' => $teamFilter !== null && $teamFilter !== '' ? (int) $teamFilter : null,
            'selected_user_id' => $selectedUserId,
            'users' => $users,
            'teams' => $teams,
            'schedule' => [
                'start_time' => $schedule->start_time ?? null,
                'end_time' => $schedule->end_time ?? null,
                'days' => $scheduleDays,
                'daily_target_minutes' => $dailyTargetMinutes,
            ],
            'summary' => [
                'worked_minutes' => $workedMinutes,
                'worked_hhmm' => WorkTime::formatMinutesAsHHMM($workedMinutes),
                'target_minutes' => $targetMinutes,
                'target_hhmm' => WorkTime::formatMinutesAsHHMM($targetMinutes),
                'requests_total' => $requestsTotal,
                'requests_pending' => $requestsPending,
                'requests_approved' => $requestsApproved,
                'requests_rejected' => $requestsRejected,
            ],
            'include_non_working' => $includeNonWorking,
            'rows' => $rows,
            'absences' => [
                'count' => count($absences),
                'rows' => $absences,
            ],
        ]);
    }

    /**
     * "Who's in" view data for attendance page.
     * Returns users currently working with check-in time and live worked hours.
     */
    public function attendanceWhoIsIn(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $isPrivileged = $user->hasPermission('attendance.view_all');

        $today = Carbon::today()->toDateString();
        $teamFilter = $request->query('team_id');

        $query = Attendance::table()
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->leftJoin('teams', 'users.team_id', '=', 'teams.id')
            ->whereDate('attendances.date', $today)
            ->where('attendances.status', 'en_trabajo')
            ->whereNull('attendances.end_date')
            ->whereNull('users.deleted_at')
            ->select(
                'attendances.id',
                'attendances.user_id',
                'attendances.date',
                'attendances.start_time',
                'attendances.pause_time',
                'attendances.resume_time',
                'attendances.created_at',
                'users.team_id',
                'users.first_name',
                'users.last_name',
                'users.name',
                'users.photo',
                'users.profile_photo_path',
                'teams.name as team_name'
            )
            ->orderBy('attendances.start_time', 'asc');

        if ($isPrivileged) {
            if ($teamFilter !== null && $teamFilter !== '') {
                $query->where('users.team_id', (int) $teamFilter);
            }
        } else {
            $query->where('attendances.user_id', $user->id);
        }

        $now = Carbon::now();
        $rows = $query->get()->map(function ($r) use ($now) {
            $fullName = trim(($r->first_name ?? $r->name ?? '') . ' ' . ($r->last_name ?? ''));
            $workedSeconds = $this->attendance->workedSecondsWithPause($r, $now);
            return [
                'attendance_id' => $r->id,
                'user_id' => $r->user_id,
                'full_name' => $fullName !== '' ? $fullName : ('Usuario #' . $r->user_id),
                'team_id' => $r->team_id,
                'team_name' => $r->team_name ?? 'Sin equipo',
                'photo' => $r->photo ?? $r->profile_photo_path ?? null,
                'start_time' => $r->start_time ? substr((string) $r->start_time, 0, 5) : null,
                'worked_seconds' => $workedSeconds,
                'worked_hhmm' => WorkTime::formatSecondsAsHHMM($workedSeconds),
            ];
        })->values();

        $teams = Team::table()
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'date' => $today,
            'can_view_all' => $isPrivileged,
            'team_filter' => $teamFilter !== null && $teamFilter !== '' ? (int) $teamFilter : null,
            'working_count' => $rows->count(),
            'rows' => $rows,
            'teams' => $teams,
        ]);
    }

    public function requestAttendanceAdjustment(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'entry_time' => 'nullable|date_format:H:i',
            'exit_time' => 'nullable|date_format:H:i',
        ]);

        $entryTime = !empty($data['entry_time']) ? $data['entry_time'] : null;
        $exitTime = !empty($data['exit_time']) ? $data['exit_time'] : null;
        if ($entryTime && $exitTime && strcmp($exitTime, $entryTime) <= 0) {
            return response()->json(['message' => 'La hora de salida debe ser posterior a la hora de entrada'], 422);
        }

        $targetDate = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $today = Carbon::today();
        if ($targetDate->gt($today)) {
            return response()->json(['message' => 'Solo puedes solicitar fichajes de hoy o dias pasados'], 422);
        }

        $attendance = Attendance::table()
            ->where('user_id', $user->id)
            ->whereDate('date', $targetDate->toDateString())
            ->orderBy('created_at', 'desc')
            ->first(['id', 'start_time', 'end_time']);

        $missingEntry = false;
        $missingExit = false;
        if (! $attendance) {
            $missingEntry = true;
            $missingExit = true;
        } else {
            $missingEntry = empty($attendance->start_time);
            $missingExit = empty($attendance->end_time);
        }

        if (! $missingEntry && ! $missingExit) {
            return response()->json(['message' => 'Ese dia ya tiene entrada y salida registradas'], 422);
        }

        $requestKind = $missingEntry && $missingExit
            ? 'entry_exit'
            : ($missingEntry ? 'entry' : 'exit');

        $eventTypeId = $this->attendance->attendanceRequestEventTypeId();
        $pendingExists = Event::table()
            ->where('user_id', $user->id)
            ->where('event_type_id', $eventTypeId)
            ->whereNull('deleted_at')
            ->where('approval_status', 'pending')
            ->whereDate('start_at', $targetDate->toDateString())
            ->exists();

        if ($pendingExists) {
            return response()->json(['message' => 'Ya existe una solicitud pendiente para ese dia'], 422);
        }

        $schedule = $this->schedules->loadEffectiveScheduleForUser((int) $user->id);

        $scheduleStart = isset($schedule->start_time) ? substr((string) $schedule->start_time, 0, 5) : '09:00';
        $scheduleEnd = isset($schedule->end_time) ? substr((string) $schedule->end_time, 0, 5) : '18:00';

        $startAt = $targetDate->copy();
        $endAt = $targetDate->copy();
        if ($requestKind === 'entry_exit') {
            $entrySource = $entryTime ?: $scheduleStart;
            $exitSource = $exitTime ?: $scheduleEnd;
        } elseif ($requestKind === 'exit') {
            $existingStart = !empty($attendance->start_time) ? substr((string) $attendance->start_time, 0, 5) : $scheduleStart;
            $entrySource = $existingStart;
            $exitSource = $exitTime ?: $scheduleEnd;
        } else {
            $existingEnd = !empty($attendance->end_time) ? substr((string) $attendance->end_time, 0, 5) : $scheduleEnd;
            $entrySource = $entryTime ?: $scheduleStart;
            $exitSource = $existingEnd;
        }

        [$sh, $sm] = array_map('intval', explode(':', $entrySource));
        [$eh, $em] = array_map('intval', explode(':', $exitSource));
        $startAt->setTime($sh, $sm, 0);
        $endAt->setTime($eh, $em, 0);

        if (empty($exitTime) && $endAt->lte($startAt)) {
            $endAt = $startAt->copy()->addHours(8);
        }

        $titleByKind = [
            'entry_exit' => 'Solicitud de fichaje: entrada y salida faltantes',
            'entry' => 'Solicitud de fichaje: entrada faltante',
            'exit' => 'Solicitud de fichaje: salida faltante',
        ];

        $description = sprintf(
            'Regularizacion de fichaje del %s.',
            $targetDate->format('d/m/Y')
        );

        $requestedTimes = array_filter(['entrada' => $entryTime, 'salida' => $exitTime]);
        if ($requestedTimes) {
            $timeParts = [];
            foreach ($requestedTimes as $timeLabel => $timeValue) {
                $timeParts[] = $timeLabel . ' ' . $timeValue;
            }
            $description .= ' Horas solicitadas: ' . implode(', ', $timeParts) . '.';
        }

        $eventId = Event::table()->insertGetId([
            'title' => $titleByKind[$requestKind] ?? 'Solicitud de fichaje',
            'description' => $description,
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $endAt->toDateTimeString(),
            'user_id' => $user->id,
            'event_type_id' => $eventTypeId,
            'all_day' => false,
            'color' => '#F59E0B',
            'approval_status' => 'pending',
            'approval_comment' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        event(new TimeOffRequestUpdated((int) $eventId, (int) $user->id, 'created'));

        return response()->json([
            'status' => 'ok',
            'request_id' => (int) $eventId,
            'request_kind' => $requestKind,
            'date' => $targetDate->toDateString(),
            'entry_time' => $entryTime,
            'exit_time' => $exitTime,
        ]);
    }
}
