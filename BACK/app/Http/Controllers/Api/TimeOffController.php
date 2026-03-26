<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeOffController extends Controller
{
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
                'events.event_type_id',
                'event_types.name as event_type_name'
            )
            ->get()
            ->map(function ($row) {
                $startAt = Carbon::parse($row->start_at);
                $endAt = $row->end_at ? Carbon::parse($row->end_at) : $startAt->copy();
                $startDate = $startAt->toDateString();
                $endDate = $endAt->toDateString();
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
                    'event_type_id' => $row->event_type_id,
                    'event_type_name' => $row->event_type_name,
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

        $id = DB::table('events')->insertGetId([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_at' => $start->toDateTimeString(),
            'end_at' => $end->toDateTimeString(),
            'user_id' => $user->id,
            'event_type_id' => (int) $data['event_type_id'],
            'all_day' => $allDay,
            'color' => $data['color'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
                'updated_at' => now(),
            ]);

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
}
