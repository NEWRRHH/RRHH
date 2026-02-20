<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserTypesSeeder::class,
            TeamsSeeder::class,
            DocumentTypesSeeder::class,
            EventTypesSeeder::class,
            ModulesSeeder::class,
            UsersTableSeeder::class,
            PostsSeeder::class,
            FollowsSeeder::class,
            AttendancesSeeder::class,
            SchedulesSeeder::class,
        ]);
    }
}
