<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/auth')
                ->group(base_path('routes/auth.php'));

            Route::middleware('api')
                ->prefix('api/admin')
                ->group(base_path('routes/admin.php'));
            
            Route::middleware('api')
                ->prefix('api/organizer')
                ->group(base_path('routes/organizer.php'));
                
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/test.php'));

            Route::middleware('api')
                ->prefix('api/v2')
                ->group(base_path('routes/v2.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'organizer' => \App\Http\Middleware\OrganizerMiddleware::class,
            'account.active' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'email.verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
