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

const REFRESH_URL = 'http://primo.acho.test/auth/refresh';

beforeEach(function () {
    $this->tenant = Tenant::create([
        'slug' => 'primo',
        'name' => 'Tenant primo',
        'status' => TenantStatus::Active,
    ]);

    $this->user = new User;
    $this->user->tenant_id = $this->tenant->id;
    $this->user->name = 'Admin';
    $this->user->email = 'admin@primo.test';
    $this->user->password = 'Senha@1234';
    $this->user->role = UserRole::Admin;
    $this->user->password_changed_at = now();
    $this->user->save();

    app()->instance('currentTenant', $this->tenant);

    $tokenService = app(TokenService::class);
    $this->familyId = $tokenService->newFamilyId();
    $this->refreshToken = $tokenService->generateRefreshToken(
        $this->user,
        $this->tenant,
        $this->familyId,
        '127.0.0.1',
        'PestTest/1.0',
    );
});

it('returns 401 when no refresh cookie is provided', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(REFRESH_URL)
        ->assertStatus(401);
});

it('returns 200 with a new access token and rotates the refresh cookie', function () {
    $response = $this->disableCookieEncryption()
        ->withCredentials()
        ->withoutMiddleware(TenantResolver::class)
        ->withCookies(['refresh_token' => $this->refreshToken])
        ->postJson(REFRESH_URL);

    $response->assertStatus(200)
        ->assertJsonStructure(['access_token']);

    $newCookie = collect($response->headers->getCookies())
        ->firstWhere(fn ($c) => $c->getName() === 'refresh_token');

    expect($newCookie)->not->toBeNull();
    expect($newCookie->getValue())->not->toBe($this->refreshToken);
});

it('marks the original refresh token as revoked after rotation', function () {
    $this->disableCookieEncryption()
        ->withCredentials()
        ->withoutMiddleware(TenantResolver::class)
        ->withCookies(['refresh_token' => $this->refreshToken])
        ->postJson(REFRESH_URL)
        ->assertStatus(200);

    $tokenService = app(TokenService::class);
    $original = RefreshToken::where('token_hash', $tokenService->hashToken($this->refreshToken))->first();

    expect($original->revoked)->toBeTrue();
    expect($original->revoked_at)->not->toBeNull();
});

it('detects replay and revokes the entire family on reuse', function () {
    // First refresh — succeeds and revokes the original token.
    $this->disableCookieEncryption()
        ->withCredentials()
        ->withoutMiddleware(TenantResolver::class)
        ->withCookies(['refresh_token' => $this->refreshToken])
        ->postJson(REFRESH_URL)
        ->assertStatus(200);

    // Second use of the original (now revoked) token: replay detected → 401.
    $this->disableCookieEncryption()
        ->withCredentials()
        ->withoutMiddleware(TenantResolver::class)
        ->withCookies(['refresh_token' => $this->refreshToken])
        ->postJson(REFRESH_URL)
        ->assertStatus(401);

    // Every token in this family must now be revoked.
    $activeInFamily = RefreshToken::where('family_id', $this->familyId)
        ->where('revoked', false)
        ->count();

    expect($activeInFamily)->toBe(0);
});

it('returns 401 for an unknown refresh token', function () {
    $this->disableCookieEncryption()
        ->withCredentials()
        ->withoutMiddleware(TenantResolver::class)
        ->withCookies(['refresh_token' => 'unknown-token-uuid-value-deadbeef'])
        ->postJson(REFRESH_URL)
        ->assertStatus(401);
});

it('returns 401 for an expired refresh token', function () {
    RefreshToken::where('token_hash', app(TokenService::class)->hashToken($this->refreshToken))
        ->update(['expires_at' => now()->subMinute()]);

    $this->disableCookieEncryption()
        ->withCredentials()
        ->withoutMiddleware(TenantResolver::class)
        ->withCookies(['refresh_token' => $this->refreshToken])
        ->postJson(REFRESH_URL)
        ->assertStatus(401);
});
