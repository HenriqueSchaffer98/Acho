<?php

use App\Exceptions\TenantNotFoundException;
use App\Exceptions\TenantSuspendedException;
use App\Http\Middleware\AuthenticateJWT;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\TenantResolver;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Tenant routes loaded separately to apply the tenant middleware
            // group without polluting web.php (ADR-016).
            require base_path('routes/tenant.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'tenant.resolve' => TenantResolver::class,
            'tenant.context' => SetTenantContext::class,
            'auth.jwt' => AuthenticateJWT::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TenantNotFoundException $e) {
            return response()->view('tenant.not-found', [], 404);
        });

        $exceptions->render(function (TenantSuspendedException $e) {
            return response()->view('tenant.suspended', [], 403);
        });
    })->create();
