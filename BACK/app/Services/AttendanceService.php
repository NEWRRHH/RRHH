<?php

namespace App\Services;

use App\Models\EventType;
use Carbon\Carbon;

/**
 * Attendance (fichaje) helpers.
 */
class AttendanceService
{
    /**
     * Hash of an API token as stored in users/attendances.session_token.
     * The plain token is never persisted, so a DB leak does not leak sessions.
     */
    public function sessionTokenHash(?string $plainToken): ?string
    {
        return $plainToken ? hash('sha256', $plainToken) : null;
    }

    /**
     * Compute worked seconds for an attendance row, discounting pause span.
     * With the current schema we store one pause/resume pair (latest values).
     */
    public function workedSecondsWithPause(object $att, Carbon $end): int
    {
        try {
            $start = Carbon::parse($att->date . ' ' . $att->start_time);
        } catch (\Exception $e) {
            $start = Carbon::parse($att->start_time);
        }

        $total = max(0, $start->diffInSeconds($end, false));
        $paused = 0;

        if (!empty($att->pause_time)) {
            try {
                $pauseStart = Carbon::parse($att->date . ' ' . $att->pause_time);
            } catch (\Exception $e) {
                $pauseStart = Carbon::parse($att->pause_time);
            }

            // If resume is missing or older than pause, treat as still paused until end.
            $pauseEnd = $end->copy();
            if (!empty($att->resume_time)) {
                try {
                    $resume = Carbon::parse($att->date . ' ' . $att->resume_time);
                } catch (\Exception $e) {
                    $resume = Carbon::parse($att->resume_time);
                }
                if ($resume->greaterThan($pauseStart)) {
                    $pauseEnd = $resume->lessThan($end) ? $resume : $end->copy();
                }
            }

            if ($pauseEnd->greaterThan($pauseStart)) {
                $paused = $pauseStart->diffInSeconds($pauseEnd);
            }
        }

        return max(0, $total - $paused);
    }

    public function attendanceRequestEventTypeId(): int
    {
        $existing = EventType::table()
            ->whereRaw('LOWER(name) = ?', ['fichaje'])
            ->whereNull('deleted_at')
            ->first(['id']);

        if ($existing && isset($existing->id)) {
            return (int) $existing->id;
        }

        return (int) EventType::table()->insertGetId([
            'name' => 'Fichaje',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
