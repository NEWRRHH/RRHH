<?php

use App\Support\DefaultRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Authorization moves from "permissions per team" + user_types + users.role
 * to a single model: every user has ONE role and roles hold permissions.
 *
 * Existing data is preserved: each team that had permissions becomes a role
 * with the same permissions and its members get that role; users with the old
 * admin user type get the Admin role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        // Capture legacy assignments before dropping the old tables.
        $legacyTeamPermissions = Schema::hasTable('team_permision')
            ? DB::table('team_permision')->get(['team_id', 'permision_id'])
            : collect();

        Schema::dropIfExists('team_permision');
        Schema::rename('permisions', 'permissions');

        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('team_id')->constrained('roles')->nullOnDelete();
        });

        $this->insertNewPermissions();

        // ---- data migration (no-op on a fresh database) ----
        $hasUsers = DB::table('users')->exists();
        if ($hasUsers) {
            $roles = DefaultRoles::ensure();
            $now = now();

            foreach ($legacyTeamPermissions->groupBy('team_id') as $teamId => $rows) {
                $teamName = DB::table('teams')->where('id', $teamId)->value('name') ?: ('Equipo ' . $teamId);
                $roleId = DB::table('roles')->where('name', $teamName)->value('id')
                    ?: DB::table('roles')->insertGetId(['name' => $teamName, 'created_at' => $now, 'updated_at' => $now]);

                DB::table('role_permission')->where('role_id', $roleId)->delete();
                foreach ($rows->pluck('permision_id')->unique() as $permissionId) {
                    DB::table('role_permission')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('users')->where('team_id', $teamId)->update(['role_id' => $roleId]);
            }

            DB::table('users')->whereNull('role_id')->update(['role_id' => $roles['employee']]);
            if (Schema::hasColumn('users', 'user_type_id')) {
                DB::table('users')->where('user_type_id', 1)->update(['role_id' => $roles['admin']]);
            }
        }

        // ---- drop the legacy authorization columns/tables ----
        $userForeignKeys = collect(Schema::getForeignKeys('users'))->pluck('columns')->flatten()->all();
        Schema::table('users', function (Blueprint $table) use ($userForeignKeys) {
            if (in_array('user_type_id', $userForeignKeys, true)) {
                $table->dropForeign(['user_type_id']);
            }
        });
        Schema::table('users', function (Blueprint $table) {
            $drop = array_values(array_filter(['user_type_id', 'role'], fn ($c) => Schema::hasColumn('users', $c)));
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
        Schema::dropIfExists('user_types');
    }

    public function down(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->string('role')->nullable()->after('username');
            $table->unsignedInteger('user_type_id')->nullable();
        });

        Schema::dropIfExists('role_permission');
        DB::table('permissions')->whereIn('code', ['attendance.view_all', 'teams.manage'])->delete();
        Schema::rename('permissions', 'permisions');

        Schema::create('team_permision', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('team_id');
            $table->unsignedBigInteger('permision_id');
            $table->timestamps();
            $table->unique(['team_id', 'permision_id']);
            $table->foreign('team_id')->references('id')->on('teams')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('permision_id')->references('id')->on('permisions')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::dropIfExists('roles');
    }

    /**
     * Permissions that replace the old hard-coded checks
     * (isHrTeam() by team name, admin-only teams CRUD).
     */
    private function insertNewPermissions(): void
    {
        $now = now();
        $rows = [
            [
                'code' => 'attendance.view_all',
                'name' => 'Ver fichajes de todos',
                'description' => 'Permite ver los fichajes, reportes y quien esta trabajando de todos los empleados.',
            ],
            [
                'code' => 'teams.manage',
                'name' => 'Gestionar equipos',
                'description' => 'Permite crear, renombrar y eliminar equipos.',
            ],
        ];

        foreach ($rows as $row) {
            if (! DB::table('permissions')->where('code', $row['code'])->exists()) {
                DB::table('permissions')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
};
