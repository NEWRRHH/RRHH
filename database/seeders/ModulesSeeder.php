<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('modules')->insert([
            ['name' => 'Dashboard', 'route' => '/dashboard', 'icon' => 'i-lucide-home', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Employees', 'route' => '/employees', 'icon' => 'i-lucide-users', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
