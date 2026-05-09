<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Wrap both connections in transactions so RefreshDatabase rolls back
     * pgsql_migrator changes too. Tests that need pgsql_migrator to see
     * their data must create records via Tenant::on('pgsql_migrator').
     */
    protected $connectionsToTransact = ['pgsql', 'pgsql_migrator'];

    /**
     * Set the current tenant context: binds app('currentTenant') and sets
     * app.tenant_id on the pgsql connection.
     */
    protected function actingAsTenant(Tenant $tenant): static
    {
        app()->instance('currentTenant', $tenant);
        DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenant->id]);

        return $this;
    }

    /**
     * Clear the tenant context (simulates CLI / super-admin scope).
     */
    protected function withoutTenantContext(): static
    {
        if (app()->bound('currentTenant')) {
            app()->forgetInstance('currentTenant');
        }

        DB::statement("SET app.tenant_id = ''");

        return $this;
    }
}
