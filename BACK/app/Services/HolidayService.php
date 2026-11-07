<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;

/**
 * Company holidays of the current tenant, with recurring ones (e.g. 25/12)
 * projected onto every year of the requested range.
 */
class HolidayService
{
    /**
     * Holidays between two dates (inclusive).
     *
     * @return array<string, string> 'Y-m-d' => holiday name
     */
    public function forRange(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $rows = Holiday::table()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere('recurring', true);
            })
            ->get(['date', 'name', 'recurring']);

        $set = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->date);
            if (! $row->recurring) {
                $set[$date->toDateString()] = $row->name;
                continue;
            }
            for ($year = $from->year; $year <= $to->year; $year++) {
                // 29/02 only exists in leap years
                if (! checkdate($date->month, $date->day, $year)) {
                    continue;
                }
                $projected = Carbon::create($year, $date->month, $date->day)->startOfDay();
                if ($projected->betweenIncluded($from, $to)) {
                    $set[$projected->toDateString()] ??= $row->name;
                }
            }
        }
        ksort($set);

        return $set;
    }

    /**
     * Next holidays from today.
     *
     * @return array<int, array{date: string, name: string}>
     */
    public function upcoming(int $limit = 5): array
    {
        $today = Carbon::today();
        $out = [];
        foreach ($this->forRange($today, $today->copy()->addYear()) as $date => $name) {
            $out[] = ['date' => $date, 'name' => $name];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
