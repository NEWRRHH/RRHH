<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - national_holidays becomes a per-company catalog (RRHH / admin manage it
 *   from Configuración > Festivos > Feriados nacionales). The old platform
 *   rows are copied into every company so nothing is lost.
 * - holidays.is_working_day: a holiday the company works (counts as a normal
 *   working day: clock-in allowed, absence if nobody clocks in); it is still
 *   labelled as a holiday in the reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('national_holidays', 'company_id')) {
            Schema::table('national_holidays', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            });
            Schema::table('national_holidays', function (Blueprint $table) {
                $table->dropUnique(['country_code', 'date']);
                $table->unique(['company_id', 'country_code', 'date']);
            });

            $global = DB::table('national_holidays')->whereNull('company_id')->get();
            $now = now();
            foreach (DB::table('companies')->pluck('id') as $companyId) {
                $rows = $global->map(fn ($h) => [
                    'company_id' => $companyId,
                    'country_code' => $h->country_code,
                    'date' => $h->date,
                    'name' => $h->name,
                    'type' => $h->type,
                    'recurring' => $h->recurring,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('national_holidays')->insert($chunk);
                }
            }
            // rows without company stay as the template new companies copy
            // (NationalHolidaysSeeder / holidays:good-friday fill it)
        }

        if (! Schema::hasColumn('holidays', 'is_working_day')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->boolean('is_working_day')->default(false)->after('recurring');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('holidays', 'is_working_day')) {
            Schema::table('holidays', fn (Blueprint $table) => $table->dropColumn('is_working_day'));
        }
        if (Schema::hasColumn('national_holidays', 'company_id')) {
            Schema::table('national_holidays', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'country_code', 'date']);
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
