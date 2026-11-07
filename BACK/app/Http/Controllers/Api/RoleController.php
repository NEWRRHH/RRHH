<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Roles (permission profiles) management. Admin only ("roles.manage" is not
 * grantable through a role, see AppServiceProvider).
 */
class RoleController extends Controller
{
    /**
     * Permission catalog + every role with its permission codes.
     */
    public function catalog(Request $request)
    {
        if ($request->user()->cannot('roles.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $permissions = Permission::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'description']);

        $roles = Role::query()
            ->with('permissions:id,code')
            ->withCount('users')
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->presentRole($role))
            ->values();

        return response()->json([
            'permissions' => $permissions,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->cannot('roles.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            'description' => 'nullable|string|max:255',
            'permission_codes' => 'nullable|array',
            'permission_codes.*' => 'string|exists:permissions,code',
        ]);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
            ]);
            $role->permissions()->sync($this->permissionIds($data['permission_codes'] ?? []));
            return $role;
        });

        return response()->json(['status' => 'ok', 'role' => $this->presentRole($role->fresh())], 201);
    }

    public function update(Request $request, int $id)
    {
        if ($request->user()->cannot('roles.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $role = Role::find($id);
        if (! $role) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => 'nullable|string|max:255',
        ]);

        $role->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? $role->description,
        ]);

        return response()->json(['status' => 'ok', 'role' => $this->presentRole($role->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        if ($request->user()->cannot('roles.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $role = Role::withCount('users')->find($id);
        if (! $role) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }
        if ($role->is_admin) {
            return response()->json(['message' => 'El rol de administrador no se puede eliminar'], 422);
        }
        if ($role->users_count > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el rol porque tiene ' . $role->users_count . ' usuario(s). Reasignalos antes de eliminar.',
            ], 422);
        }

        $role->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Replace the permission set of a role.
     */
    public function updatePermissions(Request $request, int $id)
    {
        if ($request->user()->cannot('roles.manage')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $role = Role::find($id);
        if (! $role) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }
        if ($role->is_admin) {
            // The admin role always holds every permission implicitly.
            return response()->json(['message' => 'El rol de administrador tiene todos los permisos'], 422);
        }

        $data = $request->validate([
            'permission_codes' => 'nullable|array',
            'permission_codes.*' => 'string|max:100',
        ]);

        $ids = $this->permissionIds($data['permission_codes'] ?? []);
        $role->permissions()->sync($ids);

        return response()->json([
            'status' => 'ok',
            'permission_codes' => Permission::whereIn('id', $ids)->orderBy('code')->pluck('code')->values(),
        ]);
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, int>
     */
    private function permissionIds(array $codes): array
    {
        $codes = array_values(array_unique($codes));
        if (! count($codes)) {
            return [];
        }

        return Permission::whereIn('code', $codes)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function presentRole(Role $role): array
    {
        $role->loadMissing('permissions:id,code');
        if (! isset($role->users_count)) {
            $role->loadCount('users');
        }

        return [
            'id' => (int) $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_admin' => (bool) $role->is_admin,
            'users_count' => (int) $role->users_count,
            'permission_codes' => $role->is_admin
                ? Permission::orderBy('code')->pluck('code')->values()->all()
                : $role->permissions->pluck('code')->sort()->values()->all(),
        ];
    }
}
