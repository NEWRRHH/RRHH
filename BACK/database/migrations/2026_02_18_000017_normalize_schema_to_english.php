<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Ensure users table contains combined fields (Laravel + CI)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'username')) $table->string('username')->nullable()->unique()->after('name');
                if (!Schema::hasColumn('users', 'role')) $table->string('role')->nullable()->after('username');
                if (!Schema::hasColumn('users', 'first_name')) $table->string('first_name', 50)->nullable();
                if (!Schema::hasColumn('users', 'last_name')) $table->string('last_name', 50)->nullable();
                if (!Schema::hasColumn('users', 'birth_date')) $table->date('birth_date')->nullable();
                if (!Schema::hasColumn('users', 'hire_date')) $table->date('hire_date')->nullable();
                if (!Schema::hasColumn('users', 'photo')) $table->string('photo')->nullable();
                if (!Schema::hasColumn('users', 'phone')) $table->string('phone', 20)->nullable();
                if (!Schema::hasColumn('users', 'user_type_id')) $table->unsignedInteger('user_type_id')->nullable();
                if (!Schema::hasColumn('users', 'team_id')) $table->unsignedInteger('team_id')->nullable();
            });
        }

        // 2) If legacy Spanish 'Usuarios' table exists, try to migrate its rows into 'users' then drop it
        if (Schema::hasTable('Usuarios')) {
            // copy rows by email (update existing or insert)
            $rows = DB::table('Usuarios')->get();
            foreach ($rows as $r) {
                $existing = DB::table('users')->where('email', $r->Email)->first();
                $data = [
                    'first_name' => $r->Nombre ?? null,
                    'last_name' => $r->Apellido ?? null,
                    'email' => $r->Email ?? null,
                    'password' => $r->Password ?? null,
                    'birth_date' => $r->FechaNacimiento ?? null,
                    'hire_date' => $r->FechaIngreso ?? null,
                    'photo' => $r->foto ?? null,
                    'phone' => $r->Telefono ?? null,
                    'user_type_id' => $r->TipoUsuario ?? null,
                    'team_id' => $r->IdEquipo ?? null,
                    'created_at' => $r->created_at ?? now(),
                    'updated_at' => $r->updated_at ?? now(),
                ];
                if ($existing) {
                    DB::table('users')->where('id', $existing->id)->update($data);
                } else {
                    DB::table('users')->insert($data);
                }
            }

            Schema::dropIfExists('Usuarios');
        }

        // 3) Create English-named tables for previously Spanish ones (if not present)
        if (!Schema::hasTable('teams') && Schema::hasTable('Equipo')) {
            Schema::rename('Equipo', 'teams');
        }

        if (!Schema::hasTable('user_types') && Schema::hasTable('TiposUsuarios')) {
            Schema::rename('TiposUsuarios', 'user_types');
        }

        if (!Schema::hasTable('document_types') && Schema::hasTable('TiposDocumentos')) {
            Schema::rename('TiposDocumentos', 'document_types');
        }

        if (!Schema::hasTable('event_types') && Schema::hasTable('TiposEventos')) {
            Schema::rename('TiposEventos', 'event_types');
        }

        if (!Schema::hasTable('notification_types') && Schema::hasTable('TiposNotificaciones')) {
            Schema::rename('TiposNotificaciones', 'notification_types');
        }

        if (!Schema::hasTable('modules') && Schema::hasTable('Modulos')) {
            Schema::rename('Modulos', 'modules');
        }

        if (!Schema::hasTable('schedules') && Schema::hasTable('Horarios')) {
            Schema::rename('Horarios', 'schedules');
        }

        if (!Schema::hasTable('user_schedules') && Schema::hasTable('UsuariosHorarios')) {
            Schema::rename('UsuariosHorarios', 'user_schedules');
        }

        if (!Schema::hasTable('documents') && Schema::hasTable('Documentos')) {
            Schema::rename('Documentos', 'documents');
        }

        if (!Schema::hasTable('events') && Schema::hasTable('Eventos')) {
            Schema::rename('Eventos', 'events');
        }

        if (!Schema::hasTable('notifications') && Schema::hasTable('Notificaciones')) {
            Schema::rename('Notificaciones', 'notifications');
        }

        // 4) Ensure columns inside renamed tables follow English conventions where feasible
        // (we avoid renameColumn here to prevent requiring doctrine/dbal; leave columns as-is
        // if already English, otherwise future migrations can normalize them safely)
    }

    public function down(): void
    {
        // do not attempt to restore legacy Spanish names — keep English schema as canonical
    }
};
