<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Auth\PasswordChanged;
use App\Events\Auth\PasswordReset;
use App\Events\Auth\UserLoggedIn;
use App\Events\Tenant\TenantCreated;
use App\Listeners\Auth\SendNewIpLoginNotification;
use App\Listeners\Auth\SendPasswordChangedNotification;
use App\Listeners\Tenant\InvalidateTenantCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected $listen = [
        TenantCreated::class => [],
        UserLoggedIn::class => [
            SendNewIpLoginNotification::class,
        ],
        PasswordReset::class => [
            SendPasswordChangedNotification::class,
        ],
        PasswordChanged::class => [
            SendPasswordChangedNotification::class,
        ],
    ];

    /** @var list<class-string> */
    protected $subscribe = [
        InvalidateTenantCache::class,
    ];
}
