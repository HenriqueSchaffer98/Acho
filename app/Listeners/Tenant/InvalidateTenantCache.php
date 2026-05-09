<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantReactivated;
use App\Events\Tenant\TenantSuspended;
use App\Events\Tenant\TenantUpdated;
use App\Services\Tenant\TenantService;
use Illuminate\Events\Dispatcher;

class InvalidateTenantCache
{
    public function __construct(private readonly TenantService $tenantService) {}

    public function handle(TenantUpdated|TenantSuspended|TenantReactivated $event): void
    {
        $this->tenantService->invalidateCache($event->tenant);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(TenantUpdated::class, [self::class, 'handle']);
        $events->listen(TenantSuspended::class, [self::class, 'handle']);
        $events->listen(TenantReactivated::class, [self::class, 'handle']);
    }
}
