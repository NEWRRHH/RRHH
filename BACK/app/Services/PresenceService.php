<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Carbon\Carbon;

/**
 * Where each person of the company is today:
 * - remote:   work_mode "remote" (always), or approved "Teletrabajo" covering
 *             today for people without a fixed mode (house icon)
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

        // modalidad (users.work_mode): "remote" = always remote, "office" =
        // never remote (old requests ignored), null = remote on approved days
        $modes = User::query()->whereNull('disabled_at')->whereNotNull('work_mode')->pluck('work_mode', 'id');
        $alwaysRemote = $modes->filter(fn ($m) => $m === User::WORK_MODE_REMOTE)->keys()->map(fn ($id) => (int) $id)->all();
        $officeOnly = $modes->filter(fn ($m) => $m === User::WORK_MODE_OFFICE)->keys()->map(fn ($id) => (int) $id)->all();

        // vacation wins over remote work
        $vacation = $approvedToday(EventType::VACATION);
        $remote = array_values(array_diff(
            array_merge(array_diff($approvedToday(EventType::REMOTE_WORK), $officeOnly), $alwaysRemote),
            $vacation,
        ));

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
