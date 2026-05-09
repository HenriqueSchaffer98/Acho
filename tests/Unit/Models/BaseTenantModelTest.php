<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('auto-fills tenant_id on creating', function () {
    $tenant = Tenant::create([
        'slug' => 'model-test-' . uniqid(),
        'name' => 'Model Test',
        'status' => TenantStatus::Active,
    ]);

    app()->instance('currentTenant', $tenant);
    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenant->id]);

    $probe = Probe::create(['label' => 'auto-fill test']);

    expect($probe->tenant_id)->toBe($tenant->id);
});

it('scope filters records to the current tenant', function () {
    $tenantA = Tenant::create(['slug' => 'scope-a-' . uniqid(), 'name' => 'A', 'status' => TenantStatus::Active]);
    $tenantB = Tenant::create(['slug' => 'scope-b-' . uniqid(), 'name' => 'B', 'status' => TenantStatus::Active]);

    app()->instance('currentTenant', $tenantA);
    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantA->id]);
    Probe::create(['label' => 'probe-a']);

    app()->instance('currentTenant', $tenantB);
    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantB->id]);

    $results = Probe::all();

    expect($results)->toHaveCount(0);
});

it('withoutTenantScope returns builder without global scope', function () {
    $builder = Probe::withoutTenantScope();

    expect($builder)->toBeInstanceOf(Builder::class);

    $scopes = $builder->getQuery()->wheres ?? [];
    $hasTenantWhere = collect($scopes)->contains(fn ($w) => ($w['column'] ?? '') === 'tenant_isolation_probes.tenant_id');

    expect($hasTenantWhere)->toBeFalse();
});
