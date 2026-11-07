<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\UsesDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendancesSeeder extends Seeder
{
    use UsesDemoCompany;

    public function run(): void
    {
        $adminId = DB::table('users')->where('email', env('SEED_ADMIN_EMAIL', 'admin@rrhh.test'))->value('id');

        DB::table('attendances')->insert([
            [
                'company_id' => $this->demoCompanyId(),
                'user_id' => $adminId,
                'session_token' => null,
                'date' => now()->format('Y/m/d'),
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'end_date' => now()->format('Y/m/d'),
                'hours_worked' => '08:00:00',
                'status' => 'fuera_trabajo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
