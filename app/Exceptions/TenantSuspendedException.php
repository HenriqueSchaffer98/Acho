<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class TenantSuspendedException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("Tenant is suspended: {$slug}");
    }
}
