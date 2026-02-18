<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTypesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $items = [
            ['name' => 'admin', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'employee', 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($items as $row) {
            $exists = DB::table('user_types')->where('name', $row['name'])->first();
            if (! $exists) {
                DB::table('user_types')->insert($row);
            }
        }
    }
}
