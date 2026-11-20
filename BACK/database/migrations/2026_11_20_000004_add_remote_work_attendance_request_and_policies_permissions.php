<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permissions that were missing:
 * - remote_work.view / remote_work.request: Teletrabajo has its own view and
 *   no longer rides on the vacations permissions
 * - attendance.request: ask RRHH to fix a clock-in (Reportes > Ausencias),
 *   it was not checked at all
 * - policies.manage: Configuración > Empresa y políticas (vacation window,
 *   summary email), it borrowed holidays.manage
 *
 * Each one is granted to the roles that could already do it (the permission
 * it used to depend on), so nobody loses access when updating. Admin roles
 * have everything anyway.
 */
return new class extends Migration
{
    /** code => [name, description, granted to roles that hold any of these permissions] */
    private const PERMISSIONS = [
        'remote_work.view' => ['Ver teletrabajo', 'Permite ver la sección Teletrabajo y sus solicitudes.', ['vacations.view']],
        'remote_work.request' => ['Solicitar teletrabajo', 'Permite solicitar y anular días de teletrabajo (solo modalidad "a pedido").', ['vacations.create']],
        // employees too: the API never checked it and the "salida automática" notice tells them to ask for the fix
        'attendance.request' => ['Solicitar corrección de fichajes', 'Permite pedir a RRHH que regularice entradas o salidas no fichadas (Reportes > Ausencias).', ['reports.view', 'vacations.create']],
        'policies.manage' => ['Gestionar políticas de la empresa', 'Permite cambiar los días del año siguiente que se pueden pedir de vacaciones y el envío por email del resumen mensual.', ['holidays.manage']],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::PERMISSIONS as $code => [$name, $description, $grantedWith]) {
            if (DB::table('permissions')->where('code', $code)->exists()) {
                continue;
            }
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $roleIds = DB::table('role_permission')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->join('roles', 'roles.id', '=', 'role_permission.role_id')
                ->whereIn('permissions.code', $grantedWith)
                ->where('roles.is_admin', false)
                ->distinct()
                ->pluck('role_permission.role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('code', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
