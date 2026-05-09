<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 for reserved subdomain admin', function () {
    $response = $this->get('http://admin.acho.test/');

    $response->assertStatus(404);
});

it('returns 404 for reserved subdomain api', function () {
    $response = $this->get('http://api.acho.test/');

    $response->assertStatus(404);
});

it('returns 404 for subdomain with invalid characters', function () {
    $response = $this->get('http://tenant_invalido.acho.test/');

    $response->assertStatus(404);
});
