<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use App\Support\CompanySettings;
use Carbon\Carbon;

/**
 * Yearly vacation allowance rules.
 *
 * - Vacation can only be requested up to the end of the current year, plus
 *   the first N days of next year when the company allows it
 *   (CompanySettings "vacation_next_year_days").
 * - Every request is charged to the allowance of the year it was made in
 *   (events.allowance_year): days taken at the start of next year come out of
 *   this year's days, never out of next year's.
 * - A day consumes allowance when it is a schedule day and not a holiday.
 */
class VacationAllowance
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    /** Year new requests are charged to. */
    public function currentAllowanceYear(): int
    {
        return Carbon::today()->year;
    }

    public function nextYearDays(?int $companyId): int
    {
        return (int) CompanySettings::get($companyId, 'vacation_next_year_days');
    }

    /** Last date that can be requested as vacation today. */
    public function maxRequestDate(?int $companyId): Carbon
    {
        return Carbon::create($this->currentAllowanceYear(), 12, 31)->startOfDay()->addDays($this->nextYearDays($companyId));
    }

    /**
     * Error message when the range can't be requested as vacation, or null.
     */
    public function rangeError(?int $companyId, Carbon $end): ?string
    {
        $max = $this->maxRequestDate($companyId);
        if ($end->copy()->startOfDay()->gt($max)) {
            $year = $this->currentAllowanceYear();

            return $this->nextYearDays($companyId) > 0
                ? "Solo puedes solicitar vacaciones hasta el {$max->format('d/m/Y')} (se descuentan de los días de {$year})."
                : "Solo puedes solicitar vacaciones del año en curso ({$year}).";
        }

        return null;
    }

    /**
     * Days of the allowance year already requested (pending or approved),
     * grouped by event: [event_id => ['Y-m-d', ...]].
     *
     * @return array<int, array<int, string>>
     */
    public function usedDatesByEvent(User $user, int $year, array $scheduleDays, ?int $excludeEventId = null): array
    {
        $rows = Event::table()
            ->where('user_id', $user->id)
            ->where('event_type_id', EventType::idFor(EventType::VACATION))
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('approval_status')->orWhereIn('approval_status', ['approved', 'pending']))
            ->where(function ($q) use ($year) {
                $q->where('allowance_year', $year)
                    // rows created before allowance_year existed
                    ->orWhere(fn ($q2) => $q2->whereNull('allowance_year')
                        ->whereBetween('start_at', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"]));
            })
            ->when($excludeEventId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get(['id', 'start_at', 'end_at']);

        $out = [];
        foreach ($rows as $row) {
            $start = Carbon::parse($row->start_at)->startOfDay();
            $end = $row->end_at ? Carbon::parse($row->end_at)->startOfDay() : $start->copy();
            $dates = [];
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
                if ($this->schedules->isWorkingDate($cursor, $scheduleDays)) {
                    $dates[] = $cursor->toDateString();
                }
            }
            $out[(int) $row->id] = $dates;
        }

        return $out;
    }

    /** @return array<string, true> */
    public function usedDates(User $user, int $year, array $scheduleDays, ?int $excludeEventId = null): array
    {
        $set = [];
        foreach ($this->usedDatesByEvent($user, $year, $scheduleDays, $excludeEventId) as $dates) {
            foreach ($dates as $date) {
                $set[$date] = true;
            }
        }

        return $set;
    }

    /**
     * Checks the yearly limit for a new / edited request. Returns the 422
     * payload when it is exceeded, null when it fits.
     */
    public function limitError(User $user, Carbon $start, Carbon $end, array $scheduleDays, ?int $excludeEventId = null): ?array
    {
        $year = $this->currentAllowanceYear();
        $existing = $this->usedDates($user, $year, $scheduleDays, $excludeEventId);
        $combined = $existing;
        for ($cursor = $start->copy()->startOfDay(); $cursor->lte($end->copy()->startOfDay()); $cursor->addDay()) {
            if ($this->schedules->isWorkingDate($cursor, $scheduleDays)) {
                $combined[$cursor->toDateString()] = true;
            }
        }

        $limit = (int) ($user->vacation_days_total ?? 0);
        if (count($combined) <= $limit) {
            return null;
        }

        return [
            'message' => $limit === 0
                ? 'No tienes días de vacaciones asignados. Consulta con RRHH.'
                : "No puedes solicitar más de {$limit} días de vacaciones al año",
            'requested_days' => count($combined),
            'available_days' => max(0, $limit - count($existing)),
        ];
    }
}
