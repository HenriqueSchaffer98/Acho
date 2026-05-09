<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('responds 200 with placeholder on root domain', function () {
    $response = $this->get('http://acho.test/');

    $response->assertStatus(200);
    $response->assertSee('Acho');
    $response->assertSee('Plataforma em construção');
});

it('redirects www to root domain with 301', function () {
    $response = $this->get('http://www.acho.test/');

    $response->assertStatus(301);
    $response->assertRedirect('http://acho.test');
});
