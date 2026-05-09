<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 with correct text for an unknown subdomain', function () {
    $response = $this->get('http://nao-existe-' . uniqid() . '.acho.test/');

    $response->assertStatus(404);
    $response->assertSee('Imobiliária não encontrada', false);
});

it('returns 403 with correct text for a suspended tenant', function () {
    Tenant::on('pgsql_migrator')->create([
        'slug' => 'suspended-tenant',
        'name' => 'Suspended',
        'status' => TenantStatus::Suspended,
    ]);

    $response = $this->get('http://suspended-tenant.acho.test/');

    $response->assertStatus(403);
    $response->assertSee('Esta imobiliária está temporariamente indisponível', false);
});
