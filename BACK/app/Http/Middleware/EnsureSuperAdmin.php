<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super admin panel (/api/admin/*): platform owner only.
 * Runs with no tenant filter so queries see every company.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isSuperAdmin() || $user->disabled_at) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Tenant::clear();

        return $next($request);
    }
}
