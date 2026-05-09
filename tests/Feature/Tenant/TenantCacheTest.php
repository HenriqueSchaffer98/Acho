<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\Tenant\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// Tenants are created via pgsql_migrator so TenantService (which queries
// via pgsql_migrator) can see them within the same transaction (T2).
it('caches tenant on first lookup and hits cache on second', function () {
    Cache::flush();

    $tenant = Tenant::on('pgsql_migrator')->create([
        'slug' => 'cache-hit-' . uniqid(),
        'name' => 'Cache Hit',
        'status' => TenantStatus::Active,
    ]);

    $service = app(TenantService::class);
    $service->resolveBySlug($tenant->slug);

    expect(Cache::has("tenant:{$tenant->slug}"))->toBeTrue();

    $cached = $service->resolveBySlug($tenant->slug);
    expect($cached->id)->toBe($tenant->id);
});

it('invalidates cache when tenant is suspended', function () {
    Cache::flush();

    $tenant = Tenant::on('pgsql_migrator')->create([
        'slug' => 'suspend-cache-' . uniqid(),
        'name' => 'Suspend Cache',
        'status' => TenantStatus::Active,
    ]);

    $service = app(TenantService::class);
    $service->resolveBySlug($tenant->slug);
    expect(Cache::has("tenant:{$tenant->slug}"))->toBeTrue();

    $tenant->suspend();

    expect(Cache::has("tenant:{$tenant->slug}"))->toBeFalse();
});

it('invalidates cache when tenant is reactivated', function () {
    Cache::flush();

    $tenant = Tenant::on('pgsql_migrator')->create([
        'slug' => 'reactivate-cache-' . uniqid(),
        'name' => 'Reactivate Cache',
        'status' => TenantStatus::Suspended,
    ]);

    $service = app(TenantService::class);
    $service->resolveBySlug($tenant->slug);
    expect(Cache::has("tenant:{$tenant->slug}"))->toBeTrue();

    $tenant->reactivate();

    expect(Cache::has("tenant:{$tenant->slug}"))->toBeFalse();
});
