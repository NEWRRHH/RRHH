<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Welcome to the API dashboard',
            'stats' => [
                'users_count' => User::count(),
            ],
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Return upcoming birthdays (authenticated)
     */
    public function birthdays(Request $request)
    {
        $today = Carbon::today();

        $users = User::whereNotNull('birth_date')
            ->get()
            ->map(function ($u) use ($today) {
                try {
                    $dob = Carbon::parse($u->birth_date);
                } catch (\Exception $e) {
                    return null;
                }

                $next = $dob->copy()->year($today->year);
                if ($next->lt($today)) {
                    $next->addYear();
                }

                return [
                    'id' => $u->id,
                    'first_name' => $u->first_name ?? $u->name ?? null,
                    'last_name' => $u->last_name ?? null,
                    'full_name' => trim(($u->first_name ?? $u->name ?? '') . ' ' . ($u->last_name ?? '')),
                    'photo' => $u->photo ?? $u->profile_photo_path ?? null,
                    'birth_date' => $dob->toDateString(),
                    'next_birthday' => $next->toDateString(),
                    'age' => $next->year - $dob->year,
                ];
            })
            ->filter()
            ->sortBy('next_birthday')
            ->values();

        return response()->json($users->take(10))->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Return users currently "en_trabajo" for today.
     */
    public function working(Request $request)
    {
        $today = Carbon::today()->toDateString();

        $rows = DB::table('attendances')
            ->where('date', $today)
            ->where('status', 'en_trabajo')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.photo', 'users.profile_photo_path')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'first_name' => $u->first_name ?? null,
                    'full_name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                    'photo' => $u->photo ?? $u->profile_photo_path ?? null,
                ];
            });

        return response()->json($rows)->header('Access-Control-Allow-Origin', '*');
    }
}
