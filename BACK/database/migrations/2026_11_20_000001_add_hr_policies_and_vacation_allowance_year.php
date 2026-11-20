<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - companies.settings: HR policies of the company (CompanySettings), e.g.
 *   how many days of next year can be requested as vacation and whether the
 *   monthly attendance summary is generated / emailed automatically.
 * - events.allowance_year: the yearly vacation allowance a vacation request
 *   is charged to. Days requested in the first days of next year (when the
 *   company allows it) are charged to the current year, not to the next one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'settings')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('settings')->nullable()->after('country_code');
            });
        }

        if (! Schema::hasColumn('events', 'allowance_year')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedSmallInteger('allowance_year')->nullable()->after('event_type_id');
            });
        }

        // existing vacation requests: charged to the year they start in
        $vacationTypeIds = DB::table('event_types')->where('code', 'vacation')->pluck('id');
        DB::table('events')
            ->whereIn('event_type_id', $vacationTypeIds)
            ->whereNull('allowance_year')
            ->orderBy('id')
            ->select(['id', 'start_at'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->start_at) {
                        DB::table('events')->where('id', $row->id)->update([
                            'allowance_year' => (int) substr((string) $row->start_at, 0, 4),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'allowance_year')) {
            Schema::table('events', fn (Blueprint $table) => $table->dropColumn('allowance_year'));
        }
        if (Schema::hasColumn('companies', 'settings')) {
            Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('settings'));
        }
    }
};
