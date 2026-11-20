<?php

namespace App\Http\Controllers\Api;

use App\Events\CompanyAttendanceChanged;
use App\Events\TimeOffRequestUpdated;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use App\Services\ScheduleService;
use App\Support\Tenancy\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Teletrabajo: its own view, separate from Vacaciones, so it never depends on
 * the vacation days. Only employees with modalidad "a pedido"
 * (users.work_mode null) request it; RRHH reviews it in Solicitudes like any
 * other request. The employee picks loose days; each run of consecutive days
 * becomes one request.
 */
class RemoteWorkController extends Controller
{
    private const MAX_DAYS_PER_REQUEST = 31;

    public function __construct(private ScheduleService $schedules)
    {
    }

    /** Why the user can't request remote work, or null when they can. */
    public static function blockReason(User $user): ?string
    {
        if (! $user->hasPermission('remote_work.request')) {
            return 'No tienes permiso para solicitar teletrabajo.';
        }
        if ($user->work_mode === User::WORK_MODE_OFFICE) {
            return 'Tu modalidad de trabajo es solo oficina: no puedes solicitar teletrabajo.';
        }
        if ($user->work_mode === User::WORK_MODE_REMOTE) {
            return 'Tu modalidad de trabajo es teletrabajo: no hace falta solicitarlo.';
        }
        if (! EventType::idFor(EventType::REMOTE_WORK)) {
            return 'La empresa no tiene configurado el tipo de solicitud Teletrabajo.';
        }

        return null;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user->hasPermission('remote_work.view')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $year = (int) ($request->query('year') ?: Carbon::today()->year);
        if ($year < 2000 || $year > 2100) {
            $year = Carbon::today()->year;
        }
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $holidays = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if (($name = $this->schedules->holidayName($cursor)) !== null) {
                $holidays[$cursor->toDateString()] = $name;
            }
        }

        $rows = $this->eventsInRange($user, $start, $end);

        $requests = [];
        $blocked = []; // date => reason (vacation / absence already requested)
        foreach ($rows as $row) {
            $from = Carbon::parse($row->start_at)->startOfDay();
            $to = $row->end_at ? Carbon::parse($row->end_at)->startOfDay() : $from->copy();
            $status = $row->approval_status ?: 'approved';

            if ($row->code === EventType::REMOTE_WORK) {
                $requests[] = [
                    'id' => (int) $row->id,
                    'start_date' => $from->toDateString(),
                    'end_date' => $to->toDateString(),
                    'description' => $row->description,
                    'approval_status' => $status,
                    'approval_comment' => $row->approval_comment,
                    'created_at' => $row->created_at,
                    'can_cancel' => $this->canCancel($row),
                ];
                continue;
            }
            if ($status === 'rejected' || ! $row->all_day) {
                continue; // partial-day permissions don't block remote work
            }
            for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
                $blocked[$cursor->toDateString()] = $row->type_name;
            }
        }

        usort($requests, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        $schedule = $this->schedules->loadEffectiveScheduleForUser((int) $user->id);

        return response()->json([
            'year' => $year,
            'work_mode' => $user->work_mode,
            'can_request' => self::blockReason($user) === null,
            'block_reason' => self::blockReason($user),
            'schedule_days' => $this->schedules->normalizeScheduleDays($schedule->days ?? null),
            'holidays' => (object) $holidays,
            'blocked' => (object) $blocked,
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($reason = self::blockReason($user)) {
            return response()->json(['message' => $reason], $user->hasPermission('remote_work.request') ? 422 : 403);
        }

        $data = $request->validate([
            'dates' => 'required|array|min:1|max:' . self::MAX_DAYS_PER_REQUEST,
            'dates.*' => 'required|date_format:Y-m-d|distinct',
            'description' => 'nullable|string|max:1000',
        ]);

        $dates = collect($data['dates'])->map(fn ($d) => Carbon::createFromFormat('Y-m-d', $d)->startOfDay())->sort()->values();
        $today = Carbon::today();
        $scheduleDays = $this->schedules->loadScheduleDaysForUser((int) $user->id);

        // what is already taken in that range (remote work requested, vacations...)
        $taken = [];
        foreach ($this->eventsInRange($user, $dates->first(), $dates->last()->copy()->endOfDay()) as $row) {
            if (($row->approval_status ?: 'approved') === 'rejected') {
                continue;
            }
            if ($row->code !== EventType::REMOTE_WORK && ! $row->all_day) {
                continue;
            }
            $from = Carbon::parse($row->start_at)->startOfDay();
            $to = $row->end_at ? Carbon::parse($row->end_at)->startOfDay() : $from->copy();
            for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
                $taken[$cursor->toDateString()] = $row->code === EventType::REMOTE_WORK ? 'ya tiene teletrabajo solicitado' : "tiene {$row->type_name}";
            }
        }

        foreach ($dates as $date) {
            $label = $date->format('d/m/Y');
            if ($date->lt($today)) {
                return response()->json(['message' => "No se pueden solicitar días pasados ({$label})."], 422);
            }
            if ($date->gt($today->copy()->addYear())) {
                return response()->json(['message' => "Solo puedes solicitar teletrabajo hasta dentro de un año ({$label})."], 422);
            }
            if (! $this->schedules->isWorkingDate($date, $scheduleDays)) {
                return response()->json(['message' => "El {$label} no es un día laborable."], 422);
            }
            if (isset($taken[$date->toDateString()])) {
                return response()->json(['message' => "El {$label} {$taken[$date->toDateString()]}."], 422);
            }
        }

        // runs of consecutive days -> one request each
        $runs = [];
        foreach ($dates as $date) {
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last][1]->copy()->addDay()->eq($date)) {
                $runs[$last][1] = $date;
            } else {
                $runs[] = [$date, $date];
            }
        }

        $typeId = EventType::idFor(EventType::REMOTE_WORK);
        $name = mb_substr((string) (EventType::table()->where('id', $typeId)->value('name') ?: 'Teletrabajo'), 0, 100);
        $ids = DB::transaction(function () use ($runs, $user, $typeId, $name, $data) {
            $ids = [];
            foreach ($runs as [$from, $to]) {
                $ids[] = Event::table()->insertGetId([
                    'title' => $name,
                    'description' => ($data['description'] ?? null) ?: null,
                    'start_at' => $from->copy()->startOfDay()->toDateTimeString(),
                    'end_at' => $to->copy()->endOfDay()->toDateTimeString(),
                    'user_id' => $user->id,
                    'event_type_id' => $typeId,
                    'all_day' => true,
                    'color' => EventType::colorFor(EventType::REMOTE_WORK),
                    'approval_status' => 'pending',
                    'approval_comment' => null,
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $ids;
        });

        foreach ($ids as $id) {
            event(new TimeOffRequestUpdated((int) $id, (int) $user->id, 'created'));
        }

        return response()->json(['status' => 'ok', 'event_ids' => $ids, 'requests' => count($ids), 'days' => $dates->count()]);
    }

    /** The employee withdraws a request (pending, or approved and not started yet). */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user->hasPermission('remote_work.request')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $row = Event::table()
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.id', $id)
            ->where('events.user_id', $user->id)
            ->where('event_types.code', EventType::REMOTE_WORK)
            ->whereNull('events.deleted_at')
            ->first(['events.id', 'events.start_at', 'events.approval_status']);

        if (! $row) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }
        if (! $this->canCancel($row)) {
            return response()->json(['message' => 'Solo puedes anular solicitudes que todavía no empezaron.'], 422);
        }

        Event::table()->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        event(new TimeOffRequestUpdated($id, (int) $user->id, 'cancelled'));
        if ($row->approval_status === 'approved' && Tenant::id()) {
            try {
                event(new CompanyAttendanceChanged((int) Tenant::id()));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function canCancel(object $row): bool
    {
        $status = $row->approval_status ?? 'pending';
        $start = Carbon::parse($row->start_at)->startOfDay();

        return match ($status) {
            'pending' => $start->gte(Carbon::today()),
            'rejected' => false,
            default => $start->gt(Carbon::today()), // approved: before the day starts
        };
    }

    private function eventsInRange(User $user, Carbon $start, Carbon $end)
    {
        return Event::table()
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.user_id', $user->id)
            ->whereNull('events.deleted_at')
            ->where(fn ($q) => $q->whereNull('event_types.code')->orWhere('event_types.code', '<>', EventType::ATTENDANCE_REQUEST))
            ->where('events.start_at', '<=', $end->toDateTimeString())
            ->where(fn ($q) => $q->where('events.end_at', '>=', $start->toDateTimeString())->orWhereNull('events.end_at'))
            ->orderBy('events.start_at')
            ->get([
                'events.id', 'events.start_at', 'events.end_at', 'events.all_day', 'events.description',
                'events.approval_status', 'events.approval_comment', 'events.created_at',
                'event_types.code', 'event_types.name as type_name',
            ]);
    }
}
