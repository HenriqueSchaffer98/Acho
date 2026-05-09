<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantService
{
    private const CACHE_TTL = 60;

    /**
     * Resolve a tenant by slug, using Redis cache (TTL: 60s).
     *
     * Uses the pgsql_migrator connection for the database lookup so the query
     * runs before app.tenant_id is set on the main connection (ADR-001, R2).
     */
    public function resolveBySlug(string $slug): ?Tenant
    {
        $cacheKey = "tenant:{$slug}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug): ?Tenant {
            return Tenant::on('pgsql_migrator')
                ->where('slug', $slug)
                ->first();
        });
    }

    /**
     * Resolve a tenant by custom domain.
     *
     * Prepared for v2 (ADR-016). Returns null until custom domain routing
     * is implemented.
     */
    public function resolveByCustomDomain(string $domain): ?Tenant
    {
        return null;
    }

    public function invalidateCache(Tenant $tenant): void
    {
        Cache::forget("tenant:{$tenant->slug}");
    }
}
