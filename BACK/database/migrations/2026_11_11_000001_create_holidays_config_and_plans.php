<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - holidays.manage permission (company holidays screen), granted to RRHH.
 * - national_holidays: platform catalog per country, companies copy from it.
 * - plans: per-company limits (employees, storage) managed by the super admin.
 * - companies.country_code: default country for national holidays.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('permissions')->where('code', 'holidays.manage')->exists()) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => 'holidays.manage',
                'name' => 'Gestionar festivos',
                'description' => 'Permite crear, editar, importar y eliminar los festivos de la empresa.',
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

        Schema::create('national_holidays', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->index();
            $table->date('date');
            $table->string('name', 150);
            $table->string('type', 20)->default('national'); // national | regional
            $table->boolean('recurring')->default(false);
            $table->timestamps();
            $table->unique(['country_code', 'date']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedInteger('max_employees')->nullable();  // null = unlimited
            $table->unsignedInteger('max_storage_mb')->nullable(); // null = unlimited
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Starting catalog; the super admin edits it from the panel.
        DB::table('plans')->insert([
            ['code' => 'free', 'name' => 'Gratis', 'max_employees' => 10, 'max_storage_mb' => 500, 'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'basic', 'name' => 'Básico', 'max_employees' => 50, 'max_storage_mb' => 5120, 'is_default' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'pro', 'name' => 'Pro', 'max_employees' => null, 'max_storage_mb' => null, 'is_default' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->char('country_code', 2)->default('ES')->after('plan');
        });

        // companies.plan now holds a plans.code
        DB::table('companies')
            ->whereNull('plan')
            ->orWhereNotIn('plan', ['free', 'basic', 'pro'])
            ->update(['plan' => 'free']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
        Schema::dropIfExists('plans');
        Schema::dropIfExists('national_holidays');

        $id = DB::table('permissions')->where('code', 'holidays.manage')->value('id');
        if ($id) {
            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
