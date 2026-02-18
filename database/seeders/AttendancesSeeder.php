<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendancesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('attendances')->insert([
            ['user_id' => 1, 'date' => now()->toDateString(), 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'hours_worked' => 8.0, 'status' => 'en_trabajo', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
