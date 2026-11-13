<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * Read-only view of the permission catalog: the codes are used by the
 * application logic, so they are defined by migrations, not edited here.
 */
class PermissionCatalogController extends Controller
{
    public function index()
    {
        // in how many roles (of any company) each permission is granted
        $usage = DB::table('role_permission')
            ->select('permission_id', DB::raw('COUNT(*) as roles_count'))
            ->groupBy('permission_id')
            ->pluck('roles_count', 'permission_id');

        $permissions = Permission::orderBy('code')->get(['id', 'code', 'name', 'description'])
            ->map(fn (Permission $p) => $p->toArray() + ['roles_count' => (int) ($usage[$p->id] ?? 0)]);

        return response()->json($permissions);
    }
}
