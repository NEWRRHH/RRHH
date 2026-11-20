<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Team;
use App\Models\User;
use App\Services\PayrollSync;
use App\Support\WorkTime;
use Carbon\Carbon;

/**
 * Monthly attendance summary of one employee (the data of the PDF that goes
 * to Documentos > Asistencia and by email, like Sesame's "resumen mensual").
 *
 * Per day: clock-in / pause / resume / clock-out, worked vs expected time
 * and what the day was (worked, remote, vacation, holiday, justified or
 * unjustified absence). Must run inside the employee's tenant.
 */
class AttendanceSummaryService
{
    public const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    public const WEEKDAYS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public function __construct(private ScheduleService $schedules)
    {
    }

    public static function monthLabel(Carbon $month): string
    {
        return ucfirst(self::MONTHS[$month->month - 1]) . ' ' . $month->year;
    }

    public static function signedHHMM(int $minutes): string
    {
        return ($minutes < 0 ? '-' : ($minutes > 0 ? '+' : '')) . WorkTime::formatMinutesAsHHMM(abs($minutes));
    }

    public function build(User $user, Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth()->startOfDay();
        $monthEnd = $month->copy()->endOfMonth()->startOfDay();
        $today = Carbon::today();

        $schedule = $this->schedules->loadEffectiveScheduleForUser((int) $user->id);
        $scheduleDays = $this->schedules->normalizeScheduleDays($schedule->days ?? null);
        $dailyTarget = $this->schedules->scheduleDailyMinutes($schedule);
        $hireDate = $user->hire_date ? Carbon::parse($user->hire_date)->toDateString() : null;

        // latest row of each day (same rule as Reportes)
        $attendanceByDate = [];
        $rows = Attendance::table()
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->orderBy('date')
            ->orderBy('created_at', 'desc')
            ->get(['date', 'start_time', 'pause_time', 'resume_time', 'end_time', 'hours_worked', 'auto_closed_at']);
        foreach ($rows as $row) {
            $key = Carbon::parse($row->date)->toDateString();
            $attendanceByDate[$key] ??= $row;
        }

        // approved requests (all day) per date
        $eventsByDate = [];
        $events = Event::table()
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.user_id', $user->id)
            ->whereNull('events.deleted_at')
            ->where(fn ($q) => $q->whereNull('events.approval_status')->orWhere('events.approval_status', 'approved'))
            ->where(fn ($q) => $q->whereNull('event_types.code')->orWhere('event_types.code', '<>', EventType::ATTENDANCE_REQUEST))
            ->where('events.start_at', '<=', $monthEnd->copy()->endOfDay()->toDateTimeString())
            ->where(fn ($q) => $q->where('events.end_at', '>=', $monthStart->toDateTimeString())->orWhereNull('events.end_at'))
            ->get(['events.start_at', 'events.end_at', 'events.all_day', 'event_types.code', 'event_types.name']);
        foreach ($events as $event) {
            $from = Carbon::parse($event->start_at)->startOfDay();
            $to = $event->end_at ? Carbon::parse($event->end_at)->startOfDay() : $from->copy();
            for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
                $eventsByDate[$cursor->toDateString()][] = [
                    'code' => $event->code,
                    'name' => (string) $event->name,
                    'all_day' => (bool) $event->all_day,
                ];
            }
        }

        $alwaysRemote = $user->work_mode === User::WORK_MODE_REMOTE;
        $totals = [
            'worked' => 0, 'expected' => 0, 'days_worked' => 0, 'remote_days' => 0,
            'vacation_days' => 0, 'holidays' => 0, 'justified_absences' => 0, 'absences' => 0,
        ];
        $days = [];

        for ($cursor = $monthStart->copy(); $cursor->lte($monthEnd); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $letter = ScheduleService::WEEK_LETTERS[$cursor->dayOfWeekIso];
            $isScheduled = in_array($letter, $scheduleDays, true);
            $holidayInfo = $this->schedules->holidayInfo($cursor);
            $isHoliday = $isScheduled && $this->schedules->holidayName($cursor) !== null;
            $isEmployed = $hireDate === null || $key >= $hireDate;
            $isFuture = $cursor->gt($today);
            $att = $attendanceByDate[$key] ?? null;
            $dayEvents = $eventsByDate[$key] ?? [];

            $fullDay = array_values(array_filter($dayEvents, fn ($e) => $e['all_day']));
            $vacation = collect($fullDay)->firstWhere('code', EventType::VACATION);
            $remote = $alwaysRemote || collect($dayEvents)->contains(fn ($e) => $e['code'] === EventType::REMOTE_WORK);
            $justified = collect($fullDay)->first(fn ($e) => ! in_array($e['code'], [EventType::VACATION, EventType::REMOTE_WORK, EventType::MEETING], true));

            $worked = $att && $att->hours_worked ? WorkTime::timeStringToMinutes((string) $att->hours_worked) : 0;
            $hasClock = $att && ($att->start_time || $att->end_time || $att->hours_worked);

            $status = 'rest';
            $expected = 0;
            $notes = [];
            if (! $isEmployed) {
                $status = 'not_employed';
                $notes[] = 'Antes del alta';
            } elseif ($isHoliday) {
                $status = 'holiday';
                $notes[] = 'Festivo: ' . $holidayInfo['name'];
                $totals['holidays']++;
            } elseif ($isScheduled && $vacation) {
                $status = 'vacation';
                $notes[] = $vacation['name'];
                $totals['vacation_days']++;
            } elseif ($isScheduled && $justified) {
                $status = 'justified';
                $notes[] = $justified['name'];
                $totals['justified_absences']++;
            } elseif ($isScheduled) {
                $expected = $isFuture ? 0 : $dailyTarget;
                if ($hasClock) {
                    $status = $remote ? 'remote' : 'worked';
                } elseif ($isFuture) {
                    $status = 'future';
                } else {
                    $status = 'absence';
                    $notes[] = 'Sin fichaje';
                    $totals['absences']++;
                }
            } elseif ($hasClock) {
                $status = $remote ? 'remote' : 'worked'; // worked on a rest day
                $notes[] = 'Día no laborable';
            }

            if ($holidayInfo && ($holidayInfo['working'] ?? false)) {
                $notes[] = 'Festivo laborable: ' . $holidayInfo['name'];
            }
            foreach ($dayEvents as $e) {
                if (! $e['all_day'] && $e['code'] !== EventType::REMOTE_WORK) {
                    $notes[] = $e['name'] . ' (parcial)';
                }
            }
            if ($att && $att->auto_closed_at) {
                $notes[] = 'Salida no fichada (cierre automático)';
            }
            if ($hasClock) {
                $totals['days_worked']++;
                if ($remote) {
                    $totals['remote_days']++;
                    $notes[] = 'Teletrabajo';
                }
            }

            $totals['worked'] += $worked;
            $totals['expected'] += $expected;

            // rest days without anything are left out of the table
            if ($status === 'rest' && ! $hasClock && ! $notes) {
                continue;
            }

            $days[] = [
                'date' => $key,
                'label' => $cursor->format('d/m'),
                'weekday' => self::WEEKDAYS[$cursor->dayOfWeekIso],
                'status' => $status,
                'start' => $att?->start_time ? substr((string) $att->start_time, 0, 5) : null,
                'pause' => $att?->pause_time ? substr((string) $att->pause_time, 0, 5) : null,
                'resume' => $att?->resume_time ? substr((string) $att->resume_time, 0, 5) : null,
                'end' => $att?->end_time ? substr((string) $att->end_time, 0, 5) : null,
                'worked' => $worked,
                'expected' => $expected,
                'balance' => $worked - $expected,
                'notes' => implode(' · ', array_unique($notes)),
            ];
        }

        $company = $user->company_id ? Company::query()->find($user->company_id) : null;
        $teamName = $user->team_id ? Team::table()->where('id', $user->team_id)->value('name') : null;

        return [
            'company' => ['name' => $company?->name ?? '', 'country_code' => $company?->country_code],
            'employee' => [
                'id' => $user->id,
                'name' => PayrollSync::fullName($user),
                'email' => $user->email,
                'dni' => $user->dni,
                'nss' => $user->social_security_number,
                'team' => $teamName,
                'hire_date' => $hireDate ? Carbon::parse($hireDate)->format('d/m/Y') : null,
                'work_mode' => $user->work_mode,
            ],
            'period' => [
                'month' => $monthStart->format('Y-m'),
                'label' => self::monthLabel($monthStart),
                'from' => $monthStart->format('d/m/Y'),
                'to' => $monthEnd->format('d/m/Y'),
                'partial' => $monthEnd->gte($today),
            ],
            'schedule' => [
                'start' => isset($schedule->start_time) ? substr((string) $schedule->start_time, 0, 5) : null,
                'end' => isset($schedule->end_time) ? substr((string) $schedule->end_time, 0, 5) : null,
                'days' => $scheduleDays,
                'daily_minutes' => $dailyTarget,
            ],
            'days' => $days,
            'totals' => $totals + ['balance' => $totals['worked'] - $totals['expected']],
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }
}
