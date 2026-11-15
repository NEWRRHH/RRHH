<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.work_mode (Modalidad de trabajo):
 *   null     -> office by default, remote only on approved "Teletrabajo" days
 *   'office' -> always office, can't request remote work
 *   'remote' -> always remote work, nothing to request
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'work_mode')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('work_mode', 20)->nullable()->after('contract_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'work_mode')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('work_mode'));
        }
    }
};
