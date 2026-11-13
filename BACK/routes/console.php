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

// Production start-up, run by the "migrate" service of docker-compose.prod.yml
// before the app starts. Safe to run on every boot:
//   - always: pending migrations + public/storage symlink
//   - first boot only (no super admin yet): starting catalogs + super admin
//     from SEED_SUPERADMIN_EMAIL / SEED_SUPERADMIN_PASSWORD. Never re-run
//     afterwards, so a password changed from the app is not overwritten and
//     deleted catalog rows do not come back. Demo data is never seeded.
Artisan::command('app:provision', function () {
    $this->call('migrate', ['--force' => true]);

    if (! file_exists(public_path('storage'))) {
        $this->call('storage:link');
    }

    if (\App\Models\User::where('is_super_admin', true)->exists()) {
        $this->info('Provision: database already initialised.');

        return 0;
    }

    $email = (string) env('SEED_SUPERADMIN_EMAIL', '');
    $password = (string) env('SEED_SUPERADMIN_PASSWORD', '');
    if ($email === '' || $password === '') {
        $this->error('SEED_SUPERADMIN_EMAIL and SEED_SUPERADMIN_PASSWORD are required on first boot.');

        return 1;
    }

    foreach ([
        \Database\Seeders\NationalHolidaysSeeder::class,
        \Database\Seeders\DocumentTypesSeeder::class,
        \Database\Seeders\SuperAdminSeeder::class,
    ] as $seeder) {
        $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
    }

    $this->info("Provision: super admin created ({$email}).");

    return 0;
})->purpose('Migrate and initialise the database on production start-up');
