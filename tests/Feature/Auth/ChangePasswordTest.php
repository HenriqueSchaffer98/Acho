<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Events\Auth\PasswordChanged;
use App\Http\Middleware\TenantResolver;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

const CHANGE_PWD_URL = 'http://primo.acho.test/auth/change-password';
const CHANGE_OLD_PWD = 'Senha@1234';
const CHANGE_NEW_PWD = 'NovaSenha@5678';

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
    $this->user->password = CHANGE_OLD_PWD;
    $this->user->role = UserRole::Admin;
    $this->user->password_changed_at = now();
    $this->user->save();

    app()->instance('currentTenant', $this->tenant);

    $tokenService = app(TokenService::class);
    $this->accessToken = $tokenService->generateAccessToken($this->user, $this->tenant);
    $this->familyId = $tokenService->newFamilyId();
    $this->currentRefresh = $tokenService->generateRefreshToken(
        $this->user,
        $this->tenant,
        $this->familyId,
        '127.0.0.1',
        'PestTest/1.0',
    );

    // A second refresh token from a "different device" — should be revoked
    // when the user changes the password.
    $this->otherFamilyId = $tokenService->newFamilyId();
    $this->otherRefresh = $tokenService->generateRefreshToken(
        $this->user,
        $this->tenant,
        $this->otherFamilyId,
        '203.0.113.10',
        'OtherDevice/1.0',
    );
});

it('returns 401 when the request is not authenticated', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(CHANGE_PWD_URL, [
            'current_password' => CHANGE_OLD_PWD,
            'password' => CHANGE_NEW_PWD,
            'password_confirmation' => CHANGE_NEW_PWD,
        ])
        ->assertStatus(401);
});

it('changes the password and preserves the current refresh token', function () {
    Event::fake([PasswordChanged::class]);

    $response = $this->disableCookieEncryption()
        ->withCredentials()
        ->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$this->accessToken}"])
        ->withCookies(['refresh_token' => $this->currentRefresh])
        ->postJson(CHANGE_PWD_URL, [
            'current_password' => CHANGE_OLD_PWD,
            'password' => CHANGE_NEW_PWD,
            'password_confirmation' => CHANGE_NEW_PWD,
        ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Senha alterada com sucesso.']);

    $this->user->refresh();
    expect(Hash::check(CHANGE_NEW_PWD, $this->user->password))->toBeTrue();
    expect(Hash::check(CHANGE_OLD_PWD, $this->user->password))->toBeFalse();

    // Current refresh (from cookie) preserved; the other device revoked.
    $tokenService = app(TokenService::class);
    expect(RefreshToken::where('token_hash', $tokenService->hashToken($this->currentRefresh))->value('revoked'))->toBeFalse();
    expect(RefreshToken::where('token_hash', $tokenService->hashToken($this->otherRefresh))->value('revoked'))->toBeTrue();

    Event::assertDispatched(PasswordChanged::class);
});

it('revokes ALL refresh tokens when no current cookie is sent', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$this->accessToken}"])
        ->postJson(CHANGE_PWD_URL, [
            'current_password' => CHANGE_OLD_PWD,
            'password' => CHANGE_NEW_PWD,
            'password_confirmation' => CHANGE_NEW_PWD,
        ])
        ->assertStatus(200);

    expect(RefreshToken::where('revoked', false)->count())->toBe(0);
});

it('returns 422 when the current password is wrong', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$this->accessToken}"])
        ->postJson(CHANGE_PWD_URL, [
            'current_password' => 'wrong-current',
            'password' => CHANGE_NEW_PWD,
            'password_confirmation' => CHANGE_NEW_PWD,
        ])
        ->assertStatus(422)
        ->assertJson(['message' => 'Senha atual incorreta.']);

    $this->user->refresh();
    expect(Hash::check(CHANGE_OLD_PWD, $this->user->password))->toBeTrue();
});

it('rejects a weak new password', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$this->accessToken}"])
        ->postJson(CHANGE_PWD_URL, [
            'current_password' => CHANGE_OLD_PWD,
            'password' => '123',
            'password_confirmation' => '123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects when new password equals current password', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$this->accessToken}"])
        ->postJson(CHANGE_PWD_URL, [
            'current_password' => CHANGE_OLD_PWD,
            'password' => CHANGE_OLD_PWD,
            'password_confirmation' => CHANGE_OLD_PWD,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects when password confirmation does not match', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->withHeaders(['Authorization' => "Bearer {$this->accessToken}"])
        ->postJson(CHANGE_PWD_URL, [
            'current_password' => CHANGE_OLD_PWD,
            'password' => CHANGE_NEW_PWD,
            'password_confirmation' => 'OutraSenha@9999',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
