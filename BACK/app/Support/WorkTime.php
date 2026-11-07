<?php

namespace App\Support;

/**
 * Pure time/duration formatting helpers.
 */
final class WorkTime
{
    public static function timeStringToMinutes(?string $time): int
    {
        if (!$time) return 0;
        $parts = explode(':', $time);
        if (count($parts) < 2) return 0;
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return max(0, $h * 60 + $m);
    }

    public static function formatMinutesAsHHMM(int $minutes): string
    {
        $m = max(0, $minutes);
        $h = intdiv($m, 60);
        $mm = $m % 60;
        return sprintf('%02d:%02d', $h, $mm);
    }

    public static function formatSecondsAsHHMM(int $seconds): string
    {
        $s = max(0, $seconds);
        $h = intdiv($s, 3600);
        $m = intdiv(($s % 3600), 60);
        return sprintf('%02d:%02d', $h, $m);
    }

    public static function secondsToMysqlTime(int $seconds): string
    {
        $s = max(0, $seconds);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $ss = $s % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $ss);
    }
}
