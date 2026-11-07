<?php

namespace App\Support\Tenancy;

/**
 * Current company (tenant) of the request.
 *
 * Set by the EnsureTenant middleware from the authenticated user and cleared
 * when the request ends. null means "no tenant filter": unauthenticated code
 * (login, seeders, console) and the platform super admin.
 */
final class Tenant
{
    private static ?int $companyId = null;

    public static function id(): ?int
    {
        return self::$companyId;
    }

    public static function set(?int $companyId): void
    {
        self::$companyId = $companyId;
    }

    public static function clear(): void
    {
        self::$companyId = null;
    }

    /**
     * Run a callback as the given company and restore the previous tenant.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(?int $companyId, callable $callback): mixed
    {
        $previous = self::$companyId;
        self::$companyId = $companyId;
        try {
            return $callback();
        } finally {
            self::$companyId = $previous;
        }
    }
}
