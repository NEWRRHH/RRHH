<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiters used by the API routes (routes/api.php).
        // Without these the login/register endpoints were unlimited and
        // vulnerable to brute force / account stuffing.

        // Authenticated API traffic: keyed per user (falls back to IP when the
        // request is not authenticated).
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: 'ip:'.$request->ip();

            return Limit::perMinute(120)->by($key);
        });

        // Login: 5 attempts/min per email + 20/min per IP.
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by(Str::lower((string) $request->input('email'))),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        // Public registration: 5 accounts/min per IP.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by('ip:'.$request->ip());
        });
    }
}
