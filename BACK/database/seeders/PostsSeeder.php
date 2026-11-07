<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\UsesDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostsSeeder extends Seeder
{
    use UsesDemoCompany;

    public function run(): void
    {
        $adminId = DB::table('users')->where('email', env('SEED_ADMIN_EMAIL', 'admin@rrhh.test'))->value('id');

        DB::table('posts')->insert([
            [
                'company_id' => $this->demoCompanyId(),
                'title' => 'Welcome to the API dashboard',
                'body' => '3 usuarios avisados',
                'user_id' => $adminId,
                'status' => 'published',
                'created_at' => now(),
            ],
        ]);
    }
}
