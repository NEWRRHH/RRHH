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
                'code' => 'employees.view_details',
                'name' => 'Ver detalle de usuarios',
                'description' => 'Permite ver y editar el detalle completo de empleados.',
            ],
            [
                'code' => 'employees.delete',
                'name' => 'Eliminar usuarios',
                'description' => 'Permite eliminar usuarios desde la vista de empleados.',
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

        $codes = ['employees.view_details', 'employees.delete'];
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

