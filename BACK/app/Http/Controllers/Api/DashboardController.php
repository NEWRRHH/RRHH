<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\DashboardWidget;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Post;
use App\Models\Team;
use App\Services\DashboardLayoutService;
use App\Services\ScheduleService;
use App\Support\Audit;
use App\Support\WorkTime;

class DashboardController extends Controller
{
    public function __construct(private ScheduleService $schedules)
    {
    }
    private function dailyTargetMinutesForUser(int $userId): int
    {
        $schedule = $this->schedules->loadEffectiveScheduleForUser($userId);

        if (!$schedule || empty($schedule->start_time) || empty($schedule->end_time)) return 0;

        $start = Carbon::parse((string) $schedule->start_time);
        $end = Carbon::parse((string) $schedule->end_time);
        $mins = (int) $start->diffInMinutes($end, false);
        if ($mins < 0) $mins += 24 * 60;
        if ($mins >= 6 * 60) $mins -= 60;
        return max(0, $mins);
    }

    private function workedMinutesTodayForUser(int $userId, Carbon $today): int
    {
        $row = Attendance::table()
            ->where('user_id', $userId)
            ->whereDate('date', $today->toDateString())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$row) return 0;

        if (!empty($row->hours_worked)) {
            return WorkTime::timeStringToMinutes((string) $row->hours_worked);
        }

        if (empty($row->start_time)) return 0;

        $start = Carbon::parse($today->toDateString() . ' ' . $row->start_time);
        $end = !empty($row->end_time) ? Carbon::parse($today->toDateString() . ' ' . $row->end_time) : Carbon::now();

        $total = max(0, (int) $start->diffInMinutes($end, false));
        $paused = 0;

        if (!empty($row->pause_time)) {
            $pauseStart = Carbon::parse($today->toDateString() . ' ' . $row->pause_time);
            $pauseEnd = $end->copy();
            if (!empty($row->resume_time)) {
                $resume = Carbon::parse($today->toDateString() . ' ' . $row->resume_time);
                if ($resume->greaterThan($pauseStart)) {
                    $pauseEnd = $resume->lessThan($end) ? $resume : $end->copy();
                }
            }
            if ($pauseEnd->greaterThan($pauseStart)) {
                $paused = (int) $pauseStart->diffInMinutes($pauseEnd);
            }
        }

        return max(0, $total - $paused);
    }

    private function workedMinutesMonthForUser(int $userId, Carbon $today): int
    {
        $rows = Attendance::table()
            ->where('user_id', $userId)
            ->whereDate('date', '>=', $today->copy()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $today->copy()->endOfMonth()->toDateString())
            ->get(['hours_worked']);

        $sum = 0;
        foreach ($rows as $r) {
            $sum += WorkTime::timeStringToMinutes((string) ($r->hours_worked ?? null));
        }
        return $sum;
    }

    private function targetMinutesMonthForUser(int $userId, Carbon $today): int
    {
        $schedule = $this->schedules->loadEffectiveScheduleForUser($userId);
        if (!$schedule) return 0;

        $scheduleDays = ['L', 'M', 'X', 'J', 'V'];
        if (!empty($schedule->days)) {
            $decoded = is_string($schedule->days) ? json_decode($schedule->days, true) : $schedule->days;
            if (is_array($decoded) && count($decoded)) {
                $scheduleDays = array_values(array_unique(array_map('strtoupper', $decoded)));
            }
        }

        $daily = $this->dailyTargetMinutesForUser($userId);
        // holidays are not working days
        $days = $this->schedules->calculateWorkingDays($today->copy()->startOfMonth(), $today->copy()->endOfMonth(), $scheduleDays);

        return $days * $daily;
    }

    /**
     * The logged-in user's dashboard: their own arrangement if they made one,
     * otherwise the company layout. The welcome message is the company's.
     */
    public function dashboardLayout(Request $request, DashboardLayoutService $layouts)
    {
        return response()->json($layouts->forUser($request->user()));
    }

    /**
     * Save the user's own arrangement (drag & drop on their dashboard).
     */
    public function saveDashboardLayout(Request $request, DashboardLayoutService $layouts)
    {
        $data = $request->validate([
            'slots' => 'required|array|max:' . DashboardLayoutService::POSITIONS,
            'slots.*' => 'nullable|string|max:50',
        ]);
        $layouts->saveForUser($request->user(), $data['slots']);

        return response()->json(['status' => 'ok', 'layout' => $layouts->forUser($request->user())]);
    }

    /**
     * Drop the user's arrangement and go back to the company layout.
     */
    public function resetDashboardLayout(Request $request, DashboardLayoutService $layouts)
    {
        $layouts->resetForUser($request->user());

        return response()->json(['status' => 'ok', 'layout' => $layouts->forUser($request->user())]);
    }

    /**
     * Company default layout + welcome message (RRHH, "dashboard.manage").
     */
    public function companyLayout(Request $request, DashboardLayoutService $layouts)
    {
        if ($request->user()->cannot('dashboard.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($layouts->companyLayout() + [
            'widgets' => $layouts->catalog(),
            'default_slots' => $layouts->defaultSlots(),
            'default_welcome' => DashboardLayoutService::DEFAULT_WELCOME,
        ]);
    }

    public function saveCompanyLayout(Request $request, DashboardLayoutService $layouts)
    {
        if ($request->user()->cannot('dashboard.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'slots' => 'required|array|max:' . DashboardLayoutService::POSITIONS,
            'slots.*' => 'nullable|string|max:50',
            'welcome' => 'required|array',
            'welcome.title' => 'nullable|string|max:80',
            'welcome.subtitle' => 'nullable|string|max:180',
            'welcome.show_date' => 'nullable|boolean',
        ]);

        $layout = $layouts->saveCompanyLayout($request->user(), $data['slots'], $data['welcome']);
        Audit::log('dashboard.company_layout_updated', null, ['slots' => $layout['slots']]);

        return response()->json(['status' => 'ok', 'layout' => $layout]);
    }

    public function index(Request $request)
    {
        $today = Carbon::today();

        $usersCount = User::count();
        $postsCount = Post::table()->count();
        // total open attendances in the system (used previously as "attendances_today")
        // requirements changed: only records that are unfinished
        // only consider records for today with unfinished state
        $attendancesToday = Attendance::table()
            ->whereDate('date', $today)
            ->where(function ($q) {
                $q->where('status', 'en_trabajo')
                  ->orWhereNull('end_time')
                  ->orWhereNull('end_date');
            })
            ->count();

        // notificaciones entrantes para el usuario actual
        $notifications = [];
        $unreadCount = 0;
        if ($request->user()) {
            $userId = $request->user()->id;
            // cargar todas las conversaciones donde participa
            $convs = \App\Models\Notification::with('sender')
                ->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)
                      ->orWhere('receiver_id', $userId);
                })->get();

            foreach ($convs as $conv) {
                $conv->sender_name = trim(($conv->sender->first_name ?? '') . ' ' . ($conv->sender->last_name ?? ''));
                // recorrer cada mensaje para generar items individuales
                $msgs = $conv->conversation ?: [];
                $idx = 0;
                foreach ($msgs as $msg) {
                    if (isset($msg['sender_id']) && $msg['sender_id'] != $userId && empty($msg['read'])) {
                        $notifications[] = [
                            'message_id' => $conv->id . '_' . $idx,
                            'conversation_id' => $conv->id,
                            'sender_id' => $conv->sender_id,
                            'sender_name' => $conv->sender_name,
                            'content' => $msg['content'] ?? '',
                            'created_at' => $msg['created_at'] ?? null,
                            'read' => false,
                        ];
                        $unreadCount++;
                    }
                    $idx++;
                }
            }
            // ordenar por fecha descendente
            usort($notifications, function ($a, $b) {
                return strtotime($b['created_at']) <=> strtotime($a['created_at']);
            });
        }
        $birthdaysToday = User::whereNotNull('birth_date')
            ->whereRaw('MONTH(birth_date) = ? AND DAY(birth_date) = ?', [$today->month, $today->day])
            ->count();

        $workedHours = null;
        if ($request->user()) {
            $uid = (int) $request->user()->id;
            $workedMinutes = $this->workedMinutesTodayForUser($uid, $today);
            $targetMinutes = $this->dailyTargetMinutesForUser($uid);
            $workedMonth = $this->workedMinutesMonthForUser($uid, $today);
            $targetMonth = $this->targetMinutesMonthForUser($uid, $today);
            $workedHours = [
                'worked_minutes' => $workedMinutes,
                'worked_hhmm' => WorkTime::formatMinutesAsHHMM($workedMinutes),
                'target_minutes' => $targetMinutes,
                'target_hhmm' => WorkTime::formatMinutesAsHHMM($targetMinutes),
                'progress_percent' => $targetMinutes > 0 ? (int) min(999, round(($workedMinutes / $targetMinutes) * 100)) : 0,
                'worked_month_minutes' => $workedMonth,
                'worked_month_hhmm' => WorkTime::formatMinutesAsHHMM($workedMonth),
                'target_month_minutes' => $targetMonth,
                'target_month_hhmm' => WorkTime::formatMinutesAsHHMM($targetMonth),
            ];
        }

        return response()->json([
            'message' => 'Welcome to the API dashboard',
            'stats' => [
                'users_count' => $usersCount,
                'posts_count' => $postsCount,
                'attendances_today' => $attendancesToday,
                'birthdays_today' => $birthdaysToday,
            ],
            'notifications' => $notifications,
            'unread_notifications' => $unreadCount,
            'worked_hours' => $workedHours,
        ]);
    }

    public function team(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        if (!$user->team_id) {
            return response()->json([
                'team_name' => null,
                'members' => [],
            ]);
        }

        $team = Team::table()->where('id', $user->team_id)->first(['name']);

        $rows = User::table()
            ->where('team_id', $user->team_id)
            ->whereNull('deleted_at')
            ->orderByRaw("COALESCE(first_name, name, '') asc")
            ->get(['id', 'first_name', 'last_name', 'name', 'photo', 'profile_photo_path'])
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'full_name' => trim(($u->first_name ?? $u->name ?? '') . ' ' . ($u->last_name ?? '')),
                    'photo' => $u->photo ?? $u->profile_photo_path ?? null,
                ];
            })
            ->values();

        return response()->json([
            'team_name' => $team->name ?? null,
            'members' => $rows,
        ]);
    }

    public function announcements(Request $request)
    {
        $rows = Post::table()
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->orderBy('posts.created_at', 'desc')
            ->limit(5)
            ->get([
                'posts.id',
                'posts.title',
                'posts.body',
                'posts.created_at',
                'users.first_name',
                'users.last_name',
                'users.name as user_name',
            ])
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'body' => $p->body,
                    'created_at' => $p->created_at,
                    'author' => trim(($p->first_name ?? $p->user_name ?? '') . ' ' . ($p->last_name ?? '')),
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function documentsSummary(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $rows = Document::table()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get(['id', 'category']);

        $summary = [
            'medical' => 0,
            'receipt' => 0,
            'payroll' => 0,
            'total' => 0,
        ];

        foreach ($rows as $r) {
            $cat = (string) ($r->category ?? '');
            if (array_key_exists($cat, $summary)) {
                $summary[$cat]++;
            }
            $summary['total']++;
        }

        return response()->json($summary);
    }

    /**
     * Return upcoming birthdays (authenticated)
     */
    public function birthdays(Request $request)
    {
        $today = Carbon::today();

        $users = User::whereNotNull('birth_date')
            ->get()
            ->map(function ($u) use ($today) {
                try {
                    $dob = Carbon::parse($u->birth_date);
                } catch (\Exception $e) {
                    return null;
                }

                $next = $dob->copy()->year($today->year);
                if ($next->lt($today)) {
                    $next->addYear();
                }

                return [
                    'id' => $u->id,
                    'first_name' => $u->first_name ?? $u->name ?? null,
                    'last_name' => $u->last_name ?? null,
                    'full_name' => trim(($u->first_name ?? $u->name ?? '') . ' ' . ($u->last_name ?? '')),
                    'photo' => $u->photo ?? $u->profile_photo_path ?? null,
                    'birth_date' => $dob->toDateString(),
                    'next_birthday' => $next->toDateString(),
                    'age' => $next->year - $dob->year,
                ];
            })
            ->filter()
            ->sortBy('next_birthday')
            ->values();

        return response()->json($users->take(10));
    }

    /**
     * Return users currently "en_trabajo" for today.
     */
    public function working(Request $request)
    {
        $today = Carbon::today()->toDateString();

        $rows = Attendance::table()
            ->where('date', $today)
            ->where('status', 'en_trabajo')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.photo', 'users.profile_photo_path')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'first_name' => $u->first_name ?? null,
                    'full_name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                    'photo' => $u->photo ?? $u->profile_photo_path ?? null,
                ];
            });

        return response()->json($rows);
    }

    /**
     * Mark a single notification as read (authenticated user only).
     *
     * The frontend will call this when opening the modal so the "read" flag
     * is updated in the database immediately.
     */
    public function markNotificationRead(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // ensure the notification belongs to the current user as receptor
        $note = \App\Models\Notification::where('id', $id)
            ->where('receiver_id', $user->id)
            ->first();
        if ($note) {
            $note->read = 1;
            $note->save();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }

    /**
     * Return next vacation event for the authenticated user.
     * Reads from events JOIN event_types where type name contains 'vacaci'.
     */
    public function vacationsMe(Request $request)
    {
        $user  = $request->user();
        $today = Carbon::today();
        $scheduleDays = $this->schedules->loadScheduleDaysForUser((int) $user->id);
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();

        $vacationTypeId = EventType::idFor(EventType::VACATION);
        $requestedWorkingDates = [];
        $requestedRows = Event::table()
            ->where('events.user_id', $user->id)
            ->where('events.event_type_id', $vacationTypeId)
            ->whereNull('events.deleted_at')
            ->where(function ($q) {
                $q->whereNull('events.approval_status')
                    ->orWhereIn('events.approval_status', ['approved', 'pending']);
            })
            ->where(function ($q) use ($yearStart, $yearEnd) {
                $q->whereBetween('events.start_at', [$yearStart->toDateTimeString(), $yearEnd->toDateTimeString()])
                  ->orWhereBetween('events.end_at', [$yearStart->toDateTimeString(), $yearEnd->toDateTimeString()])
                  ->orWhere(function ($q2) use ($yearStart, $yearEnd) {
                      $q2->where('events.start_at', '<=', $yearStart->toDateTimeString())
                         ->where(function ($q3) use ($yearEnd) {
                             $q3->where('events.end_at', '>=', $yearEnd->toDateTimeString())
                                ->orWhereNull('events.end_at');
                         });
                  });
            })
            ->get(['events.start_at', 'events.end_at']);

        foreach ($requestedRows as $row) {
            $start = Carbon::parse($row->start_at)->startOfDay();
            $end = $row->end_at ? Carbon::parse($row->end_at)->startOfDay() : $start->copy();
            if ($end->lt($yearStart) || $start->gt($yearEnd)) continue;
            $clampedStart = $start->lt($yearStart) ? $yearStart->copy() : $start->copy();
            $clampedEnd = $end->gt($yearEnd) ? $yearEnd->copy() : $end->copy();

            $cursor = $clampedStart->copy();
            while ($cursor->lte($clampedEnd)) {
                if ($this->schedules->isWorkingDate($cursor, $scheduleDays)) {
                    $requestedWorkingDates[$cursor->toDateString()] = true;
                }
                $cursor->addDay();
            }
        }
        $requestedYear = count($requestedWorkingDates);

        $vacationDaysTotal = (int) ($user->vacation_days_total ?? 0);
        $vacationDaysRemaining = max(0, $vacationDaysTotal - $requestedYear);

        $event = Event::table()
            ->where('events.user_id', $user->id)
            ->where('events.event_type_id', $vacationTypeId)
            ->whereNull('events.deleted_at')
            ->where(function ($q) {
                $q->whereNull('events.approval_status')
                    ->orWhereIn('events.approval_status', ['approved', 'pending']);
            })
            ->where('events.start_at', '>=', $today->toDateTimeString())
            ->orderBy('events.start_at', 'asc')
            ->select('events.start_at', 'events.end_at')
            ->first();

        if (!$event) {
            return response()->json([
                'days_until_vacation' => null,
                'vacation_days'       => null,
                'vacation_days_total' => $vacationDaysTotal,
                'vacation_days_requested_year' => $requestedYear,
                'remaining_vacation_days' => $vacationDaysRemaining,
            ]);
        }

        $start        = Carbon::parse($event->start_at)->startOfDay();
        $end          = $event->end_at ? Carbon::parse($event->end_at)->startOfDay() : $start->copy();
        $daysUntil    = (int) $today->diffInDays($start, false);
        $vacationDays = $this->schedules->calculateWorkingDays($start, $end, $scheduleDays);

        return response()->json([
            'days_until_vacation' => max(0, $daysUntil),
            'vacation_days'       => $vacationDays,
            'vacation_days_total' => $vacationDaysTotal,
            'vacation_days_requested_year' => $requestedYear,
            'remaining_vacation_days' => $vacationDaysRemaining,
        ]);
    }
}
