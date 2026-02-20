<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // convert the hours_worked column from float to time
        // MySQL allows modifying the column directly
        Schema::table('attendances', function (Blueprint $table) {
            // using raw statement because changing types requires doctrine normally
            DB::statement("ALTER TABLE attendances MODIFY hours_worked TIME NULL");
        });
        // if there were existing float values, convert them to proper time
        // hours were stored as decimal hours; multiply by 3600 and use SEC_TO_TIME
        DB::statement("UPDATE attendances SET hours_worked = SEC_TO_TIME(hours_worked * 3600) WHERE hours_worked IS NOT NULL");
    }

    public function down(): void
    {
        // revert back to float, leave existing values as NULL
        Schema::table('attendances', function (Blueprint $table) {
            DB::statement("ALTER TABLE attendances MODIFY hours_worked FLOAT NULL");
        });
    }
};
