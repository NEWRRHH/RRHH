<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\UserSchedule;
use App\Support\WorkTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Work-schedule helpers shared by attendance, time-off and dashboard.
 */
class ScheduleService
{
    public function normalizeScheduleDays($days): array
    {
        // Default: Monday to Friday if schedule has no configured days.
        $default = ['L', 'M', 'X', 'J', 'V'];
        if (empty($days)) return $default;

        $decoded = $days;
        if (is_string($days)) {
            $decoded = json_decode($days, true);
        }
        if (!is_array($decoded)) return $default;

        $allowed = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
        $normalized = array_values(array_intersect($allowed, array_map('strtoupper', $decoded)));
        return count($normalized) ? $normalized : $default;
    }

    public function scheduleDailyMinutes(?object $schedule): int
    {
        if (!$schedule || empty($schedule->start_time) || empty($schedule->end_time)) return 0;
        try {
            $start = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time);
        } catch (\Exception $e) {
            return 0;
        }

        $mins = $start->diffInMinutes($end, false);
        if ($mins < 0) {
            $mins += 24 * 60;
        }
        // Standard office shift usually includes 1h break (e.g. 09:00-18:00 => 8h target).
        // Apply this only on long shifts to avoid affecting short/part-time schedules.
        if ($mins >= 6 * 60) {
            $mins -= 60;
        }
        return max(0, (int) $mins);
    }

    public function formatScheduleTemplate(object $schedule): array
    {
        $days = $this->normalizeScheduleDays($schedule->days ?? null);
        $start = isset($schedule->start_time) ? substr((string) $schedule->start_time, 0, 5) : null;
        $end = isset($schedule->end_time) ? substr((string) $schedule->end_time, 0, 5) : null;

        return [
            'id' => (int) $schedule->id,
            'start_time' => $start,
            'end_time' => $end,
            'days' => $days,
            'label' => trim(implode('-', [$start ?: '--:--', $end ?: '--:--']) . ' | ' . implode(', ', $days)),
        ];
    }

    public function loadScheduleTemplatesCollection()
    {
        return Schedule::table()
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->get(['id', 'start_time', 'end_time', 'days']);
    }

    public function scheduleTemplateExists(string $startTime, string $endTime, array $days, ?int $excludeId = null): bool
    {
        $targetStart = substr($startTime, 0, 5);
        $targetEnd = substr($endTime, 0, 5);
        $targetDays = $this->normalizeScheduleDays($days);

        $query = Schedule::table()
            ->whereNull('deleted_at');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $rows = $query->get(['id', 'start_time', 'end_time', 'days']);

        foreach ($rows as $row) {
            $rowStart = isset($row->start_time) ? substr((string) $row->start_time, 0, 5) : '';
            $rowEnd = isset($row->end_time) ? substr((string) $row->end_time, 0, 5) : '';
            $rowDays = $this->normalizeScheduleDays($row->days ?? null);

            if ($rowStart === $targetStart && $rowEnd === $targetEnd && $rowDays === $targetDays) {
                return true;
            }
        }

        return false;
    }

    public function getScheduleTemplatesByIds(array $templateIds)
    {
        $ids = array_values(array_unique(array_map('intval', $templateIds)));
        if (!count($ids)) {
            return collect();
        }

        return Schedule::table()
            ->whereNull('deleted_at')
            ->whereIn('id', $ids)
            ->orderBy('id', 'asc')
            ->get(['id', 'start_time', 'end_time', 'days']);
    }

    public function hasOverlappingDaysInTemplates($templates): bool
    {
        $seen = [];
        foreach ($templates as $tpl) {
            $days = $this->normalizeScheduleDays($tpl->days ?? null);
            foreach ($days as $day) {
                if (isset($seen[$day])) {
                    return true;
                }
                $seen[$day] = true;
            }
        }

        return false;
    }

    public function getAssignedScheduleTemplateIds(int $userId): array
    {
        return UserSchedule::table()
            ->where('user_id', $userId)
            ->orderBy('id', 'asc')
            ->pluck('schedule_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function syncUserScheduleTemplates(int $userId, $templates): void
    {
        UserSchedule::table()->where('user_id', $userId)->delete();

        if (!count($templates)) {
            return;
        }

        $now = now();
        $hasDaysColumn = Schema::hasColumn('user_schedules', 'days');
        $rows = [];
        foreach ($templates as $tpl) {
            $row = [
                'user_id' => $userId,
                'schedule_id' => (int) $tpl->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasDaysColumn) {
                $row['days'] = json_encode($this->normalizeScheduleDays($tpl->days ?? null));
            }
            $rows[] = $row;
        }

        if (count($rows)) {
            UserSchedule::table()->insert($rows);
        }
    }

    public function loadAssignedScheduleTemplatesForUser(int $userId)
    {
        return UserSchedule::table()
            ->join('schedules', 'user_schedules.schedule_id', '=', 'schedules.id')
            ->where('user_schedules.user_id', $userId)
            ->whereNull('schedules.deleted_at')
            ->orderBy('user_schedules.id', 'asc')
            ->get(['schedules.id', 'schedules.start_time', 'schedules.end_time', 'schedules.days'])
            ->map(fn ($s) => $this->formatScheduleTemplate($s))
            ->values();
    }

    public function loadAssignedScheduleTemplatesCollectionForUser(int $userId)
    {
        return UserSchedule::table()
            ->join('schedules', 'user_schedules.schedule_id', '=', 'schedules.id')
            ->where('user_schedules.user_id', $userId)
            ->whereNull('schedules.deleted_at')
            ->orderBy('user_schedules.id', 'asc')
            ->get(['schedules.id', 'schedules.start_time', 'schedules.end_time', 'schedules.days']);
    }

    public function buildEffectiveScheduleFromTemplates($templates): ?object
    {
        if (!count($templates)) {
            return null;
        }

        $unionDays = [];
        $earliestStart = null;
        $latestEnd = null;

        foreach ($templates as $tpl) {
            $days = $this->normalizeScheduleDays($tpl->days ?? null);
            $unionDays = array_values(array_unique(array_merge($unionDays, $days)));

            $start = isset($tpl->start_time) ? substr((string) $tpl->start_time, 0, 5) : null;
            $end = isset($tpl->end_time) ? substr((string) $tpl->end_time, 0, 5) : null;

            if ($start) {
                if ($earliestStart === null || WorkTime::timeStringToMinutes($start) < WorkTime::timeStringToMinutes($earliestStart)) {
                    $earliestStart = $start;
                }
            }
            if ($end) {
                if ($latestEnd === null || WorkTime::timeStringToMinutes($end) > WorkTime::timeStringToMinutes($latestEnd)) {
                    $latestEnd = $end;
                }
            }
        }

        return (object) [
            'id' => null,
            'start_time' => $earliestStart,
            'end_time' => $latestEnd,
            'days' => $unionDays,
        ];
    }

    public function loadEffectiveScheduleForUser(int $userId): ?object
    {
        $templates = $this->loadAssignedScheduleTemplatesCollectionForUser($userId);
        $fromTemplates = $this->buildEffectiveScheduleFromTemplates($templates);
        return $fromTemplates;
    }

    public function calculateWorkingDays(Carbon $start, Carbon $end, array $scheduleDays): int
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

    public function loadScheduleDaysForUser(int $userId): array
    {
        $schedule = $this->loadEffectiveScheduleForUser($userId);
        return $this->normalizeScheduleDays($schedule->days ?? null);
    }
}
