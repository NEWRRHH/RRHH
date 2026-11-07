<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Companies (tenants) management for the super admin.
 */
class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:150',
            'status' => 'nullable|in:active,suspended',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $companies = Company::query()
            ->withCount('users')
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->addSelect(['last_login_at' => User::query()
                ->selectRaw('MAX(last_login_at)')
                ->whereColumn('users.company_id', 'companies.id')])
            ->orderByDesc('created_at')
            ->paginate($data['per_page'] ?? 20);

        return response()->json($companies);
    }

    /**
     * Create a company with its admin (same provisioning as public sign-up).
     */
    public function store(Request $request, CompanyProvisioner $provisioner)
    {
        $data = $request->validate([
            'company_name' => 'required|string|min:2|max:150',
            'plan' => 'nullable|string|max:50',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        ['company' => $company] = $provisioner->provision($data['company_name'], [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
        ]);
        if (! empty($data['plan'])) {
            $company->update(['plan' => $data['plan']]);
        }

        Audit::log('company.created', $company, ['admin_email' => $data['admin_email']]);

        return response()->json(['status' => 'ok', 'company' => $company->fresh()], 201);
    }

    public function show(int $id)
    {
        $company = Company::withTrashed()->findOrFail($id);

        $users = User::query()
            ->where('company_id', $company->id)
            ->with(['role:id,name,is_admin', 'team:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'team_id', 'last_login_at', 'disabled_at', 'created_at']);

        $roles = Role::query()
            ->where('company_id', $company->id)
            ->withCount('users')
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get(['id', 'name', 'is_admin']);

        $documents = Document::query()->where('company_id', $company->id);

        return response()->json([
            'company' => $company,
            'usage' => [
                'users' => $users->count(),
                'users_active_30d' => $users->filter(fn ($u) => $u->last_login_at && $u->last_login_at->gte(now()->subDays(30)))->count(),
                'documents' => (clone $documents)->count(),
                'storage_bytes' => (int) (clone $documents)->sum('size'),
                'attendances_30d' => Attendance::query()->where('company_id', $company->id)->where('created_at', '>=', now()->subDays(30))->count(),
                'requests_pending' => Event::query()->where('company_id', $company->id)->where('approval_status', 'pending')->count(),
            ],
            'users' => $users,
            'roles' => $roles,
            'audit' => AuditLog::where('company_id', $company->id)->orderByDesc('created_at')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $company = Company::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'plan' => 'nullable|string|max:50',
        ]);

        $before = $company->only(['name', 'plan']);
        $company->update(['name' => trim($data['name']), 'plan' => $data['plan'] ?? null]);
        Audit::log('company.updated', $company, ['before' => $before, 'after' => $company->only(['name', 'plan'])]);

        return response()->json(['status' => 'ok', 'company' => $company]);
    }

    public function suspend(int $id)
    {
        return $this->setStatus($id, Company::STATUS_SUSPENDED, 'company.suspended');
    }

    public function reactivate(int $id)
    {
        return $this->setStatus($id, Company::STATUS_ACTIVE, 'company.reactivated');
    }

    /**
     * Soft delete: users can no longer log in, data is kept.
     */
    public function destroy(int $id)
    {
        $company = Company::findOrFail($id);
        $company->delete();
        Audit::log('company.deleted', $company, ['name' => $company->name]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * "Entrar como empresa": a 2h token for the company's admin, named after
     * the super admin so /api/user can show the support banner and the
     * session can be audited and closed.
     */
    public function impersonate(Request $request, int $id)
    {
        $company = Company::findOrFail($id);
        if (! $company->isActive()) {
            return response()->json(['message' => 'La empresa esta suspendida'], 422);
        }

        $admin = User::query()
            ->where('company_id', $company->id)
            ->whereNull('disabled_at')
            ->whereHas('role', fn ($q) => $q->where('is_admin', true))
            ->orderBy('id')
            ->first();
        if (! $admin) {
            return response()->json(['message' => 'La empresa no tiene un administrador activo'], 422);
        }

        $token = $admin->createToken(
            AuthController::IMPERSONATION_TOKEN_PREFIX . $request->user()->id,
            ['*'],
            now()->addHours(2),
        )->plainTextToken;

        Audit::log('company.impersonation_started', $company, ['as_user_id' => $admin->id, 'as_user_email' => $admin->email]);

        return response()->json(['token' => $token, 'company_id' => $company->id, 'user' => $admin->only(['id', 'name', 'email'])]);
    }

    private function setStatus(int $id, string $status, string $action)
    {
        $company = Company::findOrFail($id);
        $company->update(['status' => $status]);
        Audit::log($action, $company);

        return response()->json(['status' => 'ok', 'company' => $company]);
    }
}
