<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Default roles every company starts with: Admin (everything, cannot be
 * deleted), RRHH and Empleado. Admins can edit RRHH/Empleado or add more.
 *
 * Uses the query builder (not models) so it is safe inside migrations.
 */
final class DefaultRoles
{
    public const ADMIN = 'Admin';
    public const HR = 'RRHH';
    public const EMPLOYEE = 'Empleado';

    /** Permissions of the default HR role. */
    public const HR_PERMISSIONS = [
        'employees.view_details',
        'employees.create',
        'employees.edit',
        'employees.delete',
        'attendance.view_all',
        'requests.view',
        'requests.review',
        'vacations.view',
        'vacations.create',
        'vacations.edit',
        'documents.view',
        'documents.upload',
        'reports.view',
        'schedules.manage',
        'teams.manage',
        'announcements.manage',
    ];

    /** Self-service permissions of a regular employee. */
    public const EMPLOYEE_PERMISSIONS = [
        'vacations.view',
        'vacations.create',
        'vacations.edit',
        'requests.view',
        'documents.view',
        'documents.upload',
    ];

    /**
     * Create the default roles if missing and return their ids.
     *
     * @return array{admin: int, hr: int, employee: int}
     */
    public static function ensure(): array
    {
        return [
            'admin' => self::ensureRole(self::ADMIN, 'Acceso total a la empresa', true, []),
            'hr' => self::ensureRole(self::HR, 'Recursos humanos', false, self::HR_PERMISSIONS),
            'employee' => self::ensureRole(self::EMPLOYEE, 'Empleado', false, self::EMPLOYEE_PERMISSIONS),
        ];
    }

    /**
     * @param  array<int, string>  $permissionCodes
     */
    private static function ensureRole(string $name, string $description, bool $isAdmin, array $permissionCodes): int
    {
        $now = now();
        $id = DB::table('roles')->where('name', $name)->value('id');
        if ($id) {
            return (int) $id;
        }

        $id = (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'description' => $description,
            'is_admin' => $isAdmin,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionIds = DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $id,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $id;
    }
}
