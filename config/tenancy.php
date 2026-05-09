<?php

declare(strict_types=1);
use App\Models\Tenant;
use Stancl\Tenancy\Database\Managers\PostgreSQLDatabaseManager;

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The model used to represent a tenant. We use our own Tenant model
    | instead of the package's default to keep full control over the schema,
    | events, and casts (ADR-001, ADR-025).
    |
    */

    'tenant_model' => Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | Unique ID Column
    |--------------------------------------------------------------------------
    */

    'id_generator' => null,

    /*
    |--------------------------------------------------------------------------
    | Bootstrappers
    |--------------------------------------------------------------------------
    |
    | Disabled. We handle tenant context injection manually via SetTenantContext
    | middleware, which sets app.tenant_id on the Postgres connection.
    | This gives us full control and avoids side effects from the package's
    | bootstrappers (queue isolation, cache isolation, etc.).
    |
    */

    'bootstrappers' => [],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Single-database mode: all tenants share one database, isolated by
    | tenant_id + RLS policies (ADR-001).
    |
    */

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'pgsql'),

        // No per-tenant connections in single-database mode.
        'template_tenant_connection' => null,
        'tenant_host' => null,
        'tenant_port' => null,
        'tenant_database_name' => null,
        'tenant_prefix' => null,
        'tenant_suffix' => null,

        'managers' => [
            'pgsql' => PostgreSQLDatabaseManager::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Disabled. Tenant cache resolution is handled by TenantService
    | using Laravel's Cache facade directly (key: tenant:{slug}, TTL: 60s).
    |
    */

    'cache' => [
        'tag_base' => 'tenant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem
    |--------------------------------------------------------------------------
    */

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis
    |--------------------------------------------------------------------------
    |
    | Disabled. We do not isolate Redis per tenant in this project.
    |
    */

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | All package features disabled. Multi-tenancy is implemented via
    | our own middleware + RLS + Eloquent scope stack (ADR-001, ADR-025).
    |
    */

    'features' => [],

    /*
    |--------------------------------------------------------------------------
    | Migration Parameters
    |--------------------------------------------------------------------------
    */

    'migration_parameters' => [
        '--force' => true,
        '--path' => [],
        '--realpath' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder Parameters
    |--------------------------------------------------------------------------
    */

    'seeder_parameters' => [
        '--force' => true,
    ],

];
