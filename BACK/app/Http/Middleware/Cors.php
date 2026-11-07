<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Comma separated list of origins allowed to call the API from a browser.
     *
     * Default locks CORS down to the local frontend instead of the previous
     * wildcard "*", which allowed any website to read API responses.
     */
    public const ALLOWED_ORIGINS_ENV = 'CORS_ALLOWED_ORIGINS';

    /**
     * Origins explicitly allowed (host-to-host, no wildcard support).
     *
     * @return array<int, string>
     */
    public static function allowedOrigins(): array
    {
        $origins = config('cors.allowed_origins');
        if (is_array($origins) && count($origins) > 0) {
            return array_values($origins);
        }

        // Fallback when the config is not booted (console tooling).
        $raw = (string) env(self::ALLOWED_ORIGINS_ENV, 'http://localhost:3000,http://127.0.0.1:3000');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Return the request origin when it is on the allow list, null otherwise.
     */
    public static function resolveOrigin(Request $request): ?string
    {
        $origin = (string) $request->headers->get('Origin', '');
        if ($origin === '') {
            return null;
        }

        return in_array($origin, self::allowedOrigins(), true) ? $origin : null;
    }

    /**
     * Handle an incoming request and add CORS headers to every response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Ensure API auth middleware returns JSON errors (401/403) instead of web redirects.
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
            if (!$request->headers->has('X-Requested-With')) {
                $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            }
        }

        // Preflight: answer directly when the origin is allowed.
        if ($request->getMethod() === 'OPTIONS') {
            $origin = self::resolveOrigin($request);
            $response = response('', 200);
            if ($origin !== null) {
                $response
                    ->header('Access-Control-Allow-Origin', $origin)
                    ->header('Vary', 'Origin')
                    ->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With')
                    ->header('Access-Control-Max-Age', '600');
            }

            return $response;
        }

        $response = $next($request);

        if (method_exists($response, 'header')) {
            $origin = self::resolveOrigin($request);
            if ($origin !== null) {
                $response->header('Access-Control-Allow-Origin', $origin)
                    ->header('Vary', 'Origin');
            }
            $response->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');
        }

        return $response;
    }
}
