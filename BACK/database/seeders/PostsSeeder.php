<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('posts')->insert([
            ['title' => 'Welcome to the API dashboard', 'body' => '{"users_count": 3}', 'user_id' => 1, 'status' => 'published', 'created_at' => now()],
        ]);
    }
}
