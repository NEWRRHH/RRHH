<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScheduleDaysSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('schedules') || !Schema::hasColumn('schedules', 'days')) {
            return;
        }

        $defaultDays = json_encode(['L', 'M', 'X', 'J', 'V']);

        DB::table('schedules')
            ->whereNull('days')
            ->update([
                'days' => $defaultDays,
                'updated_at' => now(),
            ]);
    }
}
