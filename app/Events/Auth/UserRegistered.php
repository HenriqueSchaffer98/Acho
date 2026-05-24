<?php

declare(strict_types=1);

namespace App\Events\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly string $ipAddress,
    ) {}
}
