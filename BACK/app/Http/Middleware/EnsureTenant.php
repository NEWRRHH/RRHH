<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the request to the authenticated user's company.
 *
 * Must run after auth:sanctum. From here on every company-owned model is
 * filtered by that company and new rows are assigned to it.
 *
 * - Regular users: their company must exist and be active.
 * - Super admin: no tenant filter (sees every company). Writes on tenant data
 *   are rejected because the super admin belongs to no company; managing
 *   companies is done from the super admin panel.
 */
class EnsureTenant
{
    /** Endpoints a super admin may write to (their own account). */
    private const SUPER_ADMIN_WRITABLE = ['api/logout', 'api/user', 'api/user/password'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            if (! $request->isMethodSafe() && ! in_array($request->path(), self::SUPER_ADMIN_WRITABLE, true)) {
                return response()->json([
                    'message' => 'El superadmin no pertenece a ninguna empresa: gestiona las empresas desde el panel de administracion.',
                ], 403);
            }

            Tenant::clear();

            return $next($request);
        }

        $company = $user->company;
        if (! $company) {
            return response()->json(['message' => 'Tu usuario no pertenece a ninguna empresa.'], 403);
        }
        if (! $company->isActive()) {
            return response()->json(['message' => 'La empresa esta suspendida. Contacta con el soporte.'], 403);
        }

        Tenant::set((int) $company->id);
        try {
            return $next($request);
        } finally {
            Tenant::clear();
        }
    }
}
