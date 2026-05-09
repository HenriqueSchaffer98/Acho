<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class TenantNotFoundException extends RuntimeException
{
    public function __construct(string $identifier)
    {
        parent::__construct("Tenant not found: {$identifier}");
    }
}
