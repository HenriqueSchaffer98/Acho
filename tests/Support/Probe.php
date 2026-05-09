<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\BaseTenantModel;

/**
 * Dummy Eloquent model used exclusively in tests to verify that
 * BaseTenantModel's automatic scope and RLS policy work correctly.
 */
class Probe extends BaseTenantModel
{
    protected $table = 'tenant_isolation_probes';

    protected $fillable = ['label'];
}
