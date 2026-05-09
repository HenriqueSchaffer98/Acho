<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\Tenant\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(TenantService::class);
});

it('returns null for resolveByCustomDomain (v2 stub)', function () {
    expect($this->service->resolveByCustomDomain('example.com'))->toBeNull();
});

it('returns null when slug does not exist', function () {
    Cache::flush();

    $result = $this->service->resolveBySlug('slug-inexistente-' . uniqid());

    expect($result)->toBeNull();
});

it('resolves an existing tenant by slug', function () {
    Cache::flush();

    $tenant = Tenant::on('pgsql_migrator')->create([
        'slug' => 'service-test-' . uniqid(),
        'name' => 'Service Test Tenant',
        'status' => TenantStatus::Active,
    ]);

    $result = $this->service->resolveBySlug($tenant->slug);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($tenant->id);
});

it('caches the tenant on second lookup', function () {
    Cache::flush();

    $tenant = Tenant::on('pgsql_migrator')->create([
        'slug' => 'cache-test-' . uniqid(),
        'name' => 'Cache Test Tenant',
        'status' => TenantStatus::Active,
    ]);

    $this->service->resolveBySlug($tenant->slug);

    expect(Cache::has("tenant:{$tenant->slug}"))->toBeTrue();
});

it('invalidates the cache for a tenant', function () {
    $tenant = Tenant::on('pgsql_migrator')->create([
        'slug' => 'invalidate-test-' . uniqid(),
        'name' => 'Invalidate Test',
        'status' => TenantStatus::Active,
    ]);

    $this->service->resolveBySlug($tenant->slug);
    expect(Cache::has("tenant:{$tenant->slug}"))->toBeTrue();

    $this->service->invalidateCache($tenant);
    expect(Cache::has("tenant:{$tenant->slug}"))->toBeFalse();
});
