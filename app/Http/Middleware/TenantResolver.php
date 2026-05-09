<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantNotFoundException;
use App\Exceptions\TenantSuspendedException;
use App\Services\Tenant\TenantService;
use App\Support\SubdomainHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantResolver
{
    public function __construct(private readonly TenantService $tenantService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $baseDomain = config('app.base_domain', 'acho.test');
        $host = $request->getHost();

        $slug = SubdomainHelper::extractSlug($host, $baseDomain);

        if ($slug === null) {
            throw new TenantNotFoundException($host);
        }

        $tenant = $this->tenantService->resolveBySlug($slug);

        if ($tenant === null) {
            throw new TenantNotFoundException($slug);
        }

        if ($tenant->isSuspended()) {
            throw new TenantSuspendedException($slug);
        }

        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
