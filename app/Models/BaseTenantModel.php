<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $tenant_id
 */
abstract class BaseTenantModel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (BaseTenantModel $model): void {
            if (empty($model->tenant_id) && app()->bound('currentTenant')) {
                /** @var Tenant $tenant */
                $tenant = app('currentTenant');
                $model->tenant_id = $tenant->id;
            }
        });
    }

    /**
     * Return a query builder with the tenant scope removed.
     * RLS on the pgsql connection still applies unless the connection
     * uses the acho_migrator role (BYPASSRLS).
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
