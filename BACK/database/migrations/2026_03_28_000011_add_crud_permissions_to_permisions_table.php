<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permisions') || !Schema::hasTable('team_permision')) {
            return;
        }

        $now = now();
        $permissions = [
            [
                'code' => 'employees.edit',
                'name' => 'Editar usuarios',
                'description' => 'Permite editar la información detallada de los empleados.',
            ],
            [
                'code' => 'schedules.manage',
                'name' => 'Gestionar jornadas',
                'description' => 'Permite crear, editar y eliminar jornadas laborales en Configuración.',
            ],
            [
                'code' => 'announcements.manage',
                'name' => 'Gestionar comunicados',
                'description' => 'Permite crear y visualizar comunicados internos.',
            ],
        ];

        $idsByCode = [];
        foreach ($permissions as $permission) {
            $existing = DB::table('permisions')
                ->where('code', $permission['code'])
                ->first(['id']);

            if ($existing && isset($existing->id)) {
                $idsByCode[$permission['code']] = (int) $existing->id;
                continue;
            }

            $idsByCode[$permission['code']] = (int) DB::table('permisions')->insertGetId([
                'code' => $permission['code'],
                'name' => $permission['name'],
                'description' => $permission['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rrhhTeamId = DB::table('teams')
            ->whereRaw('LOWER(name) LIKE ?', ['%rrhh%'])
            ->value('id');

        if ($rrhhTeamId) {
            foreach ($idsByCode as $permisionId) {
                $exists = DB::table('team_permision')
                    ->where('team_id', (int) $rrhhTeamId)
                    ->where('permision_id', (int) $permisionId)
                    ->exists();

                if (!$exists) {
                    DB::table('team_permision')->insert([
                        'team_id' => (int) $rrhhTeamId,
                        'permision_id' => (int) $permisionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permisions') || !Schema::hasTable('team_permision')) {
            return;
        }

        $codes = ['employees.edit', 'schedules.manage', 'announcements.manage'];
        $permissionIds = DB::table('permisions')
            ->whereIn('code', $codes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!empty($permissionIds)) {
            DB::table('team_permision')
                ->whereIn('permision_id', $permissionIds)
                ->delete();

            DB::table('permisions')
                ->whereIn('id', $permissionIds)
                ->delete();
        }
    }
};
