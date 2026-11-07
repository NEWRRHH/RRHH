<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\UsesDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamsSeeder extends Seeder
{
    use UsesDemoCompany;

    public function run(): void
    {
        $companyId = $this->demoCompanyId();
        $now = now();

        foreach (['Administración', 'RRHH', 'IT'] as $name) {
            $exists = DB::table('teams')->where('company_id', $companyId)->where('name', $name)->exists();
            if (! $exists) {
                DB::table('teams')->insert([
                    'company_id' => $companyId,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
