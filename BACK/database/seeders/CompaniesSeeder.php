<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Demo tenant that owns all the sample data.
 */
class CompaniesSeeder extends Seeder
{
    public const DEMO_SLUG = 'demo';

    public function run(): void
    {
        Company::firstOrCreate(
            ['slug' => self::DEMO_SLUG],
            ['name' => 'Empresa Demo', 'status' => Company::STATUS_ACTIVE],
        );
    }
}
