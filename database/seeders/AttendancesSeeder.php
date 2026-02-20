<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendancesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('attendances')->insert([
            ['user_id' => 1, 'session_token' => null, 'date' => now()->format('Y/m/d'), 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'hours_worked' => '08:00:00', 'status' => 'en_trabajo', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
