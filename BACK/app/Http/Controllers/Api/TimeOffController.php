<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\TimeOffRequestUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeOffController extends Controller
{
    private function parseAttendanceRequestKindFromEvent(object $event): string
    {
        $description = strtolower(trim((string) ($event->description ?? '')));
        if (preg_match('/tipo:\s*(entry_exit|entry|exit)/i', $description, $m)) {
            $kind = strtolower((string) ($m[1] ?? ''));
            if (in_array($kind, ['entry_exit', 'entry', 'exit'], true)) {
                return $kind;
            }
        }

        $title = strtolower(trim((string) ($event->title ?? '')));
        if (str_contains($title, 'entrada y salida')) return 'entry_exit';
        if (str_contains($title, 'entrada')) return 'entry';
        if (str_contains($title, 'salida')) return 'exit';

        return 'entry_exit';
    }

    private function secondsToMysqlTime(int $seconds): string
    {
        $s = max(0, $seconds);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $ss = $s % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $ss);
    }

    private function applyApprovedAttendanceRequest(object $event): void
    {
        $requestDate = Carbon::parse((string) $event->start_at)->toDateString();
        $startAt = Carbon::parse((string) $event->start_at);
        $endAt = Carbon::parse((string) ($event->end_at ?: $event->start_at));
        $kind = $this->parseAttendanceRequestKindFromEvent($event);

        $attendance = DB::table('attendances')
            ->where('user_id', (int) $event->user_id)
            ->whereDate('date', $requestDate)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$attendance) {
            $startTime = $startAt->format('H:i:s');
            $endTime = in_array($kind, ['entry_exit', 'exit'], true) ? $endAt->format('H:i:s') : null;
            $worked = null;
            $status = 'en_trabajo';
            $endDate = null;

            if ($endTime) {
                $startDateTime = Carbon::parse($requestDate . ' ' . $startTime);
                $endDateTime = Carbon::parse($requestDate . ' ' . $endTime);
                if ($endDateTime->lte($startDateTime)) {
                    $endDateTime->addDay();
                }
                $worked = $this->secondsToMysqlTime($startDateTime->diffInSeconds($endDateTime));
                $status = 'fuera_trabajo';
                $endDate = $requestDate;
            }

            DB::table('attendances')->insert([
                'date' => $requestDate,
                'user_id' => (int) $event->user_id,
                'start_time' => $startTime,
                'pause_time' => null,
                'resume_time' => null,
                'end_time' => $endTime,
                'end_date' => $endDate,
                'hours_worked' => $worked,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        $updates = [];
        if (in_array($kind, ['entry_exit', 'entry'], true) && empty($attendance->start_time)) {
            $updates['start_time'] = $startAt->format('H:i:s');
        }
        if (in_array($kind, ['entry_exit', 'exit'], true) && empty($attendance->end_time)) {
            $updates['end_time'] = $endAt->format('H:i:s');
            $updates['end_date'] = $requestDate;
        }

        $finalStart = isset($updates['start_time']) ? $updates['start_time'] : ($attendance->start_time ?? null);
        $finalEnd = isset($updates['end_time']) ? $updates['end_time'] : ($attendance->end_time ?? null);
        if (!empty($finalStart) && !empty($finalEnd)) {
            $startDateTime = Carbon::parse($requestDate . ' ' . $finalStart);
            $endDateTime = Carbon::parse($requestDate . ' ' . $finalEnd);
            if ($endDateTime->lte($startDateTime)) {
                $endDateTime->addDay();
            }

            $seconds = $startDateTime->diffInSeconds($endDateTime);
            if (!empty($attendance->pause_time)) {
                $pauseStart = Carbon::parse($requestDate . ' ' . $attendance->pause_time);
                $pauseEnd = !empty($attendance->resume_time)
                    ? Carbon::parse($requestDate . ' ' . $attendance->resume_time)
                    : $endDateTime->copy();
                if ($pauseEnd->greaterThan($pauseStart)) {
                    $paused = $pauseStart->diffInSeconds($pauseEnd);
                    $seconds = max(0, $seconds - $paused);
                }
            }

            $updates['hours_worked'] = $this->secondsToMysqlTime((int) $seconds);
            $updates['status'] = 'fuera_trabajo';
            $updates['end_date'] = $requestDate;
        }

        if (!empty($updates)) {
            $updates['updated_at'] = now();
            DB::table('attendances')->where('id', $attendance->id)->update($updates);
        }
    }

    private function vacationWorkingDaysSetForYear(int $userId, int $year, array $scheduleDays, ?int $excludeEventId = null): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $rowsQuery = DB::table('events')
            ->where('user_id', $userId)
            ->where('event_type_id', 2)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('approval_status')
                    ->orWhereIn('approval_status', ['approved', 'pending']);
            })
            ->where(function ($q) use ($yearStart, $yearEnd) {
                $q->whereBetween('start_at', [$yearStart->toDateTimeString(), $yearEnd->toDateTimeString()])
                    ->orWhereBetween('end_at', [$yearStart->toDateTimeString(), $yearEnd->toDateTimeString()])
                    ->orWhere(function ($q2) use ($yearStart, $yearEnd) {
                        $q2->where('start_at', '<=', $yearStart->toDateTimeString())
                            ->where(function ($q3) use ($yearEnd) {
                                $q3->where('end_at', '>=', $yearEnd->toDateTimeString())
                                    ->orWhereNull('end_at');
                            });
                    });
            });

        if ($excludeEventId) {
            $rowsQuery->where('id', '!=', $excludeEventId);
        }

        $rows = $rowsQuery->get(['start_at', 'end_at']);
        $weekMap = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
        $set = [];

        foreach ($rows as $row) {
            $start = Carbon::parse($row->start_at)->startOfDay();
            $end = $row->end_at ? Carbon::parse($row->end_at)->startOfDay() : $start->copy();
            if ($end->lt($yearStart) || $start->gt($yearEnd)) continue;
            $clampedStart = $start->lt($yearStart) ? $yearStart->copy() : $start->copy();
            $clampedEnd = $end->gt($yearEnd) ? $yearEnd->copy() : $end->copy();

            $cursor = $clampedStart->copy();
            while ($cursor->lte($clampedEnd)) {
                $letter = $weekMap[$cursor->dayOfWeekIso] ?? 'L';
                if (in_array($letter, $scheduleDays, true)) {
                    $set[$cursor->toDateString()] = true;
                }
                $cursor->addDay();
            }
        }

        return $set;
    }

    private function appendWorkingRangeToSet(array $set, Carbon $start, Carbon $end, array $scheduleDays): array
    {
        $weekMap = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        while ($cursor->lte($last)) {
            $letter = $weekMap[$cursor->dayOfWeekIso] ?? 'L';
            if (in_array($letter, $scheduleDays, true)) {
                $set[$cursor->toDateString()] = true;
            }
            $cursor->addDay();
        }
        return $set;
    }

    private function teamHasPermission(?int $teamId, string $permissionCode): bool
    {
        if (!$teamId) return false;
        return DB::table('team_permision')
            ->join('permisions', 'team_permision.permision_id', '=', 'permisions.id')
            ->where('team_permision.team_id', $teamId)
            ->where('permisions.code', $permissionCode)
            ->exists();
    }

    private function canReviewRequests(?object $user): bool
    {
        if (!$user) return false;
        return $this->teamHasPermission((int) ($user->team_id ?? 0), 'requests.review');
    }

    private function eventDisplayColor(?string $approvalStatus, ?string $selectedColor): string
    {
        $status = strtolower(trim((string) $approvalStatus));
        if ($status === 'pending') return '#9CA3AF';
        if ($status === 'rejected') return '#EF4444';
        return $selectedColor ?: '#3B82F6';
    }

    private function normalizeApprovalStatus(?string $status): string
    {
        $value = strtolower(trim((string) $status));
        if (in_array($value, ['pending', 'approved', 'rejected'], true)) {
            return $value;
        }
        return 'approved';
    }

    private function loadScheduleForUser(int $userId): ?object
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

        return $schedule ?: null;
    }

    private function normalizeScheduleDays($days): array
    {
        $default = ['L', 'M', 'X', 'J', 'V'];
        if (empty($days)) {
            return $default;
        }

        $decoded = $days;
        if (is_string($days)) {
            $decoded = json_decode($days, true);
        }

        if (!is_array($decoded)) {
            return $default;
        }

        $allowed = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
        $normalized = array_values(array_intersect($allowed, array_map('strtoupper', $decoded)));
        return count($normalized) ? $normalized : $default;
    }

    private function loadScheduleDaysForUser(int $userId): array
    {
        $schedule = $this->loadScheduleForUser($userId);
        return $this->normalizeScheduleDays($schedule->days ?? null);
    }

    private function calculateWorkingDays(Carbon $start, Carbon $end, array $scheduleDays): int
    {
        $weekMap = [
            1 => 'L',
            2 => 'M',
            3 => 'X',
            4 => 'J',
            5 => 'V',
            6 => 'S',
            7 => 'D',
        ];

        $count = 0;
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $letter = $weekMap[$cursor->dayOfWeekIso] ?? 'L';
            if (in_array($letter, $scheduleDays, true)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function calendar(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $year = (int) ($request->query('year') ?: Carbon::today()->year);
        if ($year < 2000 || $year > 2100) {
            $year = Carbon::today()->year;
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();
        $schedule = $this->loadScheduleForUser((int) $user->id);
        $scheduleDays = $this->normalizeScheduleDays($schedule->days ?? null);

        $eventTypes = DB::table('event_types')
            ->whereNull('deleted_at')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        $events = DB::table('events')
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.user_id', $user->id)
            ->whereNull('events.deleted_at')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('events.start_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->orWhereBetween('events.end_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('events.start_at', '<=', $start->toDateTimeString())
                            ->where(function ($q3) use ($end) {
                                $q3->where('events.end_at', '>=', $end->toDateTimeString())
                                    ->orWhereNull('events.end_at');
                            });
                    });
            })
            ->orderBy('events.start_at', 'asc')
            ->select(
                'events.id',
                'events.title',
                'events.description',
                'events.start_at',
                'events.end_at',
                'events.all_day',
                'events.color',
                'events.approval_status',
                'events.approval_comment',
                'events.reviewed_at',
                'events.event_type_id',
                'event_types.name as event_type_name',
                'events.created_at'
            )
            ->get()
            ->map(function ($row) {
                $startAt = Carbon::parse($row->start_at);
                $endAt = $row->end_at ? Carbon::parse($row->end_at) : $startAt->copy();
                $startDate = $startAt->toDateString();
                $endDate = $endAt->toDateString();
                $status = $this->normalizeApprovalStatus($row->approval_status ?? null);
                return [
                    'id' => $row->id,
                    'title' => $row->title,
                    'description' => $row->description,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startAt->format('H:i'),
                    'end_time' => $endAt->format('H:i'),
                    'all_day' => (bool) $row->all_day,
                    'color' => $row->color,
                    'display_color' => $this->eventDisplayColor($status, $row->color),
                    'approval_status' => $status,
                    'approval_comment' => $row->approval_comment,
                    'reviewed_at' => $row->reviewed_at,
                    'event_type_id' => $row->event_type_id,
                    'event_type_name' => $row->event_type_name,
                    'created_at' => $row->created_at,
                ];
            })
            ->values();

        return response()->json([
            'year' => $year,
            'schedule_days' => $scheduleDays,
            'schedule' => [
                'start_time' => isset($schedule->start_time) ? substr((string) $schedule->start_time, 0, 5) : null,
                'end_time' => isset($schedule->end_time) ? substr((string) $schedule->end_time, 0, 5) : null,
            ],
            'event_types' => $eventTypes,
            'events' => $events,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function create(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'event_type_id' => 'required|integer|exists:event_types,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'all_day' => 'nullable|boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'color' => 'nullable|string|max:20',
        ]);

        $allDay = (bool) ($data['all_day'] ?? true);
        $start = Carbon::createFromFormat('Y-m-d', $data['start_date'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end_date'])->endOfDay();

        $today = Carbon::today();
        if ($start->lt($today)) {
            return response()->json(['message' => 'No se pueden solicitar dias pasados'], 422);
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $isMultiDay = $start->toDateString() !== $end->toDateString();
        if ($isMultiDay && !$allDay) {
            return response()->json(['message' => 'Si seleccionas varios dias, solo se permite evento de dia completo'], 422);
        }

        if (!$allDay) {
            $startTime = $data['start_time'] ?? null;
            $endTime = $data['end_time'] ?? null;
            if (!$startTime || !$endTime) {
                return response()->json(['message' => 'Debes completar hora inicio y hora fin'], 422);
            }
            $schedule = $this->loadScheduleForUser((int) $user->id);
            if (!$schedule || empty($schedule->start_time) || empty($schedule->end_time)) {
                return response()->json(['message' => 'No hay horario laboral configurado para este usuario'], 422);
            }
            $workStart = substr((string) $schedule->start_time, 0, 5);
            $workEnd = substr((string) $schedule->end_time, 0, 5);
            [$sh, $sm] = array_map('intval', explode(':', $startTime));
            [$eh, $em] = array_map('intval', explode(':', $endTime));
            $start->setTime($sh, $sm, 0);
            $end->setTime($eh, $em, 0);
            if ($end->lte($start)) {
                return response()->json(['message' => 'El rango horario no es valido'], 422);
            }
            if ($startTime < $workStart || $endTime > $workEnd) {
                return response()->json(['message' => "Debes seleccionar un horario dentro de la jornada laboral ({$workStart} - {$workEnd})"], 422);
            }
        }

        $scheduleDays = $this->loadScheduleDaysForUser((int) $user->id);
        $workingDays = $this->calculateWorkingDays($start->copy()->startOfDay(), $end->copy()->startOfDay(), $scheduleDays);

        if ((int) $data['event_type_id'] === 2) {
            $year = (int) $start->year;
            $existingSet = $this->vacationWorkingDaysSetForYear((int) $user->id, $year, $scheduleDays, null);
            $combinedSet = $this->appendWorkingRangeToSet($existingSet, $start, $end, $scheduleDays);
            $requestedTotal = count($combinedSet);
            $limit = (int) ($user->vacation_days_total ?? 0);
            if ($requestedTotal > $limit) {
                return response()->json([
                    'message' => "No puedes solicitar mas de {$limit} dias de vacaciones al ano",
                    'requested_days' => $requestedTotal,
                    'available_days' => max(0, $limit - count($existingSet)),
                ], 422);
            }
        }

        $id = DB::table('events')->insertGetId([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'user_id' => $user->id,
            'event_type_id' => (int) $data['event_type_id'],
            'all_day' => $allDay,
            'color' => $data['color'] ?? null,
            'approval_status' => 'pending',
            'approval_comment' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        event(new TimeOffRequestUpdated((int) $id, (int) $user->id, 'created'));

        return response()->json([
            'status' => 'ok',
            'event_id' => $id,
            'working_days' => $workingDays,
            'range' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'schedule_days' => $scheduleDays,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $event = DB::table('events')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$event) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        $data = $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'event_type_id' => 'required|integer|exists:event_types,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'all_day' => 'nullable|boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'color' => 'nullable|string|max:20',
        ]);

        $allDay = (bool) ($data['all_day'] ?? true);
        $start = Carbon::createFromFormat('Y-m-d', $data['start_date'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end_date'])->endOfDay();

        $today = Carbon::today();
        if ($start->lt($today)) {
            return response()->json(['message' => 'No se pueden solicitar dias pasados'], 422);
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $isMultiDay = $start->toDateString() !== $end->toDateString();
        if ($isMultiDay && !$allDay) {
            return response()->json(['message' => 'Si seleccionas varios dias, solo se permite evento de dia completo'], 422);
        }

        if (!$allDay) {
            $startTime = $data['start_time'] ?? null;
            $endTime = $data['end_time'] ?? null;
            if (!$startTime || !$endTime) {
                return response()->json(['message' => 'Debes completar hora inicio y hora fin'], 422);
            }
            $schedule = $this->loadScheduleForUser((int) $user->id);
            if (!$schedule || empty($schedule->start_time) || empty($schedule->end_time)) {
                return response()->json(['message' => 'No hay horario laboral configurado para este usuario'], 422);
            }
            $workStart = substr((string) $schedule->start_time, 0, 5);
            $workEnd = substr((string) $schedule->end_time, 0, 5);
            [$sh, $sm] = array_map('intval', explode(':', $startTime));
            [$eh, $em] = array_map('intval', explode(':', $endTime));
            $start->setTime($sh, $sm, 0);
            $end->setTime($eh, $em, 0);
            if ($end->lte($start)) {
                return response()->json(['message' => 'El rango horario no es valido'], 422);
            }
            if ($startTime < $workStart || $endTime > $workEnd) {
                return response()->json(['message' => "Debes seleccionar un horario dentro de la jornada laboral ({$workStart} - {$workEnd})"], 422);
            }
        }

        $scheduleDays = $this->loadScheduleDaysForUser((int) $user->id);
        $workingDays = $this->calculateWorkingDays($start->copy()->startOfDay(), $end->copy()->startOfDay(), $scheduleDays);

        if ((int) $data['event_type_id'] === 2) {
            $year = (int) $start->year;
            $existingSet = $this->vacationWorkingDaysSetForYear((int) $user->id, $year, $scheduleDays, (int) $event->id);
            $combinedSet = $this->appendWorkingRangeToSet($existingSet, $start, $end, $scheduleDays);
            $requestedTotal = count($combinedSet);
            $limit = (int) ($user->vacation_days_total ?? 0);
            if ($requestedTotal > $limit) {
                return response()->json([
                    'message' => "No puedes solicitar mas de {$limit} dias de vacaciones al ano",
                    'requested_days' => $requestedTotal,
                    'available_days' => max(0, $limit - count($existingSet)),
                ], 422);
            }
        }

        DB::table('events')
            ->where('id', $event->id)
            ->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_at' => $start->toDateTimeString(),
                'end_at' => $end->toDateTimeString(),
                'event_type_id' => (int) $data['event_type_id'],
                'all_day' => $allDay,
                'color' => $data['color'] ?? null,
                'approval_status' => 'pending',
                'approval_comment' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'updated_at' => now(),
            ]);
        event(new TimeOffRequestUpdated((int) $event->id, (int) $user->id, 'updated'));

        return response()->json([
            'status' => 'ok',
            'event_id' => $event->id,
            'working_days' => $workingDays,
            'range' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'schedule_days' => $scheduleDays,
        ]);
    }

    public function requests(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);
        $canReview = $this->canReviewRequests($user);

        $statusFilter = strtolower(trim((string) $request->query('status', 'pending')));
        $allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'pending';
        }

        $query = DB::table('events')
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->join('users', 'events.user_id', '=', 'users.id')
            ->leftJoin('users as reviewer', 'events.reviewed_by_user_id', '=', 'reviewer.id')
            ->whereNull('events.deleted_at')
            ->orderByRaw("CASE events.approval_status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderBy('events.created_at', 'desc')
            ->select(
                'events.id',
                'events.title',
                'events.description',
                'events.start_at',
                'events.end_at',
                'events.all_day',
                'events.color',
                'events.approval_status',
                'events.approval_comment',
                'events.reviewed_at',
                'events.created_at',
                'event_types.id as event_type_id',
                'event_types.name as event_type_name',
                'users.id as requester_id',
                'users.first_name as requester_first_name',
                'users.last_name as requester_last_name',
                'users.name as requester_name',
                'users.team_id as requester_team_id',
                'reviewer.first_name as reviewer_first_name',
                'reviewer.last_name as reviewer_last_name',
                'reviewer.name as reviewer_name'
            );

        if (!$canReview) {
            $query->where('events.user_id', $user->id);
        }

        if ($statusFilter !== 'all') {
            $query->where('events.approval_status', $statusFilter);
        }

        $rows = $query->get()->map(function ($row) {
            $start = Carbon::parse($row->start_at);
            $end = $row->end_at ? Carbon::parse($row->end_at) : $start->copy();
            $status = $this->normalizeApprovalStatus($row->approval_status ?? null);
            $requesterFullName = trim(($row->requester_first_name ?? $row->requester_name ?? '') . ' ' . ($row->requester_last_name ?? ''));
            $reviewerFullName = trim(($row->reviewer_first_name ?? $row->reviewer_name ?? '') . ' ' . ($row->reviewer_last_name ?? ''));

            return [
                'id' => $row->id,
                'title' => $row->title,
                'description' => $row->description,
                'event_type_id' => $row->event_type_id,
                'event_type_name' => $row->event_type_name,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'all_day' => (bool) $row->all_day,
                'color' => $row->color,
                'display_color' => $this->eventDisplayColor($status, $row->color),
                'approval_status' => $status,
                'approval_comment' => $row->approval_comment,
                'reviewed_at' => $row->reviewed_at,
                'created_at' => $row->created_at,
                'requester' => [
                    'id' => $row->requester_id,
                    'full_name' => $requesterFullName !== '' ? $requesterFullName : ('Usuario #' . $row->requester_id),
                    'team_id' => $row->requester_team_id,
                ],
                'reviewer_name' => $reviewerFullName !== '' ? $reviewerFullName : null,
            ];
        })->values();

        return response()->json([
            'can_review' => $canReview,
            'status_filter' => $statusFilter,
            'requests' => $rows,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    public function review(Request $request, int $id)
    {
        $user = $request->user();
        if (!$this->canReviewRequests($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:2000',
        ]);

        $decision = strtolower(trim((string) $data['decision']));
        $comment = trim((string) ($data['comment'] ?? ''));

        if ($decision === 'rejected' && $comment === '') {
            return response()->json(['message' => 'Debes agregar un comentario al desaprobar'], 422);
        }

        $event = DB::table('events')
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.id', $id)
            ->whereNull('events.deleted_at')
            ->first([
                'events.id',
                'events.user_id',
                'events.title',
                'events.description',
                'events.start_at',
                'events.end_at',
                'events.event_type_id',
                'event_types.name as event_type_name',
            ]);

        if (!$event) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        DB::transaction(function () use ($id, $decision, $comment, $user, $event) {
            DB::table('events')
                ->where('id', $id)
                ->update([
                    'approval_status' => $decision,
                    'approval_comment' => $decision === 'rejected' ? $comment : null,
                    'reviewed_by_user_id' => $user->id,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            $eventTypeName = strtolower(trim((string) ($event->event_type_name ?? '')));
            if ($decision === 'approved' && str_contains($eventTypeName, 'fichaj')) {
                $this->applyApprovedAttendanceRequest($event);
            }
        });
        event(new TimeOffRequestUpdated((int) $id, (int) $event->user_id, 'reviewed'));

        return response()->json([
            'status' => 'ok',
            'event_id' => $id,
            'decision' => $decision,
        ])->header('Access-Control-Allow-Origin', '*');
    }
}
