<?php

namespace Database\Seeders;

use App\Support\DefaultRoles;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        DefaultRoles::ensure();
    }
}
