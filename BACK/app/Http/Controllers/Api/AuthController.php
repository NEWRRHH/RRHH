<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\CompanyProvisioner;
use App\Services\PlanLimits;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authentication and the logged-in user's own profile.
 */
class AuthController extends Controller
{
    /** Name of the temporary tokens issued by "entrar como empresa": "impersonation:<super admin id>". */
    public const IMPERSONATION_TOKEN_PREFIX = 'impersonation:';

    public function __construct(private AttendanceService $attendance)
    {
    }

    /**
     * Public sign-up = create a company. The person signing up becomes the
     * company Admin and can then create the rest of the users.
     */
    public function register(Request $request, CompanyProvisioner $provisioner)
    {
        $data = $request->validate([
            'company_name' => 'required|string|min:2|max:150',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        ['company' => $company, 'admin' => $user] = $provisioner->provision($data['company_name'], [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Audit::log('company.registered', $company, actor: $user);

        $user->load(['role:id,name,is_admin', 'company:id,name,slug,status']);

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

        if ($user->disabled_at) {
            return response()->json(['message' => 'Tu cuenta esta desactivada. Contacta con el soporte.'], 403);
        }

        // A regular user can only log in while their company is active.
        if (! $user->isSuperAdmin() && ! $user->company?->isActive()) {
            return response()->json(['message' => 'La empresa esta suspendida o no existe. Contacta con el soporte.'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // record session token hash on user (attendance start is manual now)
        $user->session_token = $this->attendance->sessionTokenHash($token);
        $user->last_login_at = now();
        $user->save();

        Audit::log('auth.login', $user, actor: $user, companyId: $user->company_id);

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
        // Super admin browsing this company through "entrar como empresa".
        $payload['impersonated_by'] = $this->impersonator($request);
        $payload['is_admin'] = $user->isAdmin();
        // legacy flag name kept for the frontend: "can see everybody's attendance"
        $payload['is_hr_team'] = $user->hasPermission('attendance.view_all');
        $payload['can_view_employee_details'] = $user->hasPermission('employees.view_details');
        $payload['can_delete_employee'] = $user->hasPermission('employees.delete');
        $payload['can_create_employee'] = $user->hasPermission('employees.create');
        $payload['can_edit_employee'] = $user->hasPermission('employees.edit');
        $payload['can_manage_schedules'] = $user->hasPermission('schedules.manage');
        $payload['can_manage_teams'] = $user->hasPermission('teams.manage');
        $payload['can_manage_holidays'] = $user->hasPermission('holidays.manage');
        $payload['can_manage_dashboard'] = $user->hasPermission('dashboard.manage');
        // plan limits vs usage (company admins see it in the settings screen)
        $payload['plan_usage'] = $user->company && $user->isAdmin() ? app(PlanLimits::class)->usage($user->company) : null;
        $payload['can_access_announcements'] = $user->hasPermission('announcements.manage');
        $payload['can_manage_announcements'] = $user->hasPermission('announcements.manage');
        $payload['can_access_settings'] = $user->isAdmin()
            || $user->hasPermission('schedules.manage')
            || $user->hasPermission('teams.manage')
            || $user->hasPermission('dashboard.manage');
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
     * End a super admin impersonation session: revoke the temporary token.
     * The Nuxt server then restores the super admin's own session cookie.
     */
    public function stopImpersonation(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        $impersonator = $this->impersonator($request);
        if (! $token || ! $impersonator) {
            return response()->json(['message' => 'No hay una sesion de soporte activa'], 422);
        }

        Audit::log(
            'company.impersonation_stopped',
            $request->user()->company,
            ['as_user_id' => $request->user()->id],
            actor: User::withoutGlobalScopes()->find($impersonator['id']),
        );
        $token->delete();

        return response()->json(['status' => 'ok', 'company_id' => $request->user()->company_id]);
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function impersonator(Request $request): ?array
    {
        $token = $request->user()?->currentAccessToken();
        $name = $token instanceof PersonalAccessToken ? (string) $token->name : '';
        if (! str_starts_with($name, self::IMPERSONATION_TOKEN_PREFIX)) {
            return null;
        }

        $super = User::withoutGlobalScopes()->find((int) substr($name, strlen(self::IMPERSONATION_TOKEN_PREFIX)));

        return $super && $super->isSuperAdmin() ? ['id' => $super->id, 'name' => $super->name] : null;
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
            $path = $request->file('photo')->store('avatars/' . ($user->company_id ?? 'platform'), 'public');
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
