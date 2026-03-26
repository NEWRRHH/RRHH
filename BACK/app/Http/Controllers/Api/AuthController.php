<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Compute worked seconds for an attendance row, discounting pause span.
     * With the current schema we store one pause/resume pair (latest values).
     */
    private function workedSecondsWithPause(object $att, Carbon $end): int
    {
        try {
            $start = Carbon::parse($att->date . ' ' . $att->start_time);
        } catch (\Exception $e) {
            $start = Carbon::parse($att->start_time);
        }

        $total = max(0, $start->diffInSeconds($end, false));
        $paused = 0;

        if (!empty($att->pause_time)) {
            try {
                $pauseStart = Carbon::parse($att->date . ' ' . $att->pause_time);
            } catch (\Exception $e) {
                $pauseStart = Carbon::parse($att->pause_time);
            }

            // If resume is missing or older than pause, treat as still paused until end.
            $pauseEnd = $end->copy();
            if (!empty($att->resume_time)) {
                try {
                    $resume = Carbon::parse($att->date . ' ' . $att->resume_time);
                } catch (\Exception $e) {
                    $resume = Carbon::parse($att->resume_time);
                }
                if ($resume->greaterThan($pauseStart)) {
                    $pauseEnd = $resume->lessThan($end) ? $resume : $end->copy();
                }
            }

            if ($pauseEnd->greaterThan($pauseStart)) {
                $paused = $pauseStart->diffInSeconds($pauseEnd);
            }
        }

        return max(0, $total - $paused);
    }

    private function normalizeScheduleDays($days): array
    {
        // Default: Monday to Friday if schedule has no configured days.
        $default = ['L', 'M', 'X', 'J', 'V'];
        if (empty($days)) return $default;

        $decoded = $days;
        if (is_string($days)) {
            $decoded = json_decode($days, true);
        }
        if (!is_array($decoded)) return $default;

        $allowed = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
        $normalized = array_values(array_intersect($allowed, array_map('strtoupper', $decoded)));
        return count($normalized) ? $normalized : $default;
    }

    private function scheduleDailyMinutes(?object $schedule): int
    {
        if (!$schedule || empty($schedule->start_time) || empty($schedule->end_time)) return 0;
        try {
            $start = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time);
        } catch (\Exception $e) {
            return 0;
        }

        $mins = $start->diffInMinutes($end, false);
        if ($mins < 0) {
            $mins += 24 * 60;
        }
        // Standard office shift usually includes 1h break (e.g. 09:00-18:00 => 8h target).
        // Apply this only on long shifts to avoid affecting short/part-time schedules.
        if ($mins >= 6 * 60) {
            $mins -= 60;
        }
        return max(0, (int) $mins);
    }

    private function timeStringToMinutes(?string $time): int
    {
        if (!$time) return 0;
        $parts = explode(':', $time);
        if (count($parts) < 2) return 0;
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return max(0, $h * 60 + $m);
    }

    private function formatMinutesAsHHMM(int $minutes): string
    {
        $m = max(0, $minutes);
        $h = intdiv($m, 60);
        $mm = $m % 60;
        return sprintf('%02d:%02d', $h, $mm);
    }

    private function formatSecondsAsHHMM(int $seconds): string
    {
        $s = max(0, $seconds);
        $h = intdiv($s, 3600);
        $m = intdiv(($s % 3600), 60);
        return sprintf('%02d:%02d', $h, $m);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required','confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        // record session token on user (attendance will be started manually via API)
        $user->session_token = $token;
        $user->save();

        return response()->json(['user' => $user, 'token' => $token], 201)
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function login(Request $request)
    {
        // force JSON response format (protect against missing Accept header from client)
        $request->headers->set('Accept', 'application/json');

        // debugging: log raw body and headers
        logger('login raw content', ['content' => $request->getContent(), 'headers' => $request->headers->all()]);

        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // record session token on user (attendance start is manual now)
        $user->session_token = $token;
        $user->save();

        return response()->json(['user' => $user, 'token' => $token])
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user && $request->bearerToken()) {
            // close attendance if exists for this session
            $token = $request->bearerToken();
            $now = Carbon::now();
            $att = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('session_token', $token)
                ->where('status', 'en_trabajo')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($att) {
                $diffSeconds = $this->workedSecondsWithPause($att, $now);
                $hours = gmdate('H:i:s', $diffSeconds);
                DB::table('attendances')
                    ->where('id', $att->id)
                    ->update([
                        'end_time' => $now->toTimeString(),
                        'end_date' => $now->format('Y/m/d'),
                        'hours_worked' => $hours,
                        'status' => 'fuera_trabajo',
                        'updated_at' => now(),
                    ]);
            }

            $request->user()->currentAccessToken()->delete();
            // clear stored session token
            $user->session_token = null;
            $user->save();
        }

        return response()->json(['message' => 'Logged out'])
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function user(Request $request)
    {
        return response()->json($request->user())
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Update authenticated user's profile information.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'photo' => 'nullable|image|max:2048',
            'birth_date' => 'nullable|date',
            'dni' => 'nullable|string|max:30',
            'social_security_number' => 'nullable|string|max:50',
            'contract_type' => 'nullable|string|max:50',
            'contract_start_date' => 'nullable|date',
        ]);
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->birth_date = $data['birth_date'] ?? $user->birth_date;
        $user->dni = $data['dni'] ?? $user->dni;
        $user->social_security_number = $data['social_security_number'] ?? $user->social_security_number;
        $user->contract_type = $data['contract_type'] ?? $user->contract_type;
        $user->contract_start_date = $data['contract_start_date'] ?? $user->contract_start_date;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $user->photo = '/storage/' . $path;
        }
        $user->save();
        return response()->json($user)
            ->header('Access-Control-Allow-Origin', '*');
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

        // use the current bearer token as session token
        $token = $request->bearerToken() ?: $user->session_token;
        if (! $token) {
            // should not happen if the user is authenticated via Sanctum
            $token = hash('sha256', Str::random(40));
            $user->session_token = $token;
            $user->save();
        }

        // update or create an attendance record
        $open = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($open) {
            DB::table('attendances')->where('id', $open->id)->update([
                'session_token' => $token,
                'updated_at' => now(),
            ]);
        } else {
            $now = Carbon::now();
            DB::table('attendances')->insert([
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
            $working = DB::table('attendances')
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
        $att = DB::table('attendances')
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
        $att = DB::table('attendances')
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
            $diffSeconds = $this->workedSecondsWithPause($att, $now);
            $hours = gmdate('H:i:s', $diffSeconds);
            DB::table('attendances')
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

        $att = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $att) {
            return response()->json(['message' => 'No hay jornada activa'], 422);
        }

        $now = Carbon::now()->toTimeString();
        DB::table('attendances')
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

        $att = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $att) {
            return response()->json(['message' => 'No hay jornada activa'], 422);
        }

        $now = Carbon::now()->toTimeString();
        DB::table('attendances')
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
        $info = ['working' => false, 'attendance' => null, 'schedule' => null];
        if ($user) {
            $info['working'] = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->exists();
            $info['attendance'] = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->orderBy('created_at', 'desc')
                ->first();

            // try direct schedule on user or via pivot
            $sched = DB::table('schedules')
                ->where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->first();
            if (! $sched) {
                $sched = DB::table('schedules')
                    ->join('user_schedules', 'schedules.id', '=', 'user_schedules.schedule_id')
                    ->where('user_schedules.user_id', $user->id)
                    ->select('schedules.*')
                    ->orderBy('schedules.id', 'desc')
                    ->first();
            }
            if ($sched) {
                $sched->days = $this->normalizeScheduleDays($sched->days ?? null);
            }
            $info['schedule'] = $sched;
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

        $isPrivileged = in_array((int) $user->team_id, [1, 2], true);

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

        $usersQuery = DB::table('users')
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

        $attendances = DB::table('attendances')
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

        $teams = DB::table('teams')
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

        $isPrivileged = in_array((int) $user->team_id, [1, 2], true);

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

        $usersQuery = DB::table('users')
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

        $schedule = DB::table('schedules')
            ->where('user_id', $selectedUserId)
            ->orderBy('id', 'desc')
            ->first();
        if (! $schedule) {
            $schedule = DB::table('schedules')
                ->join('user_schedules', 'schedules.id', '=', 'user_schedules.schedule_id')
                ->where('user_schedules.user_id', $selectedUserId)
                ->select('schedules.*')
                ->orderBy('schedules.id', 'desc')
                ->first();
        }
        $scheduleDays = $this->normalizeScheduleDays($schedule->days ?? null);
        $dailyTargetMinutes = $this->scheduleDailyMinutes($schedule);

        $rawRows = DB::table('attendances')
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

        $rawEvents = DB::table('events')
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.user_id', $selectedUserId)
            ->whereNull('events.deleted_at')
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

        $eventsByDate = [];
        foreach ($rawEvents as $event) {
            $eventStart = Carbon::parse($event->start_at);
            $eventEnd = $event->end_at ? Carbon::parse($event->end_at) : $eventStart->copy();
            $cursorDate = $eventStart->copy()->startOfDay();
            $lastDate = $eventEnd->copy()->startOfDay();

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
                $cursorDate->addDay();
            }
        }

        // One record per date: keep the latest row for that day.
        $rowsByDate = [];
        foreach ($rawRows as $r) {
            if (!isset($rowsByDate[$r->date])) {
                $rowsByDate[$r->date] = $r;
            }
        }

        $rows = [];
        $targetMinutes = 0;
        $workedMinutes = 0;
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
            $isWorkingDay = in_array($dayLetter, $scheduleDays, true);

            if ($isWorkingDay) {
                $targetMinutes += $dailyTargetMinutes;
            }

            $rowWorked = 0;
            if ($att && !empty($att->hours_worked)) {
                $rowWorked = $this->timeStringToMinutes((string) $att->hours_worked);
            }
            $workedMinutes += $rowWorked;

            // Default table shows only configured working days (e.g. L-V).
            // Optionally include non-working days when requested by UI switch.
            if ($isWorkingDay || $includeNonWorking) {
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
                    'events' => $eventsByDate[$key] ?? [],
                ];
            }
            $cursor->addDay();
        }

        $teams = DB::table('teams')
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
                'worked_hhmm' => $this->formatMinutesAsHHMM($workedMinutes),
                'target_minutes' => $targetMinutes,
                'target_hhmm' => $this->formatMinutesAsHHMM($targetMinutes),
            ],
            'include_non_working' => $includeNonWorking,
            'rows' => $rows,
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

        $isPrivileged = in_array((int) $user->team_id, [1, 2], true);

        $today = Carbon::today()->toDateString();
        $teamFilter = $request->query('team_id');

        $query = DB::table('attendances')
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
            $workedSeconds = $this->workedSecondsWithPause($r, $now);
            return [
                'attendance_id' => $r->id,
                'user_id' => $r->user_id,
                'full_name' => $fullName !== '' ? $fullName : ('Usuario #' . $r->user_id),
                'team_id' => $r->team_id,
                'team_name' => $r->team_name ?? 'Sin equipo',
                'photo' => $r->photo ?? $r->profile_photo_path ?? null,
                'start_time' => $r->start_time ? substr((string) $r->start_time, 0, 5) : null,
                'worked_seconds' => $workedSeconds,
                'worked_hhmm' => $this->formatSecondsAsHHMM($workedSeconds),
            ];
        })->values();

        $teams = DB::table('teams')
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
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Change password for authenticated user.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => 'required',
            'password' => ['required','confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()],
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password incorrect'], 422);
        }
        $user->password = Hash::make($data['password']);
        $user->save();
        return response()->json(['status'=>'ok']);
    }

    /**
     * Update schedule for authenticated user (admin only).
     */
    public function updateSchedule(Request $request)
    {
        $user = $request->user();
        if ($user->user_type_id !== 1) {
            return response()->json(['message'=>'Forbidden'], 403);
        }
        $data = $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'days' => 'nullable|array',
            'days.*' => 'in:L,M,X,J,V,S,D',
        ]);
        $days = array_values(array_unique($this->normalizeScheduleDays($data['days'] ?? null)));
        // find existing schedule
        $sched = DB::table('schedules')->where('user_id', $user->id)->first();
        if ($sched) {
            DB::table('schedules')->where('id', $sched->id)->update([
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'days' => json_encode($days),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('schedules')->insert([
                'user_id' => $user->id,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'days' => json_encode($days),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['status'=>'ok']);
    }

    /**
     * List all employees (admin only) with team name.
     */
    public function employees(Request $request)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employees = DB::table('users')
            ->leftJoin('teams', 'users.team_id', '=', 'teams.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.photo',
                'users.profile_photo_path',
                'users.team_id',
                'teams.name as team_name'
            )
            ->whereNull('users.deleted_at')
            ->orderBy('users.id', 'asc')
            ->get();

        $teams = DB::table('teams')->select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'employees' => $employees,
            'teams' => $teams,
        ]);
    }

    /**
     * Get one employee with schedule and teams (admin only).
     */
    public function getEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $schedule = DB::table('schedules')
            ->where('user_id', $employee->id)
            ->orderBy('id', 'desc')
            ->first();

        if (! $schedule) {
            $schedule = DB::table('schedules')
                ->join('user_schedules', 'schedules.id', '=', 'user_schedules.schedule_id')
                ->where('user_schedules.user_id', $employee->id)
                ->select('schedules.*')
                ->orderBy('schedules.id', 'desc')
                ->first();
        }
        if ($schedule) {
            $schedule->days = $this->normalizeScheduleDays($schedule->days ?? null);
        }

        $teams = DB::table('teams')->select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'employee' => $employee,
            'schedule' => $schedule,
            'teams' => $teams,
        ]);
    }

    /**
     * Monthly attendance data for one employee (admin only).
     */
    public function employeeAttendanceMonth(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::whereNull('deleted_at')->find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $monthInput = (string) ($request->query('month') ?: Carbon::today()->format('Y-m'));
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Exception $e) {
            $monthDate = Carbon::today()->startOfMonth();
        }

        $includeNonWorking = (bool) ((int) $request->query('include_non_working', 0));
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();

        $schedule = DB::table('schedules')
            ->where('user_id', $employee->id)
            ->orderBy('id', 'desc')
            ->first();
        if (! $schedule) {
            $schedule = DB::table('schedules')
                ->join('user_schedules', 'schedules.id', '=', 'user_schedules.schedule_id')
                ->where('user_schedules.user_id', $employee->id)
                ->select('schedules.*')
                ->orderBy('schedules.id', 'desc')
                ->first();
        }
        $scheduleDays = $this->normalizeScheduleDays($schedule->days ?? null);
        $dailyTargetMinutes = $this->scheduleDailyMinutes($schedule);

        $rawRows = DB::table('attendances')
            ->where('user_id', $employee->id)
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
            ]);

        $rowsByDate = [];
        foreach ($rawRows as $r) {
            if (!isset($rowsByDate[$r->date])) {
                $rowsByDate[$r->date] = $r;
            }
        }

        $rows = [];
        $targetMinutes = 0;
        $workedMinutes = 0;
        $weekMap = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];

        $workedDaysCount = 0;
        $workingDaysInMonth = 0;

        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $key = $cursor->toDateString();
            $att = $rowsByDate[$key] ?? null;
            $dayLetter = $weekMap[$cursor->dayOfWeekIso] ?? 'L';
            $isWorkingDay = in_array($dayLetter, $scheduleDays, true);

            if ($isWorkingDay) {
                $targetMinutes += $dailyTargetMinutes;
                $workingDaysInMonth++;
            }

            $rowWorked = 0;
            if ($att && !empty($att->hours_worked)) {
                $rowWorked = $this->timeStringToMinutes((string) $att->hours_worked);
            }
            if ($rowWorked > 0) {
                $workedDaysCount++;
            }
            $workedMinutes += $rowWorked;

            if ($isWorkingDay || $includeNonWorking) {
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
                ];
            }

            $cursor->addDay();
        }

        return response()->json([
            'month' => $monthDate->format('Y-m'),
            'employee_id' => $employee->id,
            'summary' => [
                'worked_minutes' => $workedMinutes,
                'worked_hhmm' => $this->formatMinutesAsHHMM($workedMinutes),
                'target_minutes' => $targetMinutes,
                'target_hhmm' => $this->formatMinutesAsHHMM($targetMinutes),
                'worked_days' => $workedDaysCount,
                'working_days' => $workingDaysInMonth,
                'missing_days' => max(0, $workingDaysInMonth - $workedDaysCount),
                'compliance_percent' => $targetMinutes > 0 ? (int) round(($workedMinutes / $targetMinutes) * 100) : 0,
            ],
            'schedule' => [
                'start_time' => $schedule->start_time ?? null,
                'end_time' => $schedule->end_time ?? null,
                'days' => $scheduleDays,
                'daily_target_minutes' => $dailyTargetMinutes,
            ],
            'rows' => $rows,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Documents for one employee (admin only).
     */
    public function employeeDocuments(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::whereNull('deleted_at')->find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $category = (string) $request->query('category', '');
        $q = DB::table('documents')
            ->where('user_id', $employee->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc');

        if (in_array($category, ['medical', 'receipt', 'payroll'], true)) {
            $q->where('category', $category);
        }

        $docs = $q->get([
            'id',
            'description',
            'filename',
            'original_name',
            'mime_type',
            'size',
            'category',
            'created_at',
        ]);

        return response()->json(['documents' => $docs])->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Download one employee document (admin only).
     */
    public function employeeDocumentDownload(Request $request, int $employeeId, int $docId)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::whereNull('deleted_at')->find($employeeId);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $doc = DB::table('documents')
            ->where('id', $docId)
            ->where('user_id', $employee->id)
            ->whereNull('deleted_at')
            ->first(['id', 'filename', 'original_name']);

        if (!$doc || empty($doc->filename)) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }
        if (!Storage::disk('local')->exists($doc->filename)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        $downloadName = $doc->original_name ?: basename((string) $doc->filename);
        return Storage::disk('local')->download($doc->filename, $downloadName);
    }

    /**
     * Update one employee (admin only).
     */
    public function updateEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $employee->id,
            'team_id' => 'nullable|integer|exists:teams,id',
            'photo' => 'nullable|image|max:2048',
            'phone' => 'nullable|string|max:20',
            'hire_date' => 'nullable|date',
            'birth_date' => 'nullable|date',
            'dni' => 'nullable|string|max:30',
            'social_security_number' => 'nullable|string|max:50',
            'contract_type' => 'nullable|string|max:50',
            'contract_start_date' => 'nullable|date',
            'vacation_days_total' => 'nullable|integer|min:0|max:365',
            'password' => ['nullable','confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'days' => 'nullable|array',
            'days.*' => 'in:L,M,X,J,V,S,D',
        ]);

        $employee->name = $data['name'];
        $employee->email = $data['email'];
        $employee->team_id = $data['team_id'] ?? null;
        $employee->phone = $data['phone'] ?? null;
        $employee->hire_date = $data['hire_date'] ?? null;
        $employee->birth_date = $data['birth_date'] ?? null;
        $employee->dni = $data['dni'] ?? null;
        $employee->social_security_number = $data['social_security_number'] ?? null;
        $employee->contract_type = $data['contract_type'] ?? null;
        $employee->contract_start_date = $data['contract_start_date'] ?? null;
        $employee->vacation_days_total = isset($data['vacation_days_total']) ? (int) $data['vacation_days_total'] : (int) ($employee->vacation_days_total ?? 0);
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $employee->photo = '/storage/' . $path;
        }
        if (! empty($data['password'])) {
            $employee->password = Hash::make($data['password']);
        }
        $employee->save();

        if (! empty($data['start_time']) && ! empty($data['end_time'])) {
            $days = array_values(array_unique($this->normalizeScheduleDays($data['days'] ?? null)));
            $sched = DB::table('schedules')->where('user_id', $employee->id)->first();
            if ($sched) {
                DB::table('schedules')->where('id', $sched->id)->update([
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'days' => json_encode($days),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('schedules')->insert([
                    'user_id' => $employee->id,
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'days' => json_encode($days),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Delete one employee (admin only).
     */
    public function deleteEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->id === $id) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario'], 422);
        }

        $employee = User::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $employee->delete();
        return response()->json(['status' => 'ok']);
    }
}
