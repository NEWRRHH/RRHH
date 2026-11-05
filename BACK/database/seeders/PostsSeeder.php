<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('posts')->insert([
            ['title' => 'Welcome to the API dashboard', 'body' => '3 usuarios avisados', 'user_id' => 1, 'status' => 'published', 'created_at' => now()],
        ]);
    }
}
