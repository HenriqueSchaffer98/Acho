<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Spatie\LaravelData\Data;

class AcceptInviteData extends Data
{
    public function __construct(
        public readonly string $token,
        public readonly string $name,
        public readonly string $password,
    ) {}
}
