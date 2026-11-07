<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $this->configureAuthorization();

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

    /**
     * Single authorization entry point.
     *
     * - The admin role can do everything.
     * - Abilities named like a permission code ("employees.edit") are granted
     *   when the user's role holds that permission: $user->can('employees.edit').
     * - Anything else (e.g. "update" on a User) falls through to the policies.
     */
    private function configureAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            if (str_contains($ability, '.') && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        // Admin-only abilities that are deliberately not grantable through a
        // role permission (a role able to edit roles could escalate itself).
        Gate::define('roles.manage', fn () => false);
    }
}
