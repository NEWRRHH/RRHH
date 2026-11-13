<?php

namespace Database\Seeders;

use App\Models\NationalHoliday;
use App\Support\Easter;
use Illuminate\Database\Seeder;

/**
 * Starting catalog: national public holidays of Spain. Fixed-date ones are
 * recurring; Good Friday moves with Easter, so it is generated for the
 * previous year and the next five (extend with `php artisan holidays:good-friday`).
 * The super admin maintains the catalog from the panel (or CSV).
 */
class NationalHolidaysSeeder extends Seeder
{
    public function run(): void
    {
        $recurring = [
            ['2026-01-01', 'Año Nuevo'],
            ['2026-01-06', 'Epifanía del Señor'],
            ['2026-05-01', 'Fiesta del Trabajo'],
            ['2026-08-15', 'Asunción de la Virgen'],
            ['2026-10-12', 'Fiesta Nacional de España'],
            ['2026-11-01', 'Todos los Santos'],
            ['2026-12-06', 'Día de la Constitución'],
            ['2026-12-08', 'Inmaculada Concepción'],
            ['2026-12-25', 'Navidad'],
        ];
        foreach ($recurring as [$date, $name]) {
            NationalHoliday::firstOrCreate(
                ['country_code' => 'ES', 'date' => $date],
                ['name' => $name, 'type' => 'national', 'recurring' => true],
            );
        }

        $thisYear = now()->year;
        foreach (range($thisYear - 1, $thisYear + 5) as $year) {
            NationalHoliday::firstOrCreate(
                ['country_code' => 'ES', 'date' => Easter::goodFriday($year)->toDateString()],
                ['name' => 'Viernes Santo', 'type' => 'national', 'recurring' => false],
            );
        }
    }
}
