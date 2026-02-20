<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SchedulesSeeder extends Seeder
{
    public function run(): void
    {
        // create a default schedule for the first user
        DB::table('schedules')->insert([
            'user_id' => 1,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
