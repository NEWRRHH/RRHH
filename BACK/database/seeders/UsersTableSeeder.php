<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\DefaultRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Test accounts. Credentials come from the environment so they are not
 * hard-coded; the defaults are for local development only:
 *
 *   SEED_ADMIN_EMAIL     (default admin@rrhh.test)
 *   SEED_ADMIN_PASSWORD  (default Admin1234!)  - also used for the RRHH user
 */
class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('SEED_ADMIN_PASSWORD', 'Admin1234!');
        $roles = Role::pluck('id', 'name');
        $teams = Team::pluck('id', 'name');

        $admin = User::create([
            'name' => 'Fernando Heredia',
            'username' => 'fernando',
            'email' => (string) env('SEED_ADMIN_EMAIL', 'admin@rrhh.test'),
            'password' => Hash::make($password),
            'first_name' => 'Fernando',
            'last_name' => 'Heredia',
            'birth_date' => now()->subYears(30)->toDateString(),
            'photo' => '',
            'team_id' => $teams['Administración'] ?? $teams->first(),
        ]);
        $admin->role_id = $roles[DefaultRoles::ADMIN];
        $admin->save();

        $hr = User::create([
            'name' => 'Usuario RRHH',
            'username' => 'rrhh',
            'email' => 'rrhh@rrhh.test',
            'password' => Hash::make($password),
            'first_name' => 'Usuario',
            'last_name' => 'RRHH',
            'birth_date' => now()->subYears(28)->toDateString(),
            'photo' => '',
            'team_id' => $teams['RRHH'] ?? $teams->first(),
        ]);
        $hr->role_id = $roles[DefaultRoles::HR];
        $hr->save();

        // example employees
        User::factory()->count(3)->create([
            'role_id' => $roles[DefaultRoles::EMPLOYEE],
            'team_id' => $teams['IT'] ?? $teams->first(),
        ]);
    }
}
