<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Birthdays and work anniversaries (years since hire_date).
 */
class CelebrationService
{
    /**
     * Next occurrence (today included) of a yearly date. Feb 29 falls on
     * Feb 28 in non-leap years so it is never skipped.
     */
    public static function nextOccurrence(Carbon $date, Carbon $today): Carbon
    {
        $next = self::inYear($date, $today->year);
        if ($next->lt($today)) {
            $next = self::inYear($date, $today->year + 1);
        }

        return $next;
    }

    private static function inYear(Carbon $date, int $year): Carbon
    {
        $day = $date->month === 2 && $date->day === 29 && ! Carbon::create($year)->isLeapYear() ? 28 : $date->day;

        return Carbon::create($year, $date->month, $day)->startOfDay();
    }

    private static function parse(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The `$limit` next celebrations of the company (Configuración > Límites),
     * grouped one row per person: a row only shows the events that made it
     * into those next ones (birthday and/or anniversary, the other is null).
     * Someone whose birthday just passed and whose anniversary is far away
     * is not listed.
     */
    public function upcoming(?Carbon $today = null, ?int $limit = null): Collection
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        $events = User::query()
            ->where(fn ($q) => $q->whereNotNull('birth_date')->orWhereNotNull('hire_date'))
            ->get()
            ->flatMap(fn (User $u) => $this->eventsFor($u, $today))
            ->sortBy(fn ($e) => $e['date'] . ($e['type'] === 'birthday' ? '0' : '1') . str_pad((string) $e['user_id'], 10, '0', STR_PAD_LEFT))
            ->values();

        if ($limit !== null) {
            $events = $events->take($limit);
        }

        $pick = fn ($e) => $e ? ['date' => $e['date'], 'years' => $e['years'], 'is_today' => $e['is_today']] : null;

        // groupBy keeps the order of each person's first (soonest) event
        return $events->groupBy('user_id')->map(function (Collection $personEvents) use ($pick) {
            $byType = $personEvents->keyBy('type');
            $first = $personEvents->first();

            return [
                'id' => $first['user_id'],
                'user_id' => $first['user_id'],
                'first_name' => $first['first_name'],
                'last_name' => $first['last_name'],
                'full_name' => $first['full_name'],
                'photo' => $first['photo'],
                'birthday' => $pick($byType->get('birthday')),
                'anniversary' => $pick($byType->get('anniversary')),
                // soonest shown event, used for the "today" highlight
                'date' => $first['date'],
                'is_today' => $personEvents->contains('is_today', true),
            ];
        })->values();
    }

    /**
     * Celebrations of this user happening today (shown after login), with the
     * company's texts (Configuración > Festejos) already filled in.
     */
    public function todayFor(User $user, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $events = array_filter($this->eventsFor($user, $today), fn ($e) => $e['is_today']);
        if (! $events) {
            return [];
        }

        $layouts = app(DashboardLayoutService::class);
        $firstName = (string) (preg_split('/\s+/', trim((string) ($user->first_name ?: $user->name)))[0] ?? '');

        return array_values(array_map(
            fn ($e) => ['type' => $e['type'], 'years' => $e['years']]
                + $layouts->celebrationText($e['type'], $firstName, $e['years']),
            $events
        ));
    }

    private function eventsFor(User $u, Carbon $today): array
    {
        $person = [
            'user_id' => $u->id,
            'first_name' => $u->first_name ?? $u->name ?? null,
            'last_name' => $u->last_name ?? null,
            'full_name' => trim(($u->first_name ?? $u->name ?? '') . ' ' . ($u->last_name ?? '')),
            'photo' => $u->photo ?? $u->profile_photo_path ?? null,
        ];

        $events = [];

        if ($dob = self::parse($u->birth_date)) {
            $next = self::nextOccurrence($dob, $today);
            $events[] = $person + [
                'id' => "birthday-{$u->id}",
                'type' => 'birthday',
                'date' => $next->toDateString(),
                'years' => $next->year - $dob->year,
                'is_today' => $next->equalTo($today),
            ];
        }

        // The hire day itself (0 years) and future hire dates are not anniversaries.
        if (($hire = self::parse($u->hire_date)) && $hire->lt($today)) {
            $next = self::nextOccurrence($hire, $today);
            $years = $next->year - $hire->year;
            if ($years >= 1) {
                $events[] = $person + [
                    'id' => "anniversary-{$u->id}",
                    'type' => 'anniversary',
                    'date' => $next->toDateString(),
                    'years' => $years,
                    'is_today' => $next->equalTo($today),
                ];
            }
        }

        return $events;
    }
}
