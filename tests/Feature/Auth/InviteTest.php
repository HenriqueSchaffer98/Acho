<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Http\Middleware\TenantResolver;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Auth\CorretorInviteNotification;
use App\Services\Auth\TokenService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

const INVITE_URL = 'http://primo.acho.test/admin/corretores/convite';
const ACCEPT_INVITE_URL = 'http://primo.acho.test/auth/convite/aceitar';
const INVITE_PWD = 'SenhaCorretor@5678';

function makeAdminWithToken(Tenant $tenant): array
{
    $admin = new User;
    $admin->tenant_id = $tenant->id;
    $admin->name = 'Admin';
    $admin->email = 'admin@primo.test';
    $admin->password = 'Senha@1234';
    $admin->role = UserRole::Admin;
    $admin->password_changed_at = now();
    $admin->save();

    $accessToken = app(TokenService::class)->generateAccessToken($admin, $tenant);

    return [$admin, $accessToken];
}

beforeEach(function () {
    $this->tenant = Tenant::create([
        'slug' => 'primo',
        'name' => 'Primo Imóveis',
        'status' => TenantStatus::Active,
    ]);

    app()->instance('currentTenant', $this->tenant);
});

it('admin sends a corretor invite and the notification is queued', function () {
    Notification::fake();
    [, $accessToken] = makeAdminWithToken($this->tenant);

    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
        ->postJson(INVITE_URL, ['email' => 'novo@corretor.test'])
        ->assertStatus(201)
        ->assertJson(['message' => 'Convite enviado com sucesso.']);

    Notification::assertSentOnDemand(CorretorInviteNotification::class, function ($notification, array $channels, AnonymousNotifiable $notifiable) {
        return $notifiable->routes['mail'] === 'novo@corretor.test'
            && $notification->tenant->id === $this->tenant->id;
    });
});

it('rejects invite when the e-mail already exists in the tenant', function () {
    [, $accessToken] = makeAdminWithToken($this->tenant);

    $existing = new User;
    $existing->tenant_id = $this->tenant->id;
    $existing->name = 'Existente';
    $existing->email = 'novo@corretor.test';
    $existing->password = INVITE_PWD;
    $existing->role = UserRole::Cliente;
    $existing->password_changed_at = now();
    $existing->save();

    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
        ->postJson(INVITE_URL, ['email' => 'novo@corretor.test'])
        ->assertStatus(422);
});

it('returns 403 when non-admin tries to send an invite', function () {
    $cliente = new User;
    $cliente->tenant_id = $this->tenant->id;
    $cliente->name = 'Cliente';
    $cliente->email = 'cliente@primo.test';
    $cliente->password = INVITE_PWD;
    $cliente->role = UserRole::Cliente;
    $cliente->password_changed_at = now();
    $cliente->save();

    $accessToken = app(TokenService::class)->generateAccessToken($cliente, $this->tenant);

    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$accessToken}"])
        ->postJson(INVITE_URL, ['email' => 'novo@corretor.test'])
        ->assertStatus(403);
});

it('returns 401 when invite is sent without authentication', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(INVITE_URL, ['email' => 'novo@corretor.test'])
        ->assertStatus(401);
});

it('accepts an invite and creates a corretor with a fresh session', function () {
    $token = app(TokenService::class)->generateAnonymousToken([
        'email' => 'novo@corretor.test',
        'role' => UserRole::Corretor->value,
        'tenant_id' => $this->tenant->id,
        'purpose' => 'invite',
    ], 3600);

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->postJson(ACCEPT_INVITE_URL, [
            'token' => $token,
            'name' => 'Novo Corretor',
            'password' => INVITE_PWD,
            'password_confirmation' => INVITE_PWD,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['access_token', 'user' => ['id', 'name', 'email', 'role']])
        ->assertJsonPath('user.role', 'corretor')
        ->assertCookie('refresh_token');

    $user = User::where('email', 'novo@corretor.test')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe(UserRole::Corretor);
    expect($user->tenant_id)->toBe($this->tenant->id);
    expect(Hash::check(INVITE_PWD, $user->password))->toBeTrue();
});

it('returns 410 for an expired invite', function () {
    $payload = [
        'email' => 'novo@corretor.test',
        'role' => UserRole::Corretor->value,
        'tenant_id' => $this->tenant->id,
        'purpose' => 'invite',
        'iat' => time() - 7200,
        'exp' => time() - 60,
    ];
    $expiredToken = JWT::encode($payload, base64_decode(substr((string) config('app.key'), 7)), 'HS256');

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(ACCEPT_INVITE_URL, [
            'token' => $expiredToken,
            'name' => 'Novo Corretor',
            'password' => INVITE_PWD,
            'password_confirmation' => INVITE_PWD,
        ])
        ->assertStatus(410);
});

it('returns 410 for an invite with wrong purpose', function () {
    $admin = new User;
    $admin->tenant_id = $this->tenant->id;
    $admin->name = 'Admin';
    $admin->email = 'admin@primo.test';
    $admin->password = INVITE_PWD;
    $admin->role = UserRole::Admin;
    $admin->password_changed_at = now();
    $admin->save();

    // Access token has purpose=access, not invite
    $wrongPurposeToken = app(TokenService::class)->generateAccessToken($admin, $this->tenant);

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(ACCEPT_INVITE_URL, [
            'token' => $wrongPurposeToken,
            'name' => 'Novo Corretor',
            'password' => INVITE_PWD,
            'password_confirmation' => INVITE_PWD,
        ])
        ->assertStatus(410);
});

it('returns 410 when accepting an invite twice (e-mail already taken)', function () {
    $token = app(TokenService::class)->generateAnonymousToken([
        'email' => 'novo@corretor.test',
        'role' => UserRole::Corretor->value,
        'tenant_id' => $this->tenant->id,
        'purpose' => 'invite',
    ], 3600);

    $payload = [
        'token' => $token,
        'name' => 'Novo Corretor',
        'password' => INVITE_PWD,
        'password_confirmation' => INVITE_PWD,
    ];

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(ACCEPT_INVITE_URL, $payload)
        ->assertStatus(201);

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(ACCEPT_INVITE_URL, $payload)
        ->assertStatus(410);
});

it('validates required fields on accept', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(ACCEPT_INVITE_URL, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token', 'name', 'password']);
});
