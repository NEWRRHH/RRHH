<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Ver como" (employees.view_as): RRHH / admin can open the app as an
 * employee in read-only mode for visual audits/support. Every session is
 * audited and private chat stays hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('permissions')->where('code', 'employees.view_as')->exists()) {
            return;
        }
        $now = now();
        $permissionId = DB::table('permissions')->insertGetId([
            'code' => 'employees.view_as',
            'name' => 'Ver como empleado (solo lectura)',
            'description' => 'Permite abrir la aplicación como otro empleado, sin poder modificar nada ni leer su chat. Cada acceso queda en la auditoría.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table('roles')->where('name', 'RRHH')->where('is_admin', false)->pluck('id') as $roleId) {
            DB::table('role_permission')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('code', 'employees.view_as')->value('id');
        if ($id) {
            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
