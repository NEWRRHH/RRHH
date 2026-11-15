<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\EmployeeController;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Ver como" sessions (tokens named "view-as:<viewer id>") are read-only:
 * - only GET/HEAD requests, except leaving the session;
 * - the employee's private chat (conversations, messages, attachments) and
 *   realtime channels stay closed, even for reading.
 */
class ReadOnlyViewAs
{
    /** Private communications are never shown in "Ver como". */
    private const PRIVATE_PATHS = [
        'api/notifications/conversations',
        'api/notifications/conversation/*',
        'api/notifications/attachments/*',
        'api/notifications/users',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        $name = $token instanceof PersonalAccessToken ? (string) $token->name : '';
        if (! str_starts_with($name, EmployeeController::VIEW_AS_TOKEN_PREFIX)) {
            return $next($request);
        }

        if ($request->is('api/view-as/stop')) {
            return $next($request);
        }

        if ($request->is(...self::PRIVATE_PATHS)) {
            return response()->json([
                'message' => 'El chat es privado: no se muestra en el modo "Ver como".',
                'reason' => 'view_as_private',
            ], 403);
        }

        if (! $request->isMethodSafe()) {
            return response()->json([
                'message' => 'Modo "Ver como": solo lectura, no se pueden hacer cambios.',
                'reason' => 'view_as_read_only',
            ], 403);
        }

        return $next($request);
    }
}
