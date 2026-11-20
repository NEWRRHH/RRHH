<?php

namespace App\Services;

use App\Events\AttendanceStatusChanged;
use App\Events\CompanyAttendanceChanged;
use App\Models\Attendance;
use App\Support\Tenancy\Tenant;
use App\Support\WorkTime;
use Carbon\Carbon;

/**
 * Clock-ins the employee forgot to close: once their day is over the system
 * closes them as if the employee had clocked out at the end of their
 * schedule, and leaves a note on the record (Reportes, monthly PDF) plus a
 * notice the employee sees on the dashboard until they dismiss it.
 *
 * - end time = end of the employee's schedule that day; started after it
 *   (or no schedule): the start time + the daily hours, never past 23:59:59
 * - an unresumed pause counts as paused until that end
 */
class AttendanceAutoClose
{
    public function __construct(private ScheduleService $schedules, private AttendanceService $attendance)
    {
    }

    /**
     * Closes every open clock-in of the company that started before today.
     *
     * @return int records closed
     */
    public function closeCompany(int $companyId, ?int $userId = null): int
    {
        return Tenant::run($companyId, function () use ($companyId, $userId) {
            $today = Carbon::today()->toDateString();
            $rows = Attendance::table()
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->whereDate('date', '<', $today)
                ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
                ->get(['id', 'user_id', 'date', 'start_time', 'pause_time', 'resume_time']);

            $closed = 0;
            $users = [];
            foreach ($rows as $row) {
                $date = Carbon::parse($row->date)->toDateString();
                [$end, $reason] = $this->closingTime($row, $date);

                $worked = $this->attendance->workedSecondsWithPause($row, $end);
                Attendance::table()->where('id', $row->id)->update([
                    'end_time' => $end->format('H:i:s'),
                    'end_date' => $date,
                    'hours_worked' => WorkTime::secondsToMysqlTime($worked),
                    'status' => 'fuera_trabajo',
                    'auto_closed_at' => now(),
                    'auto_close_seen_at' => null,
                    'note' => "Salida no fichada: cerrado automáticamente a las {$end->format('H:i')} ({$reason}).",
                    'updated_at' => now(),
                ]);
                $closed++;
                $users[(int) $row->user_id] = true;
            }

            foreach (array_keys($users) as $userId) {
                try {
                    event(new AttendanceStatusChanged($userId, AttendanceStatusChanged::IDLE));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            if ($closed) {
                try {
                    event(new CompanyAttendanceChanged($companyId));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $closed;
        });
    }

    /** @return array{0: Carbon, 1: string} */
    private function closingTime(object $row, string $date): array
    {
        $start = Carbon::parse($date . ' ' . ($row->start_time ?: '00:00:00'));
        $dayEnd = Carbon::parse($date . ' 23:59:59');
        $schedule = $this->schedules->loadEffectiveScheduleForUser((int) $row->user_id);

        if ($schedule && ! empty($schedule->end_time)) {
            $scheduleEnd = Carbon::parse($date . ' ' . $schedule->end_time);
            if ($scheduleEnd->gt($start)) {
                return [$scheduleEnd, 'fin de su jornada'];
            }
        }

        $minutes = $this->schedules->scheduleDailyMinutes($schedule) ?: 8 * 60;
        $end = $start->copy()->addMinutes($minutes);

        return [$end->gt($dayEnd) ? $dayEnd : $end, 'horas de su jornada desde la entrada'];
    }
}
