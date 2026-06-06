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
                'code' => 'vacations.view',
                'name' => 'Ver vacaciones',
                'description' => 'Permite ver el calendario de vacaciones y ausencias.',
            ],
            [
                'code' => 'vacations.create',
                'name' => 'Crear solicitudes de vacaciones',
                'description' => 'Permite crear nuevas solicitudes de vacaciones y ausencias.',
            ],
            [
                'code' => 'vacations.edit',
                'name' => 'Editar solicitudes de vacaciones',
                'description' => 'Permite editar solicitudes de vacaciones existentes.',
            ],
            [
                'code' => 'requests.view',
                'name' => 'Ver solicitudes',
                'description' => 'Permite ver la lista de solicitudes de tiempo libre.',
            ],
            [
                'code' => 'documents.view',
                'name' => 'Ver documentos',
                'description' => 'Permite ver la lista de documentos personales.',
            ],
            [
                'code' => 'documents.upload',
                'name' => 'Subir documentos',
                'description' => 'Permite subir documentos personales.',
            ],
            [
                'code' => 'reports.view',
                'name' => 'Ver reportes',
                'description' => 'Permite ver la sección de reportes de fichajes.',
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

        $codes = [
            'vacations.view', 'vacations.create', 'vacations.edit',
            'requests.view',
            'documents.view', 'documents.upload',
            'reports.view',
        ];

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
