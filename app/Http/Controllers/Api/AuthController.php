<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required','confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        // record session token on user (attendance will be started manually via API)
        $user->session_token = $token;
        $user->save();

        return response()->json(['user' => $user, 'token' => $token], 201)
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function login(Request $request)
    {
        // force JSON response format (protect against missing Accept header from client)
        $request->headers->set('Accept', 'application/json');

        // debugging: log raw body and headers
        logger('login raw content', ['content' => $request->getContent(), 'headers' => $request->headers->all()]);

        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // record session token on user (attendance start is manual now)
        $user->session_token = $token;
        $user->save();

        return response()->json(['user' => $user, 'token' => $token])
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user && $request->bearerToken()) {
            // close attendance if exists for this session
            $token = $request->bearerToken();
            $now = Carbon::now();
            $att = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('session_token', $token)
                ->where('status', 'en_trabajo')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($att) {
                // parse combined date/time rather than assuming slashes; wrap in try
                try {
                    $start = Carbon::parse($att->date . ' ' . $att->start_time);
                } catch (\Exception $e) {
                    $start = Carbon::parse($att->start_time);
                }
                $diffSeconds = $start->diffInSeconds($now);
                $hours = gmdate('H:i:s', $diffSeconds);
                DB::table('attendances')
                    ->where('id', $att->id)
                    ->update([
                        'end_time' => $now->toTimeString(),
                        'end_date' => $now->format('Y/m/d'),
                        'hours_worked' => $hours,
                        'status' => 'fuera_trabajo',
                        'updated_at' => now(),
                    ]);
            }

            $request->user()->currentAccessToken()->delete();
            // clear stored session token
            $user->session_token = null;
            $user->save();
        }

        return response()->json(['message' => 'Logged out'])
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function user(Request $request)
    {
        return response()->json($request->user())
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Update authenticated user's profile information.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'photo' => 'nullable|image|max:2048',
        ]);
        $user->name = $data['name'];
        $user->email = $data['email'];
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $user->photo = '/storage/' . $path;
        }
        $user->save();
        return response()->json($user)
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Start a new attendance session for the authenticated user.
     * This is called manually (e.g. from a header button) instead of on login.
     */
    public function startAttendance(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // use the current bearer token as session token
        $token = $request->bearerToken() ?: $user->session_token;
        if (! $token) {
            // should not happen if the user is authenticated via Sanctum
            $token = hash('sha256', Str::random(40));
            $user->session_token = $token;
            $user->save();
        }

        // update or create an attendance record
        $open = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($open) {
            DB::table('attendances')->where('id', $open->id)->update([
                'session_token' => $token,
                'updated_at' => now(),
            ]);
        } else {
            $now = Carbon::now();
            DB::table('attendances')->insert([
                'user_id' => $user->id,
                'session_token' => $token,
                'date' => $now->format('Y/m/d'),
                'start_time' => $now->toTimeString(),
                'status' => 'en_trabajo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Check whether the current user already has an open attendance.
     */
    public function attendanceStatus(Request $request)
    {
        $user = $request->user();
        $working = false;
        if ($user) {
            $working = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->exists();
        }
        return response()->json(['working' => $working]);
    }

    /**
     * Return the current open attendance record (if any).
     */
    public function currentAttendance(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(null);
        }
        $att = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();
        return response()->json($att);
    }

    /**
     * Stop the current attendance session without logging out.
     */
    public function stopAttendance(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $now = Carbon::now();
        // fetch the most recent open attendance for the user regardless of session token
        $att = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('status', 'en_trabajo')
            ->whereNull('end_date')
            ->orderBy('created_at', 'desc')
            ->first();
        if ($att) {
            // debug before computing
            \Log::info('stopAttendance debug', [
                'att_start' => $att->date . ' ' . $att->start_time,
                'now' => $now->toDateTimeString(),
                'app_tz' => config('app.timezone'),
                'server_tz' => date_default_timezone_get(),
            ]);
            // parse start date/time
            try {
                $start = Carbon::parse($att->date . ' ' . $att->start_time);
            } catch (\Exception $e) {
                $start = Carbon::parse($att->start_time);
            }
            // use absolute diff and format as H:i:s
            $diffSeconds = $start->diffInSeconds($now);
            $hours = gmdate('H:i:s', $diffSeconds);
            DB::table('attendances')
                ->where('id', $att->id)
                ->update([
                    'end_time' => $now->toTimeString(),
                    'end_date' => $now->format('Y/m/d'),
                    'hours_worked' => $hours,
                    'status' => 'fuera_trabajo',
                    'updated_at' => now(),
                ]);
        } else {
            // no open attendance found: maybe token changed or already closed
        }
        return response()->json(['status' => 'stopped']);
    }

    /**
     * Provide attendance + schedule info for the current user.
     */
    public function attendanceInfo(Request $request)
    {
        $user = $request->user();
        $info = ['working' => false, 'attendance' => null, 'schedule' => null];
        if ($user) {
            $info['working'] = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->exists();
            $info['attendance'] = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('status', 'en_trabajo')
                ->whereNull('end_date')
                ->orderBy('created_at', 'desc')
                ->first();

            // try direct schedule on user or via pivot
            $sched = DB::table('schedules')
                ->where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->first();
            if (! $sched) {
                $sched = DB::table('schedules')
                    ->join('user_schedules', 'schedules.id', '=', 'user_schedules.schedule_id')
                    ->where('user_schedules.user_id', $user->id)
                    ->select('schedules.*')
                    ->orderBy('schedules.id', 'desc')
                    ->first();
            }
            $info['schedule'] = $sched;
        }
        return response()->json($info);
    }

    /**
     * Change password for authenticated user.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => 'required',
            'password' => ['required','confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()],
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password incorrect'], 422);
        }
        $user->password = Hash::make($data['password']);
        $user->save();
        return response()->json(['status'=>'ok']);
    }

    /**
     * Update schedule for authenticated user (admin only).
     */
    public function updateSchedule(Request $request)
    {
        $user = $request->user();
        if ($user->user_type_id !== 1) {
            return response()->json(['message'=>'Forbidden'], 403);
        }
        $data = $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ]);
        // find existing schedule
        $sched = DB::table('schedules')->where('user_id', $user->id)->first();
        if ($sched) {
            DB::table('schedules')->where('id', $sched->id)->update([
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'updated_at' => now(),
            ]);
        } else {
            DB::table('schedules')->insert([
                'user_id' => $user->id,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return response()->json(['status'=>'ok']);
    }

    /**
     * List all employees (admin only) with team name.
     */
    public function employees(Request $request)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employees = DB::table('users')
            ->leftJoin('teams', 'users.team_id', '=', 'teams.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.photo',
                'users.profile_photo_path',
                'users.team_id',
                'teams.name as team_name'
            )
            ->whereNull('users.deleted_at')
            ->orderBy('users.id', 'asc')
            ->get();

        $teams = DB::table('teams')->select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'employees' => $employees,
            'teams' => $teams,
        ]);
    }

    /**
     * Get one employee with schedule and teams (admin only).
     */
    public function getEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $schedule = DB::table('schedules')
            ->where('user_id', $employee->id)
            ->orderBy('id', 'desc')
            ->first();

        if (! $schedule) {
            $schedule = DB::table('schedules')
                ->join('user_schedules', 'schedules.id', '=', 'user_schedules.schedule_id')
                ->where('user_schedules.user_id', $employee->id)
                ->select('schedules.*')
                ->orderBy('schedules.id', 'desc')
                ->first();
        }

        $teams = DB::table('teams')->select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'employee' => $employee,
            'schedule' => $schedule,
            'teams' => $teams,
        ]);
    }

    /**
     * Update one employee (admin only).
     */
    public function updateEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = User::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $employee->id,
            'team_id' => 'nullable|integer|exists:teams,id',
            'photo' => 'nullable|image|max:2048',
            'password' => ['nullable','confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);

        $employee->name = $data['name'];
        $employee->email = $data['email'];
        $employee->team_id = $data['team_id'] ?? null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $employee->photo = '/storage/' . $path;
        }
        if (! empty($data['password'])) {
            $employee->password = Hash::make($data['password']);
        }
        $employee->save();

        if (! empty($data['start_time']) && ! empty($data['end_time'])) {
            $sched = DB::table('schedules')->where('user_id', $employee->id)->first();
            if ($sched) {
                DB::table('schedules')->where('id', $sched->id)->update([
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('schedules')->insert([
                    'user_id' => $employee->id,
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Delete one employee (admin only).
     */
    public function deleteEmployee(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || $user->user_type_id !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($user->id === $id) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario'], 422);
        }

        $employee = User::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $employee->delete();
        return response()->json(['status' => 'ok']);
    }
}

