<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Good Friday moves with Easter: add it to the national holidays catalog
// for the coming years (existing dates are left untouched).
Artisan::command('holidays:good-friday {country=ES} {--years=5}', function (string $country) {
    $country = strtoupper($country);
    $created = 0;
    foreach (range(now()->year, now()->year + (int) $this->option('years')) as $year) {
        $holiday = \App\Models\NationalHoliday::firstOrCreate(
            ['country_code' => $country, 'date' => \App\Support\Easter::goodFriday($year)->toDateString()],
            ['name' => 'Viernes Santo', 'type' => 'national', 'recurring' => false],
        );
        $created += $holiday->wasRecentlyCreated ? 1 : 0;
        $this->line("{$year}: {$holiday->date->toDateString()}");
    }
    $this->info("{$created} Viernes Santo agregados para {$country}.");
})->purpose('Add Good Friday (Easter based) to the national holidays catalog');
