<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Broadcast;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // OPTIONS preflight for broadcasting/auth (no auth required).
            \Illuminate\Support\Facades\Route::options('broadcasting/auth', function () {
                return response('', 200)
                    ->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
            });
            // Register broadcasting auth route with Sanctum so Bearer tokens
            // are accepted. Must be done here (not via the 'channels' shortcut)
            // because the shortcut uses the 'auth' guard instead of 'auth:sanctum'.
            Broadcast::routes(['middleware' => ['auth:sanctum', \App\Http\Middleware\Cors::class]]);
            require base_path('routes/channels.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
