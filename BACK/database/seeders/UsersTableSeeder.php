<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        // admin
        User::create([
            'name' => 'Fernando Heredia',
            'username' => 'fernando',
            'email' => 'admin@rrhh.test',
            'password' => Hash::make('admin'),
            'first_name' => 'Fernando',
            'last_name' => 'Heredia',
            'birth_date' => now()->subYears(30)->toDateString(),
            'photo' => '',
            'user_type_id' => 1,
            'team_id' => 1,
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // example user(s)
        User::factory()->count(3)->create();
    }
}
