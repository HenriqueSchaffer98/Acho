<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Tenant\TenantService;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantService::class);
    }

    public function boot(): void
    {
        // Scope the session cookie to the exact subdomain so it cannot leak
        // across tenants (ADR-016). Must run before the session middleware
        // reads/writes the cookie.
        if ($this->app->runningInConsole()) {
            return;
        }

        $host = Request::getHost();

        if (! empty($host)) {
            config(['session.domain' => $host]);
        }
    }
}
