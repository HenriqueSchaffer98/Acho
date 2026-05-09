<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('currentTenant')) {
            // No tenant context (CLI, super admin, migrations).
            // RLS on the pgsql connection acts as the last line of defence.
            return;
        }

        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        $builder->where($model->getTable() . '.tenant_id', $tenant->id);
    }
}
