<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - events.request_days: an attendance request ("Fichaje") can cover several
 *   absence days; RRHH approves or rejects each day:
 *   [{date, kind, start_time, end_time, status: pending|approved|rejected}]
 * - event_types.code for General / Permiso / Reunión, so the app can rely on
 *   them (title = type name, required description, icon) whatever their name.
 */
return new class extends Migration
{
    private const CODES = ['General' => 'general', 'Permiso' => 'permission', 'Reunión' => 'meeting'];

    public function up(): void
    {
        if (! Schema::hasColumn('events', 'request_days')) {
            Schema::table('events', function (Blueprint $table) {
                $table->json('request_days')->nullable()->after('description');
            });
        }

        foreach (self::CODES as $name => $code) {
            DB::table('event_types')->whereNull('code')->where('name', $name)->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'request_days')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('request_days');
            });
        }
        DB::table('event_types')->whereIn('code', array_values(self::CODES))->update(['code' => null]);
    }
};
