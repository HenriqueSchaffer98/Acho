<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'teste-interno')->first();

        if ($tenant === null) {
            throw new RuntimeException('TenantSeeder must run before UserSeeder.');
        }

        User::firstOrCreate(
            ['email' => 'admin@teste.test', 'tenant_id' => $tenant->id],
            [
                'name' => 'Admin Teste',
                'password' => 'Senha@1234',
                'role' => UserRole::Admin,
                'password_changed_at' => now(),
            ]
        );
    }
}
