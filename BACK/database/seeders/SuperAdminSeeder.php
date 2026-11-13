<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Platform super admin (belongs to no company, sees every company).
 * Always seeded so there is a test account to log in with:
 *
 *   SEED_SUPERADMIN_EMAIL     (default superadmin@rrhh.test)
 *   SEED_SUPERADMIN_PASSWORD  (default SuperAdmin1234!)
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SEED_SUPERADMIN_EMAIL', 'superadmin@rrhh.test');

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'password' => Hash::make((string) env('SEED_SUPERADMIN_PASSWORD', 'SuperAdmin1234!')),
            'photo' => '',
        ]);
        // not mass assignable on purpose
        $user->company_id = null;
        $user->role_id = null;
        $user->is_super_admin = true;
        $user->save();
    }
}
