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
        if (app()->bound('currentTenant')) {
            /** @var Tenant $tenant */
            $tenant = app('currentTenant');

            // Set app.tenant_id on the pgsql connection so RLS policies
            // can use current_setting('app.tenant_id', true) (ADR-001).
            DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenant->id]);
        }

        return $next($request);
    }
}
