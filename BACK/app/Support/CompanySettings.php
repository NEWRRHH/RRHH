<?php

namespace App\Support;

use App\Models\Company;

/**
 * HR policies of a company, stored in companies.settings (JSON).
 * Missing keys fall back to the defaults below.
 */
final class CompanySettings
{
    public const DEFAULTS = [
        // days of next year that can be requested as vacation (charged to the current year)
        'vacation_next_year_days' => 0,
        // the monthly attendance summary (always generated automatically) is also emailed
        'attendance_summary_email' => true,
    ];

    public const MAX_NEXT_YEAR_DAYS = 90;

    public static function for(?int $companyId): array
    {
        $stored = $companyId ? (Company::query()->whereKey($companyId)->value('settings')) : null;
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return self::normalize(is_array($stored) ? $stored : []);
    }

    public static function get(?int $companyId, string $key): mixed
    {
        return self::for($companyId)[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public static function save(int $companyId, array $values): array
    {
        $settings = self::normalize(array_merge(self::for($companyId), $values));
        Company::query()->whereKey($companyId)->update(['settings' => json_encode($settings)]);

        return $settings;
    }

    private static function normalize(array $values): array
    {
        $out = self::DEFAULTS;
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            $out[$key] = is_bool(self::DEFAULTS[$key]) ? (bool) $value : (int) $value;
        }
        $out['vacation_next_year_days'] = max(0, min(self::MAX_NEXT_YEAR_DAYS, $out['vacation_next_year_days']));

        return $out;
    }
}
