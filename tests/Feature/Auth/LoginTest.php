<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Events\Auth\UserLoggedIn;
use App\Http\Middleware\TenantResolver;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

const LOGIN_URL = 'http://primo.acho.test/auth/login';
const ADMIN_EMAIL = 'admin@primo.test';
const ADMIN_PASSWORD = 'Senha@1234';

function createTenantWithUser(string $slug = 'primo', string $email = ADMIN_EMAIL, string $password = ADMIN_PASSWORD): array
{
    $tenant = Tenant::create([
        'slug' => $slug,
        'name' => 'Tenant ' . $slug,
        'status' => TenantStatus::Active,
    ]);

    $user = new User;
    $user->tenant_id = $tenant->id;
    $user->name = 'Admin';
    $user->email = $email;
    $user->password = $password;
    $user->role = UserRole::Admin;
    $user->password_changed_at = now();
    $user->save();

    // Bind tenant so requests can skip TenantResolver and still find it.
    app()->instance('currentTenant', $tenant);

    return [$tenant, $user];
}

it('returns access_token, user payload, and refresh cookie on valid credentials', function () {
    [, $user] = createTenantWithUser();

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGIN_URL, [
            'email' => ADMIN_EMAIL,
            'password' => ADMIN_PASSWORD,
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'user' => ['id', 'name', 'email', 'role'],
        ])
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', 'admin');

    $response->assertCookie('refresh_token');
});

it('returns 401 with generic message for wrong password', function () {
    createTenantWithUser();

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGIN_URL, [
            'email' => ADMIN_EMAIL,
            'password' => 'wrong-password',
        ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'E-mail ou senha inválidos.']);
});

it('returns 401 with generic message for unknown e-mail (no enumeration)', function () {
    createTenantWithUser();

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGIN_URL, [
            'email' => 'naoexiste@primo.test',
            'password' => ADMIN_PASSWORD,
        ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'E-mail ou senha inválidos.']);
});

it('does not authenticate a user from another tenant', function () {
    createTenantWithUser('tenant-a', 'admin@a.test');
    [$tenantB] = createTenantWithUser('tenant-b', 'admin@b.test');

    // Acting on tenant B — admin@a.test must not be authenticated here.
    app()->instance('currentTenant', $tenantB);

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->postJson('http://tenant-b.acho.test/auth/login', [
            'email' => 'admin@a.test',
            'password' => ADMIN_PASSWORD,
        ]);

    $response->assertStatus(401);
});

it('updates last_login_ip and last_login_at on successful login', function () {
    [, $user] = createTenantWithUser();

    expect($user->last_login_at)->toBeNull();
    expect($user->last_login_ip)->toBeNull();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGIN_URL, [
            'email' => ADMIN_EMAIL,
            'password' => ADMIN_PASSWORD,
        ])->assertStatus(200);

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();
    expect($user->last_login_ip)->not->toBeNull();
});

it('dispatches UserLoggedIn event with newIp false on first login', function () {
    Event::fake([UserLoggedIn::class]);
    createTenantWithUser();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGIN_URL, [
            'email' => ADMIN_EMAIL,
            'password' => ADMIN_PASSWORD,
        ])->assertStatus(200);

    Event::assertDispatched(UserLoggedIn::class, fn (UserLoggedIn $event) => $event->newIp === false);
});

it('dispatches UserLoggedIn event with newIp true when ip changed', function () {
    Event::fake([UserLoggedIn::class]);
    [, $user] = createTenantWithUser();

    $user->forceFill(['last_login_ip' => '203.0.113.10'])->saveQuietly();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGIN_URL, [
            'email' => ADMIN_EMAIL,
            'password' => ADMIN_PASSWORD,
        ])->assertStatus(200);

    Event::assertDispatched(UserLoggedIn::class, fn (UserLoggedIn $event) => $event->newIp === true);
});

it('returns 429 after exceeding the rate limit of 5 attempts in 15 min', function () {
    createTenantWithUser();

    foreach (range(1, 5) as $_) {
        $this->withoutMiddleware(TenantResolver::class)
            ->postJson(LOGIN_URL, [
                'email' => ADMIN_EMAIL,
                'password' => 'wrong',
            ])->assertStatus(401);
    }

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGIN_URL, [
            'email' => ADMIN_EMAIL,
            'password' => 'wrong',
        ]);

    $response->assertStatus(429);
});

it('validates that email and password are required', function () {
    createTenantWithUser();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(LOGIN_URL, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
