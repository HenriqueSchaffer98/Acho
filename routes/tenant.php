<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Tenant routes — {slug}.acho.test
// TenantResolver resolves and binds the tenant; SetTenantContext injects
// app.tenant_id into the Postgres connection (ADR-001, ADR-016).
Route::domain('{slug}.' . config('app.base_domain', 'acho.test'))
    ->middleware(['tenant.resolve', 'tenant.context'])
    ->group(function () {
        // Placeholder until the public storefront (ADR-006) is implemented.
        Route::get('/', function (string $slug) {
            return response("Tenant {$slug} resolvido", 200);
        });
    });
