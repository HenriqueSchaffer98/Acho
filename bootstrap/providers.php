<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\TenantServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    TenantServiceProvider::class,
];
