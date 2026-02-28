<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'pause_time')) {
                $table->time('pause_time')->nullable()->after('start_time');
            }
            if (! Schema::hasColumn('attendances', 'resume_time')) {
                $table->time('resume_time')->nullable()->after('pause_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'resume_time')) {
                $table->dropColumn('resume_time');
            }
            if (Schema::hasColumn('attendances', 'pause_time')) {
                $table->dropColumn('pause_time');
            }
        });
    }
};
