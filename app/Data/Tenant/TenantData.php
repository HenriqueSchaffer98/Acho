<?php

declare(strict_types=1);

namespace App\Data\Tenant;

use App\Enums\TenantStatus;
use Spatie\LaravelData\Data;

class TenantData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $customDomain,
        public readonly TenantStatus $status,
    ) {}
}
