<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FollowsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('follows')->insert([
            ['following_user_id' => 1, 'followed_user_id' => 2, 'created_at' => now()],
            ['following_user_id' => 1, 'followed_user_id' => 3, 'created_at' => now()],
        ]);
    }
}
