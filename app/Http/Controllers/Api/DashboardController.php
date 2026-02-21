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
        $today = Carbon::today();

        $usersCount = User::count();
        $postsCount = DB::table('posts')->count();
        // total open attendances in the system (used previously as "attendances_today")
        // requirements changed: only records that are unfinished
        // only consider records for today with unfinished state
        $attendancesToday = DB::table('attendances')
            ->whereDate('date', $today)
            ->where(function ($q) {
                $q->where('status', 'en_trabajo')
                  ->orWhereNull('end_time')
                  ->orWhereNull('end_date');
            })
            ->count();

        // notifications list for current user from notifications table
        $notifications = [];
        $unreadCount = 0;
        if ($request->user()) {
            // return only unread notifications for the card
            $notifications = DB::table('notifications')
                ->where('user_id', $request->user()->id)
                ->where('read', 0)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->get();
            // count of unread (same as above query)
            $unreadCount = $notifications->count();
        }
        $birthdaysToday = User::whereNotNull('birth_date')
            ->whereRaw('MONTH(birth_date) = ? AND DAY(birth_date) = ?', [$today->month, $today->day])
            ->count();

        return response()->json([
            'message' => 'Welcome to the API dashboard',
            'stats' => [
                'users_count' => $usersCount,
                'posts_count' => $postsCount,
                'attendances_today' => $attendancesToday,
                'birthdays_today' => $birthdaysToday,
            ],
            'notifications' => $notifications,
            'unread_notifications' => $unreadCount,
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

    /**
     * Mark a single notification as read (authenticated user only).
     *
     * The frontend will call this when opening the modal so the "read" flag
     * is updated in the database immediately.
     */
    public function markNotificationRead(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // ensure the notification belongs to the current user
        $updated = DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->update(['read' => 1, 'updated_at' => now()]);

        if ($updated) {
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }

    /**
     * Return next vacation event for the authenticated user.
     * Reads from events JOIN event_types where type name contains 'vacaci'.
     */
    public function vacationsMe(Request $request)
    {
        $user  = $request->user();
        $today = Carbon::today();

        $event = DB::table('events')
            ->join('event_types', 'events.event_type_id', '=', 'event_types.id')
            ->where('events.user_id', $user->id)
            ->whereNull('events.deleted_at')
            ->whereRaw('LOWER(event_types.name) LIKE ?', ['%vacaci%'])
            ->where('events.start_at', '>=', $today->toDateTimeString())
            ->orderBy('events.start_at', 'asc')
            ->select('events.start_at', 'events.end_at')
            ->first();

        if (!$event) {
            return response()->json([
                'days_until_vacation' => null,
                'vacation_days'       => null,
            ])->header('Access-Control-Allow-Origin', '*');
        }

        $start        = Carbon::parse($event->start_at)->startOfDay();
        $end          = $event->end_at ? Carbon::parse($event->end_at)->startOfDay() : $start->copy();
        $daysUntil    = (int) $today->diffInDays($start, false);
        $vacationDays = (int) $start->diffInDays($end) + 1; // inclusive

        return response()->json([
            'days_until_vacation' => max(0, $daysUntil),
            'vacation_days'       => $vacationDays,
        ])->header('Access-Control-Allow-Origin', '*');
    }
}
