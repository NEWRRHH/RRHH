<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventTypesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $data = [
            ['name' => 'General', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Vacaciones', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Permiso', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Reunión', 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($data as $row) {
            $exists = DB::table('event_types')->where('name', $row['name'])->first();
            if (! $exists) {
                DB::table('event_types')->insert($row);
            }
        }
    }
}
