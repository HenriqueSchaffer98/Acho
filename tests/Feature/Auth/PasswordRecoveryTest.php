<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Events\Auth\PasswordReset;
use App\Http\Middleware\TenantResolver;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Auth\PasswordResetNotification;
use App\Services\Auth\TokenService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

const FORGOT_URL = 'http://primo.acho.test/auth/forgot-password';
const RESET_URL = 'http://primo.acho.test/auth/reset-password';
const CURRENT_PWD = 'Senha@1234';
const NEW_PWD = 'NovaSenha@5678';

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
    $this->user->password = CURRENT_PWD;
    $this->user->role = UserRole::Admin;
    $this->user->password_changed_at = now();
    $this->user->save();

    app()->instance('currentTenant', $this->tenant);
});

it('forgot password queues PasswordResetNotification for an existing e-mail', function () {
    Notification::fake();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(FORGOT_URL, ['email' => 'admin@primo.test'])
        ->assertStatus(200)
        ->assertJson(['message' => 'Se o e-mail estiver cadastrado, você receberá as instruções para redefinir sua senha.']);

    Notification::assertSentTo($this->user, PasswordResetNotification::class);
});

it('forgot password returns same message and sends NO notification for unknown e-mail (no enumeration)', function () {
    Notification::fake();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(FORGOT_URL, ['email' => 'naoexiste@primo.test'])
        ->assertStatus(200)
        ->assertJson(['message' => 'Se o e-mail estiver cadastrado, você receberá as instruções para redefinir sua senha.']);

    Notification::assertNothingSent();
});

it('forgot password validates that e-mail is required and valid', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(FORGOT_URL, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(FORGOT_URL, ['email' => 'not-an-email'])
        ->assertStatus(422);
});

it('reset password updates the password, revokes tokens, and returns a new session', function () {
    Event::fake([PasswordReset::class]);

    $token = app(TokenService::class)->generatePurposeToken($this->user, $this->tenant, 'password_reset', 3600);

    // Create one active refresh token that should be revoked after reset.
    RefreshToken::create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'family_id' => app(TokenService::class)->newFamilyId(),
        'token_hash' => 'pre-existing-hash',
        'expires_at' => now()->addDay(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'old',
    ]);

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->postJson(RESET_URL, [
            'token' => $token,
            'password' => NEW_PWD,
            'password_confirmation' => NEW_PWD,
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['access_token', 'user' => ['id', 'name', 'email', 'role']]);

    $this->user->refresh();
    expect(Hash::check(NEW_PWD, $this->user->password))->toBeTrue();
    expect(Hash::check(CURRENT_PWD, $this->user->password))->toBeFalse();

    // Pre-existing token was revoked; the new refresh issued by reset is active.
    expect(RefreshToken::where('token_hash', 'pre-existing-hash')->value('revoked'))->toBeTrue();
    expect(RefreshToken::where('revoked', false)->count())->toBe(1);

    Event::assertDispatched(PasswordReset::class);
});

it('reset password rejects an invalid JWT', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(RESET_URL, [
            'token' => 'not-a-jwt',
            'password' => NEW_PWD,
            'password_confirmation' => NEW_PWD,
        ])
        ->assertStatus(422)
        ->assertJson(['message' => 'Token de redefinição inválido ou expirado.']);
});

it('reset password rejects a JWT with the wrong purpose claim', function () {
    // Generate an access-purpose token instead of password_reset.
    $accessToken = app(TokenService::class)->generateAccessToken($this->user, $this->tenant);

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(RESET_URL, [
            'token' => $accessToken,
            'password' => NEW_PWD,
            'password_confirmation' => NEW_PWD,
        ])
        ->assertStatus(422);
});

it('reset password rejects an expired JWT', function () {
    // Sign a password_reset JWT that already expired.
    $payload = [
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'role' => $this->user->role->value,
        'purpose' => 'password_reset',
        'iat' => time() - 7200,
        'exp' => time() - 60,
    ];
    $expiredToken = JWT::encode($payload, base64_decode(substr((string) config('app.key'), 7)), 'HS256');

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(RESET_URL, [
            'token' => $expiredToken,
            'password' => NEW_PWD,
            'password_confirmation' => NEW_PWD,
        ])
        ->assertStatus(422);
});

it('reset password rejects a weak password', function () {
    $token = app(TokenService::class)->generatePurposeToken($this->user, $this->tenant, 'password_reset', 3600);

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(RESET_URL, [
            'token' => $token,
            'password' => '123',
            'password_confirmation' => '123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('reset password rejects when password confirmation does not match', function () {
    $token = app(TokenService::class)->generatePurposeToken($this->user, $this->tenant, 'password_reset', 3600);

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(RESET_URL, [
            'token' => $token,
            'password' => NEW_PWD,
            'password_confirmation' => 'OutraSenha@9999',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
