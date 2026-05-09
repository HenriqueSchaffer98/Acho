<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves a tenant and responds 200 for an active subdomain', function () {
    Tenant::on('pgsql_migrator')->create([
        'slug' => 'resolve-test',
        'name' => 'Resolve Test',
        'status' => TenantStatus::Active,
    ]);

    $response = $this->get('http://resolve-test.acho.test/');

    $response->assertStatus(200);
    $response->assertSee('Tenant resolve-test resolvido');
});
