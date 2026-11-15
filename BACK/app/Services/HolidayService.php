<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;

/**
 * Company holidays of the current tenant, with recurring ones (e.g. 25/12)
 * projected onto every year of the requested range.
 *
 * A holiday can be a day off (default) or a "working" holiday
 * (holidays.is_working_day): the company works it, so it behaves like a
 * normal working day but is still shown as a holiday in reports/calendars.
 */
class HolidayService
{
    /**
     * Every holiday between two dates (inclusive), working ones included.
     *
     * @return array<string, array{name: string, working: bool}> 'Y-m-d' => info
     */
    public function allForRange(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $rows = Holiday::table()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere('recurring', true);
            })
            ->get(['date', 'name', 'recurring', 'is_working_day']);

        $set = [];
        foreach ($rows as $row) {
            $info = ['name' => (string) $row->name, 'working' => (bool) $row->is_working_day];
            $date = Carbon::parse($row->date);
            if (! $row->recurring) {
                $set[$date->toDateString()] = $info;
                continue;
            }
            for ($year = $from->year; $year <= $to->year; $year++) {
                // 29/02 only exists in leap years
                if (! checkdate($date->month, $date->day, $year)) {
                    continue;
                }
                $projected = Carbon::create($year, $date->month, $date->day)->startOfDay();
                if ($projected->betweenIncluded($from, $to)) {
                    $set[$projected->toDateString()] ??= $info;
                }
            }
        }
        ksort($set);

        return $set;
    }

    /**
     * Days off between two dates: holidays the company does NOT work.
     *
     * @return array<string, string> 'Y-m-d' => holiday name
     */
    public function forRange(Carbon $from, Carbon $to): array
    {
        $out = [];
        foreach ($this->allForRange($from, $to) as $date => $info) {
            if (! $info['working']) {
                $out[$date] = $info['name'];
            }
        }

        return $out;
    }

    /**
     * Next holidays from today (working ones flagged).
     *
     * @return array<int, array{date: string, name: string, working: bool}>
     */
    public function upcoming(int $limit = 5): array
    {
        $today = Carbon::today();
        $out = [];
        foreach ($this->allForRange($today, $today->copy()->addYear()) as $date => $info) {
            $out[] = ['date' => $date, 'name' => $info['name'], 'working' => $info['working']];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
