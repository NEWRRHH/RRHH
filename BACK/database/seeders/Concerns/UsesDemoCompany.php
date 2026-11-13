<?php

namespace Database\Seeders\Concerns;

use App\Models\Company;
use Database\Seeders\CompaniesSeeder;

trait UsesDemoCompany
{
    protected function demoCompanyId(): int
    {
        return (int) Company::where('slug', CompaniesSeeder::DEMO_SLUG)->value('id');
    }
}
