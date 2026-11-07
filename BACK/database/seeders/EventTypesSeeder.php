<?php

namespace Database\Seeders;

use App\Models\EventType;
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
        $types = [
            ['name' => 'General', 'code' => null],
            ['name' => 'Vacaciones', 'code' => EventType::VACATION],
            ['name' => 'Permiso', 'code' => null],
            ['name' => 'Fichaje', 'code' => EventType::ATTENDANCE_REQUEST],
            ['name' => 'Reunión', 'code' => null],
        ];

        foreach ($types as $type) {
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
