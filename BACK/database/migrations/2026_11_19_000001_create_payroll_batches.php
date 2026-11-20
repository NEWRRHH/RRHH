<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nóminas module: the gestoría calculates the payslips, RRHH uploads the
 * whole month at once (files named with the employee's DNI), checks who gets
 * each one and presses "Enviar": every payslip lands in the employee's
 * Documentos > Nóminas (with the real-time badge).
 *
 * - payroll_batches: one upload per period (draft -> sent)
 * - payroll_batch_files: each file, the employee it was matched to and whether it was sent
 * - permission payroll.manage (RRHH by default, admins always)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7);                 // YYYY-MM
            $table->string('status', 20)->default('draft'); // draft | sent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'period']);
        });

        Schema::create('payroll_batch_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('payroll_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');                    // staging path, then the employee's folder
            $table->unsignedBigInteger('size')->default(0);
            $table->string('match', 20)->default('none'); // dni | manual | none | ambiguous
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        if (! DB::table('permissions')->where('code', 'payroll.manage')->exists()) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => 'payroll.manage',
                'name' => 'Gestionar nóminas',
                'description' => 'Permite subir las nóminas de la gestoría y enviarlas a cada empleado.',
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
        Schema::dropIfExists('payroll_batch_files');
        Schema::dropIfExists('payroll_batches');
        $id = DB::table('permissions')->where('code', 'payroll.manage')->value('id');
        if ($id) {
            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
