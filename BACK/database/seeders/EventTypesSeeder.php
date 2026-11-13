<?php

namespace Database\Seeders;

use App\Services\CompanyProvisioner;
use Database\Seeders\Concerns\UsesDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventTypesSeeder extends Seeder
{
    use UsesDemoCompany;

    public function run(): void
    {
        $companyId = $this->demoCompanyId();
        $now = now();
        foreach (CompanyProvisioner::DEFAULT_EVENT_TYPES as $type) {
            $exists = DB::table('event_types')->where('company_id', $companyId)->where('name', $type['name'])->exists();
            if (! $exists) {
                DB::table('event_types')->insert($type + [
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
