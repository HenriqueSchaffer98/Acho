<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->resolveTenantId($request);

        if ($tenantId !== null) {
            // Set app.tenant_id on the pgsql connection so RLS policies
            // can use current_setting('app.tenant_id', true) (ADR-001).
            DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantId]);
        }

        return $next($request);
    }

    private function resolveTenantId(Request $request): ?string
    {
        // 1. Authenticated request: trust the JWT (ADR-014).
        $payload = $request->attributes->get('auth.payload');

        if (is_array($payload) && isset($payload['tenant_id']) && is_string($payload['tenant_id'])) {
            return $payload['tenant_id'];
        }

        // 2. Anonymous request: fall back to subdomain-resolved tenant (ADR-016).
        if (app()->bound('currentTenant')) {
            /** @var Tenant $tenant */
            $tenant = app('currentTenant');

            return $tenant->id;
        }

        return null;
    }
}
