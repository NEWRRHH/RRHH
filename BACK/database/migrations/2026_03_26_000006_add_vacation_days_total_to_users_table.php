<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'vacation_days_total')) {
                $table->unsignedSmallInteger('vacation_days_total')->default(0)->after('contract_start_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'vacation_days_total')) {
                $table->dropColumn('vacation_days_total');
            }
        });
    }
};

