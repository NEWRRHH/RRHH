<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SchedulesSeeder extends Seeder
{
    public function run(): void
    {
        // Look up the admin user by email (avoid hardcoding id)
        $admin = DB::table('users')->where('email', 'admin@rrhh.test')->first(['id']);
        if (!$admin) {
            return;
        }

        // If admin already has a schedule assigned, skip to avoid duplicates
        $existingAssignment = DB::table('user_schedules')
            ->where('user_id', $admin->id)
            ->exists();

        if ($existingAssignment) {
            return;
        }

        // Create default schedule template (L-V, 09:00-18:00)
        $scheduleId = DB::table('schedules')->insertGetId([
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'days' => json_encode(['L', 'M', 'X', 'J', 'V']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign the schedule to the admin user
        DB::table('user_schedules')->insert([
            'user_id' => $admin->id,
            'schedule_id' => $scheduleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
