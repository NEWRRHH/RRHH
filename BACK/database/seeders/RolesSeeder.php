<?php

namespace Database\Seeders;

use App\Support\DefaultRoles;
use Database\Seeders\Concerns\UsesDemoCompany;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    use UsesDemoCompany;

    public function run(): void
    {
        DefaultRoles::ensure($this->demoCompanyId());
    }
}
