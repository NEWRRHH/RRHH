<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mixed dashboard configuration:
 * - company_dashboard_layouts: the default layout + welcome message that
 *   RRHH defines for everybody in the company ("dashboard.manage");
 * - user_dashboard_widgets (existing): each user's own arrangement, which
 *   overrides the company layout until the user resets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->json('slots');            // 9 positions: widget key or null
            $table->json('settings')->nullable(); // { welcome: { title, subtitle, show_date } }
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        if (! DB::table('permissions')->where('code', 'dashboard.manage')->exists()) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => 'dashboard.manage',
                'name' => 'Configurar dashboard de la empresa',
                'description' => 'Permite definir las tarjetas y el mensaje de bienvenida que ven por defecto todos los empleados.',
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
    }

    public function down(): void
    {
        Schema::dropIfExists('company_dashboard_layouts');

        $id = DB::table('permissions')->where('code', 'dashboard.manage')->value('id');
        if ($id) {
            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
