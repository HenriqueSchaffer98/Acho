<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Probe;
use Tests\Support\ProbeMigration;

uses(RefreshDatabase::class);

// beforeAll runs before the Laravel app is booted; ProbeMigration needs
// the DB connection so it must run in beforeEach (after app setup).
beforeEach(function () {
    ProbeMigration::up();
});

afterEach(function () {
    ProbeMigration::down();
});

it('scope prevents tenant B from seeing records of tenant A', function () {
    $tenantA = Tenant::create(['slug' => 'iso-a-' . uniqid(), 'name' => 'A', 'status' => TenantStatus::Active]);
    $tenantB = Tenant::create(['slug' => 'iso-b-' . uniqid(), 'name' => 'B', 'status' => TenantStatus::Active]);

    $this->actingAsTenant($tenantA);
    Probe::create(['label' => 'probe-from-a']);

    $this->actingAsTenant($tenantB);

    expect(Probe::all())->toHaveCount(0);
});

it('RLS blocks cross-tenant read even when scope is removed (pgsql role without BYPASSRLS)', function () {
    $tenantA = Tenant::create(['slug' => 'rls-a-' . uniqid(), 'name' => 'A', 'status' => TenantStatus::Active]);
    $tenantB = Tenant::create(['slug' => 'rls-b-' . uniqid(), 'name' => 'B', 'status' => TenantStatus::Active]);

    // Insert probe as tenant A via pgsql (acho_app): RLS WITH CHECK allows it
    // when app.tenant_id matches the probe's tenant_id.
    $this->actingAsTenant($tenantA);
    Probe::create(['label' => 'rls-probe']);

    // Switch pgsql (acho_app, no BYPASSRLS) context to tenant B
    $this->actingAsTenant($tenantB);

    // Even without the Eloquent scope, RLS on pgsql blocks the read
    $result = Probe::withoutTenantScope()
        ->withoutGlobalScope(SoftDeletingScope::class)
        ->get();

    expect($result)->toHaveCount(0);
});
