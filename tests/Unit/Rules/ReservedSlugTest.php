<?php

declare(strict_types=1);

use App\Rules\ReservedSlug;

it('rejects reserved slugs', function (string $slug) {
    $rule = new ReservedSlug;
    $failed = false;

    $rule->validate('slug', $slug, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
})->with(['admin', 'api', 'www', 'app', 'docs', 'support', 'login', 'register']);

it('rejects reserved slugs case-insensitively', function () {
    $rule = new ReservedSlug;
    $failed = false;

    $rule->validate('slug', 'ADMIN', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('accepts valid slugs', function (string $slug) {
    $rule = new ReservedSlug;
    $failed = false;

    $rule->validate('slug', $slug, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
})->with(['primoimoveis', 'casanova', 'imobiliaria-abc', 'imoveis2024']);
