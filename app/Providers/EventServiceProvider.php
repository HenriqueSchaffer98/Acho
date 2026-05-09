<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Tenant\TenantCreated;
use App\Listeners\Tenant\InvalidateTenantCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected $listen = [
        TenantCreated::class => [],
    ];

    /** @var list<class-string> */
    protected $subscribe = [
        InvalidateTenantCache::class,
    ];
}
