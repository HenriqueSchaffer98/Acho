<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::firstOrCreate(
            ['slug' => 'teste-interno'],
            [
                'name' => 'Tenant de Teste Interno',
                'status' => TenantStatus::Active,
            ]
        );
    }
}
