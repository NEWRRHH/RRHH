<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clock-ins left open (the employee forgot to clock out) are closed by the
 * system at the end of the day, at the end of the employee's schedule:
 * - auto_closed_at: when the system closed it
 * - note: explanation shown in Reportes and in the monthly PDF
 * - auto_close_seen_at: the employee saw the notice (dashboard)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'auto_closed_at')) {
                $table->timestamp('auto_closed_at')->nullable();
            }
            if (! Schema::hasColumn('attendances', 'auto_close_seen_at')) {
                $table->timestamp('auto_close_seen_at')->nullable();
            }
            if (! Schema::hasColumn('attendances', 'note')) {
                $table->string('note', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            foreach (['auto_closed_at', 'auto_close_seen_at', 'note'] as $column) {
                if (Schema::hasColumn('attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
