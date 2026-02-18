<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventsSeeder extends Seeder
{
    public function run(): void
    {
        // create a sample vacation for the admin user (id = 1)
        $vacType = DB::table('event_types')->where('name', 'Vacaciones')->first();
        if (! $vacType) return;

        $start = now()->addDays(7)->startOfDay();
        $end = (clone $start)->addDays(4)->endOfDay(); // 5 days total

        $exists = DB::table('events')
            ->where('user_id', 1)
            ->where('event_type_id', $vacType->id)
            ->whereDate('start_at', $start->toDateString())
            ->first();

        if (! $exists) {
            DB::table('events')->insert([
                'title' => 'Vacaciones',
                'start_at' => $start,
                'end_at' => $end,
                'user_id' => 1,
                'event_type_id' => $vacType->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
