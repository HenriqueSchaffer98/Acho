<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

class AccountLockedException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct("Account locked. Retry in {$retryAfterSeconds}s.");
    }
}
