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
        $code = 'employees.create';

        $existing = DB::table('permisions')
            ->where('code', $code)
            ->first(['id']);

        $permissionId = $existing && isset($existing->id)
            ? (int) $existing->id
            : (int) DB::table('permisions')->insertGetId([
                'code' => $code,
                'name' => 'Crear usuarios',
                'description' => 'Permite crear usuarios desde la vista de empleados.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $rrhhTeamId = DB::table('teams')
            ->whereRaw('LOWER(name) LIKE ?', ['%rrhh%'])
            ->value('id');

        if ($rrhhTeamId) {
            $exists = DB::table('team_permision')
                ->where('team_id', (int) $rrhhTeamId)
                ->where('permision_id', $permissionId)
                ->exists();

            if (!$exists) {
                DB::table('team_permision')->insert([
                    'team_id' => (int) $rrhhTeamId,
                    'permision_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permisions') || !Schema::hasTable('team_permision')) {
            return;
        }

        $permissionIds = DB::table('permisions')
            ->where('code', 'employees.create')
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
