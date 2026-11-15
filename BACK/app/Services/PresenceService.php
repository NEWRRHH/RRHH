<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventType;
use Carbon\Carbon;

/**
 * Where each person of the company is today:
 * - remote:   approved "Teletrabajo" covering today (house icon)
 * - vacation: approved "Vacaciones" covering today
 * - office:   clocked in today and not on remote work (building icon)
 * Tenant scoped (current company).
 */
class PresenceService
{
    /**
     * @return array{remote: array<int, int>, vacation: array<int, int>, office: array<int, int>}
     */
    public function today(?Carbon $day = null): array
    {
        $day = ($day ?? Carbon::today())->copy();
        $start = $day->copy()->startOfDay()->toDateTimeString();
        $end = $day->copy()->endOfDay()->toDateTimeString();

        $approvedToday = fn (string $code) => Event::table()
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('event_types.code', $code)
            ->where('events.approval_status', 'approved')
            ->whereNull('events.deleted_at')
            ->where('events.start_at', '<=', $end)
            ->where(fn ($q) => $q->where('events.end_at', '>=', $start)->orWhereNull('events.end_at'))
            ->distinct()
            ->pluck('events.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $remote = $approvedToday(EventType::REMOTE_WORK);
        $vacation = array_values(array_diff($approvedToday(EventType::VACATION), $remote));

        $working = Attendance::table()
            ->whereDate('date', $day->toDateString())
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [
            'remote' => array_values(array_unique($remote)),
            'vacation' => array_values(array_unique($vacation)),
            'office' => array_values(array_diff(array_unique($working), $remote)),
        ];
    }
}
