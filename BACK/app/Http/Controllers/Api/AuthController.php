<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Authentication and the logged-in user's own profile.
 */
class AuthController extends Controller
{
    public function __construct(private AttendanceService $attendance)
    {
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        // record session token hash on user (attendance will be started manually via API)
        $user->session_token = $this->attendance->sessionTokenHash($token);
        $user->save();

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        // force JSON response format (protect against missing Accept header from client)
        $request->headers->set('Accept', 'application/json');

        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // record session token hash on user (attendance start is manual now)
        $user->session_token = $this->attendance->sessionTokenHash($token);
        $user->save();

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user && $request->bearerToken()) {
            // close attendance if exists for this session
            $tokenHash = $this->attendance->sessionTokenHash($request->bearerToken());
            $now = Carbon::now();
            $att = Attendance::table()
                ->where('user_id', $user->id)
                ->where('session_token', $tokenHash)
                ->where('status', 'en_trabajo')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($att) {
                $diffSeconds = $this->attendance->workedSecondsWithPause($att, $now);
                $hours = gmdate('H:i:s', $diffSeconds);
                Attendance::table()
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

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Logged-in user + the permission flags the frontend uses to show/hide UI.
     * Every flag is derived from the user's single role.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user->loadMissing(['role:id,name,is_admin', 'company:id,name,slug,status']);

        $payload = $user->toArray();
        $payload['permissions'] = $user->permissionCodes();
        $payload['is_super_admin'] = $user->isSuperAdmin();
        $payload['is_admin'] = $user->isAdmin();
        // legacy flag name kept for the frontend: "can see everybody's attendance"
        $payload['is_hr_team'] = $user->hasPermission('attendance.view_all');
        $payload['can_view_employee_details'] = $user->hasPermission('employees.view_details');
        $payload['can_delete_employee'] = $user->hasPermission('employees.delete');
        $payload['can_create_employee'] = $user->hasPermission('employees.create');
        $payload['can_edit_employee'] = $user->hasPermission('employees.edit');
        $payload['can_manage_schedules'] = $user->hasPermission('schedules.manage');
        $payload['can_manage_teams'] = $user->hasPermission('teams.manage');
        $payload['can_access_announcements'] = $user->hasPermission('announcements.manage');
        $payload['can_manage_announcements'] = $user->hasPermission('announcements.manage');
        $payload['can_access_settings'] = $user->isAdmin()
            || $user->hasPermission('schedules.manage')
            || $user->hasPermission('teams.manage');
        $payload['can_view_vacations'] = $user->hasPermission('vacations.view');
        $payload['can_create_vacations'] = $user->hasPermission('vacations.create');
        $payload['can_edit_vacations'] = $user->hasPermission('vacations.edit');
        $payload['can_view_requests'] = $user->hasPermission('requests.view');
        $payload['can_view_documents'] = $user->hasPermission('documents.view');
        $payload['can_upload_documents'] = $user->hasPermission('documents.upload');
        $payload['can_view_reports'] = $user->hasPermission('reports.view');

        return response()->json($payload);
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
            'birth_date' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'date'],
            'dni' => 'nullable|string|max:30',
            'social_security_number' => 'nullable|string|max:50',
        ]);
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->birth_date = $data['birth_date'] ?? $user->birth_date;
        $user->dni = $data['dni'] ?? $user->dni;
        $user->social_security_number = $data['social_security_number'] ?? $user->social_security_number;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $user->photo = '/storage/' . $path;
        }
        $user->save();
        return response()->json($user);
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
}
