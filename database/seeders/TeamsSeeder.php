<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $data = [
            ['name' => 'Administración', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'RRHH', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'IT', 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($data as $row) {
            $exists = DB::table('teams')->where('name', $row['name'])->first();
            if (! $exists) {
                DB::table('teams')->insert($row);
            }
        }
    }
}
