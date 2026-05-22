<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Http\Middleware\TenantResolver;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const LOGOUT_URL = 'http://primo.acho.test/auth/logout';

/** @return array{Tenant, User, string, string} */
function bootAuthenticatedSession(): array
{
    $tenant = Tenant::create([
        'slug' => 'primo',
        'name' => 'Tenant primo',
        'status' => TenantStatus::Active,
    ]);

    $user = new User;
    $user->tenant_id = $tenant->id;
    $user->name = 'Admin';
    $user->email = 'admin@primo.test';
    $user->password = 'Senha@1234';
    $user->role = UserRole::Admin;
    $user->password_changed_at = now();
    $user->save();

    app()->instance('currentTenant', $tenant);

    $tokenService = app(TokenService::class);
    $accessToken = $tokenService->generateAccessToken($user, $tenant);
    $familyId = $tokenService->newFamilyId();
    $refreshToken = $tokenService->generateRefreshToken($user, $tenant, $familyId, '127.0.0.1', 'PestTest/1.0');

    return [$tenant, $user, $accessToken, $refreshToken];
}

it('returns 401 when no bearer token is provided', function () {
    bootAuthenticatedSession();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGOUT_URL)
        ->assertStatus(401);
});

it('returns 204 and revokes the refresh token from cookie', function () {
    [, , $accessToken, $refreshToken] = bootAuthenticatedSession();

    expect(RefreshToken::where('revoked', false)->count())->toBe(1);

    $this->disableCookieEncryption()
        ->withCredentials()
        ->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
        ->withCookies(['refresh_token' => $refreshToken])
        ->postJson(LOGOUT_URL)
        ->assertStatus(204);

    expect(RefreshToken::where('revoked', false)->count())->toBe(0);
    expect(RefreshToken::where('revoked', true)->whereNotNull('revoked_at')->count())->toBe(1);
});

it('is idempotent when called with no refresh cookie', function () {
    [, , $accessToken] = bootAuthenticatedSession();

    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
        ->postJson(LOGOUT_URL)
        ->assertStatus(204);
});

it('clears the refresh_token cookie via Set-Cookie', function () {
    [, , $accessToken, $refreshToken] = bootAuthenticatedSession();

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
        ->withCookies(['refresh_token' => $refreshToken])
        ->postJson(LOGOUT_URL);

    $cookie = collect($response->headers->getCookies())
        ->firstWhere(fn ($c) => $c->getName() === 'refresh_token');

    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->toBe('');
});
