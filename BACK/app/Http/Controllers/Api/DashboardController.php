<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function timeToMinutes(?string $time): int
    {
        if (!$time) return 0;
        $parts = explode(':', $time);
        if (count($parts) < 2) return 0;
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return max(0, ($h * 60) + $m);
    }

    private function minutesToHHMM(int $minutes): string
    {
        $m = max(0, $minutes);
        $h = intdiv($m, 60);
        $mm = $m % 60;
        return sprintf('%02d:%02d', $h, $mm);
    }

    private function dailyTargetMinutesForUser(int $userId): int
    {
        $schedule = DB::table('schedules')
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->first();

        if (!$schedule) {
            $schedule = DB::table('schedules')
                ->join('user_schedules', 'schedules.id', '=', 'user_schedules.schedule_id')
                ->where('user_schedules.user_id', $userId)
                ->select('schedules.*')
                ->orderBy('schedules.id', 'desc')
                ->first();
        }

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
        $row = DB::table('attendances')
            ->where('user_id', $userId)
            ->whereDate('date', $today->toDateString())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$row) return 0;

        if (!empty($row->hours_worked)) {
            return $this->timeToMinutes((string) $row->hours_worked);
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
        $rows = DB::table('attendances')
            ->where('user_id', $userId)
            ->whereDate('date', '>=', $today->copy()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $today->copy()->endOfMonth()->toDateString())
            ->get(['hours_worked']);

        $sum = 0;
        foreach ($rows as $r) {
            $sum += $this->timeToMinutes((string) ($r->hours_worked ?? null));
        }
        return $sum;
    }

    private function targetMinutesMonthForUser(int $userId, Carbon $today): int
    {
        $schedule = DB::table('schedules')
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->first();
        if (!$schedule) {
            $schedule = DB::table('schedules')
                ->join('user_schedules', 'schedules.id', '=', 'user_schedules.schedule_id')
                ->where('user_schedules.user_id', $userId)
                ->select('schedules.*')
                ->orderBy('schedules.id', 'desc')
                ->first();
        }
        if (!$schedule) return 0;

        $scheduleDays = ['L', 'M', 'X', 'J', 'V'];
        if (!empty($schedule->days)) {
            $decoded = is_string($schedule->days) ? json_decode($schedule->days, true) : $schedule->days;
            if (is_array($decoded) && count($decoded)) {
                $scheduleDays = array_values(array_unique(array_map('strtoupper', $decoded)));
            }
        }

        $daily = $this->dailyTargetMinutesForUser($userId);
        $weekMap = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
        $cursor = $today->copy()->startOfMonth();
        $end = $today->copy()->endOfMonth();
        $days = 0;
        while ($cursor->lte($end)) {
            $w = $weekMap[$cursor->dayOfWeekIso] ?? 'L';
            if (in_array($w, $scheduleDays, true)) $days++;
            $cursor->addDay();
        }
        return $days * $daily;
    }

    private function widgetCatalog(): array
    {
        return [
            [
                'key' => 'welcome',
                'name' => 'Bienvenida',
                'default_visible' => true,
                'default_slot' => 0,
                'default_settings' => [
                    'title' => 'Bienvenido',
                    'subtitle' => 'Aqui tenes un resumen de la actividad del sistema.',
                    'show_date' => true,
                ],
            ],
            ['key' => 'birthdays', 'name' => 'Cumpleaños', 'default_visible' => true, 'default_slot' => 1],
            ['key' => 'team', 'name' => 'Tu equipo', 'default_visible' => true, 'default_slot' => 2],
            ['key' => 'vacation', 'name' => 'Vacaciones', 'default_visible' => true, 'default_slot' => 3],
            ['key' => 'notifications', 'name' => 'Notificaciones', 'default_visible' => true, 'default_slot' => 4],
            ['key' => 'worked_hours', 'name' => 'Horas trabajadas', 'default_visible' => true, 'default_slot' => 5],
            ['key' => 'holidays', 'name' => 'Proximos festivos', 'default_visible' => true, 'default_slot' => 6],
            ['key' => 'announcements', 'name' => 'Comunicados', 'default_visible' => true, 'default_slot' => 7],
            ['key' => 'documents', 'name' => 'Documentos', 'default_visible' => true, 'default_slot' => 8],
        ];
    }

    private function buildLayoutForUser(int $userId): array
    {
        $catalog = $this->widgetCatalog();
        $catalogByKey = [];
        foreach ($catalog as $w) {
            $catalogByKey[$w['key']] = $w;
        }

        $rows = DB::table('user_dashboard_widgets')
            ->where('user_id', $userId)
            ->get(['widget_key', 'slot_index', 'visible', 'settings']);

        $hasCustom = $rows->count() > 0;

        $visibility = [];
        $settings = [];
        $slots = array_fill(0, 9, null);

        if (!$hasCustom) {
            foreach ($catalog as $w) {
                $visibility[$w['key']] = (bool) $w['default_visible'];
                $settings[$w['key']] = $w['default_settings'] ?? [];
                if ($w['default_visible'] && isset($w['default_slot']) && $w['default_slot'] >= 0 && $w['default_slot'] < 9) {
                    $slots[$w['default_slot']] = $w['key'];
                }
            }
        } else {
            foreach ($catalog as $w) {
                $visibility[$w['key']] = (bool) $w['default_visible'];
                $settings[$w['key']] = $w['default_settings'] ?? [];
            }

            foreach ($rows as $r) {
                if (!isset($catalogByKey[$r->widget_key])) continue;
                $visibility[$r->widget_key] = (bool) $r->visible;
                $decoded = is_string($r->settings) ? json_decode($r->settings, true) : (is_array($r->settings) ? $r->settings : null);
                if (is_array($decoded)) {
                    $settings[$r->widget_key] = array_merge($settings[$r->widget_key] ?? [], $decoded);
                }
            }

            $placed = [];
            foreach ($rows as $r) {
                if (!isset($catalogByKey[$r->widget_key])) continue;
                if (!(bool) $r->visible) continue;
                if ($r->slot_index === null) continue;
                $slot = (int) $r->slot_index;
                if ($slot < 0 || $slot > 8) continue;
                if ($slots[$slot] !== null) continue;
                if (in_array($r->widget_key, $placed, true)) continue;
                $slots[$slot] = $r->widget_key;
                $placed[] = $r->widget_key;
            }

            foreach ($catalog as $w) {
                $key = $w['key'];
                if (!($visibility[$key] ?? false)) continue;
                if (in_array($key, $slots, true)) continue;
                $emptyIndex = array_search(null, $slots, true);
                if ($emptyIndex === false) break;
                $slots[$emptyIndex] = $key;
            }
        }

        return [
            'slots' => $slots,
            'visibility' => $visibility,
            'settings' => $settings,
            'widgets' => $catalog,
        ];
    }

    public function dashboardLayout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($this->buildLayoutForUser((int) $user->id))
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function saveDashboardLayout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $catalog = $this->widgetCatalog();
        $allowedKeys = array_map(fn ($w) => $w['key'], $catalog);
        $slotsInput = $request->input('slots');
        $visibilityInput = $request->input('visibility');
        $settingsInput = $request->input('settings');

        if (!is_array($slotsInput)) {
            $slotsInput = array_fill(0, 9, null);
        }

        $slotsNormalized = array_slice(array_pad($slotsInput, 9, null), 0, 9);
        $seen = [];
        foreach ($slotsNormalized as $idx => $value) {
            if ($value === null || $value === '') {
                $slotsNormalized[$idx] = null;
                continue;
            }
            if (!is_string($value) || !in_array($value, $allowedKeys, true)) {
                return response()->json(['message' => 'Slot inválido'], 422);
            }
            if (in_array($value, $seen, true)) {
                return response()->json(['message' => 'No se permiten widgets duplicados'], 422);
            }
            $seen[] = $value;
        }

        $visibility = [];
        $settings = [];
        $existingRows = DB::table('user_dashboard_widgets')
            ->where('user_id', $user->id)
            ->get(['widget_key', 'settings', 'visible']);
        $existingByKey = [];
        foreach ($existingRows as $r) {
            $existingByKey[$r->widget_key] = $r;
        }
        foreach ($catalog as $w) {
            $key = $w['key'];
            $visibility[$key] = (bool) $w['default_visible'];
            $settings[$key] = $w['default_settings'] ?? [];
            if (isset($existingByKey[$key])) {
                $old = $existingByKey[$key];
                $visibility[$key] = (bool) $old->visible;
                $decoded = is_string($old->settings) ? json_decode($old->settings, true) : (is_array($old->settings) ? $old->settings : null);
                if (is_array($decoded)) {
                    $settings[$key] = array_merge($settings[$key], $decoded);
                }
            }
        }
        if (is_array($visibilityInput)) {
            foreach ($visibilityInput as $k => $v) {
                if (in_array($k, $allowedKeys, true)) {
                    $visibility[$k] = (bool) $v;
                }
            }
        }
        if (is_array($settingsInput)) {
            foreach ($settingsInput as $k => $v) {
                if (!in_array($k, $allowedKeys, true)) continue;
                if (!is_array($v)) continue;
                $settings[$k] = array_merge($settings[$k] ?? [], $v);
            }
        }

        DB::transaction(function () use ($user, $catalog, $slotsNormalized, $visibility, $settings) {
            $slotByWidget = [];
            foreach ($slotsNormalized as $idx => $key) {
                if ($key !== null) {
                    $slotByWidget[$key] = $idx;
                }
            }

            foreach ($catalog as $w) {
                $key = $w['key'];
                DB::table('user_dashboard_widgets')->updateOrInsert(
                    ['user_id' => $user->id, 'widget_key' => $key],
                    [
                        'slot_index' => array_key_exists($key, $slotByWidget) ? $slotByWidget[$key] : null,
                        'visible' => (bool) ($visibility[$key] ?? true),
                        'settings' => json_encode($settings[$key] ?? []),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });

        return response()->json([
            'status' => 'ok',
            'layout' => $this->buildLayoutForUser((int) $user->id),
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function index(Request $request)
    {
        $today = Carbon::today();

        $usersCount = User::count();
        $postsCount = DB::table('posts')->count();
        // total open attendances in the system (used previously as "attendances_today")
        // requirements changed: only records that are unfinished
        // only consider records for today with unfinished state
        $attendancesToday = DB::table('attendances')
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
                'worked_hhmm' => $this->minutesToHHMM($workedMinutes),
                'target_minutes' => $targetMinutes,
                'target_hhmm' => $this->minutesToHHMM($targetMinutes),
                'progress_percent' => $targetMinutes > 0 ? (int) min(999, round(($workedMinutes / $targetMinutes) * 100)) : 0,
                'worked_month_minutes' => $workedMonth,
                'worked_month_hhmm' => $this->minutesToHHMM($workedMonth),
                'target_month_minutes' => $targetMonth,
                'target_month_hhmm' => $this->minutesToHHMM($targetMonth),
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
        ])->header('Access-Control-Allow-Origin', '*');
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

        $team = DB::table('teams')->where('id', $user->team_id)->first(['name']);

        $rows = DB::table('users')
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
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function upcomingHolidays(Request $request)
    {
        $today = Carbon::today();
        $year = (int) $today->year;
        $nextYear = $year + 1;
        $list = [
            ['date' => "{$year}-01-01", 'name' => 'Año Nuevo'],
            ['date' => "{$year}-05-01", 'name' => 'Dia del Trabajador'],
            ['date' => "{$year}-12-25", 'name' => 'Navidad'],
            ['date' => "{$nextYear}-01-01", 'name' => 'Año Nuevo'],
            ['date' => "{$nextYear}-05-01", 'name' => 'Dia del Trabajador'],
            ['date' => "{$nextYear}-12-25", 'name' => 'Navidad'],
        ];

        $rows = collect($list)
            ->filter(fn ($h) => Carbon::parse($h['date'])->gte($today))
            ->sortBy('date')
            ->take(5)
            ->values();

        return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
    }

    public function announcements(Request $request)
    {
        $rows = DB::table('posts')
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

        return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
    }

    public function documentsSummary(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $rows = DB::table('documents')
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

        return response()->json($summary)->header('Access-Control-Allow-Origin', '*');
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

        return response()->json($users->take(10))->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Return users currently "en_trabajo" for today.
     */
    public function working(Request $request)
    {
        $today = Carbon::today()->toDateString();

        $rows = DB::table('attendances')
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

        return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
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
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();

        $requestedYear = 0;
        $requestedRows = DB::table('events')
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.user_id', $user->id)
            ->whereNull('events.deleted_at')
            ->whereRaw('LOWER(TRIM(event_types.name)) = ?', ['vacaciones'])
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
            $requestedYear += ((int) $clampedStart->diffInDays($clampedEnd)) + 1;
        }

        $vacationDaysTotal = (int) ($user->vacation_days_total ?? 0);
        $vacationDaysRemaining = max(0, $vacationDaysTotal - $requestedYear);

        $event = DB::table('events')
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.user_id', $user->id)
            ->whereNull('events.deleted_at')
            ->whereRaw('LOWER(TRIM(event_types.name)) = ?', ['vacaciones'])
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
            ])->header('Access-Control-Allow-Origin', '*');
        }

        $start        = Carbon::parse($event->start_at)->startOfDay();
        $end          = $event->end_at ? Carbon::parse($event->end_at)->startOfDay() : $start->copy();
        $daysUntil    = (int) $today->diffInDays($start, false);
        $vacationDays = (int) $start->diffInDays($end) + 1; // inclusive

        return response()->json([
            'days_until_vacation' => max(0, $daysUntil),
            'vacation_days'       => $vacationDays,
            'vacation_days_total' => $vacationDaysTotal,
            'vacation_days_requested_year' => $requestedYear,
            'remaining_vacation_days' => $vacationDaysRemaining,
        ])->header('Access-Control-Allow-Origin', '*');
    }
}
