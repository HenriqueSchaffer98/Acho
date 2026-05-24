<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantService
{
    private const CACHE_TTL = 60;

    private const MISS_SENTINEL = '__miss__';

    /**
     * Resolve a tenant by slug, using Redis cache (TTL: 60s).
     *
     * Uses the pgsql_migrator connection for the database lookup so the query
     * runs before app.tenant_id is set on the main connection (ADR-001, R2).
     *
     * Caches only the raw attribute array (not the Eloquent model itself) to
     * avoid Laravel 13's `cache.serializable_classes` restriction, which by
     * default rejects all classes on unserialize as defense against gadget
     * chain attacks if APP_KEY leaks.
     */
    public function resolveBySlug(string $slug): ?Tenant
    {
        $cacheKey = "tenant:{$slug}";

        $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug): array|string {
            $tenant = Tenant::on('pgsql_migrator')
                ->where('slug', $slug)
                ->first();

            return $tenant === null ? self::MISS_SENTINEL : $tenant->getAttributes();
        });

        if ($cached === self::MISS_SENTINEL) {
            return null;
        }

        return $this->hydrate($cached);
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

    /** @param array<string, mixed> $attributes */
    private function hydrate(array $attributes): Tenant
    {
        $tenant = new Tenant;
        $tenant->setRawAttributes($attributes, true);
        $tenant->exists = true;
        $tenant->setConnection('pgsql_migrator');

        return $tenant;
    }
}
