<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Events\Auth\UserRegistered;
use App\Http\Middleware\TenantResolver;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

const REGISTER_URL = 'http://primo.acho.test/auth/register';
const REGISTER_PWD = 'Senha@Forte#9876';

beforeEach(function () {
    $this->tenant = Tenant::create([
        'slug' => 'primo',
        'name' => 'Tenant primo',
        'status' => TenantStatus::Active,
    ]);

    app()->instance('currentTenant', $this->tenant);
});

it('registers a cliente, returns 201 with tokens and sets refresh cookie', function () {
    Event::fake([UserRegistered::class]);

    $response = $this->withoutMiddleware(TenantResolver::class)
        ->postJson(REGISTER_URL, [
            'name' => 'Maria Cliente',
            'email' => 'maria@cliente.test',
            'password' => REGISTER_PWD,
            'password_confirmation' => REGISTER_PWD,
            'terms' => true,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['access_token', 'user' => ['id', 'name', 'email', 'role']])
        ->assertJsonPath('user.role', 'cliente')
        ->assertCookie('refresh_token');

    $user = User::where('email', 'maria@cliente.test')->first();
    expect($user)->not->toBeNull();
    expect($user->tenant_id)->toBe($this->tenant->id);
    expect($user->role)->toBe(UserRole::Cliente);
    expect(Hash::check(REGISTER_PWD, $user->password))->toBeTrue();

    Event::assertDispatched(UserRegistered::class);
});

it('rejects duplicate e-mail within the same tenant', function () {
    $existing = new User;
    $existing->tenant_id = $this->tenant->id;
    $existing->name = 'Existente';
    $existing->email = 'maria@cliente.test';
    $existing->password = REGISTER_PWD;
    $existing->role = UserRole::Cliente;
    $existing->password_changed_at = now();
    $existing->save();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(REGISTER_URL, [
            'name' => 'Maria',
            'email' => 'maria@cliente.test',
            'password' => REGISTER_PWD,
            'password_confirmation' => REGISTER_PWD,
            'terms' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('allows the same e-mail in a different tenant', function () {
    $otherTenant = Tenant::create([
        'slug' => 'outro',
        'name' => 'Outro Tenant',
        'status' => TenantStatus::Active,
    ]);

    $userOnOther = new User;
    $userOnOther->tenant_id = $otherTenant->id;
    $userOnOther->name = 'Outro';
    $userOnOther->email = 'maria@cliente.test';
    $userOnOther->password = REGISTER_PWD;
    $userOnOther->role = UserRole::Cliente;
    $userOnOther->password_changed_at = now();
    $userOnOther->save();

    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(REGISTER_URL, [
            'name' => 'Maria',
            'email' => 'maria@cliente.test',
            'password' => REGISTER_PWD,
            'password_confirmation' => REGISTER_PWD,
            'terms' => true,
        ])
        ->assertStatus(201);
});

it('rejects when terms are not accepted', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(REGISTER_URL, [
            'name' => 'Maria',
            'email' => 'maria@cliente.test',
            'password' => REGISTER_PWD,
            'password_confirmation' => REGISTER_PWD,
            'terms' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['terms']);
});

it('rejects weak password', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(REGISTER_URL, [
            'name' => 'Maria',
            'email' => 'maria@cliente.test',
            'password' => '123',
            'password_confirmation' => '123',
            'terms' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects mismatched password confirmation', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(REGISTER_URL, [
            'name' => 'Maria',
            'email' => 'maria@cliente.test',
            'password' => REGISTER_PWD,
            'password_confirmation' => 'OutraSenha@9999',
            'terms' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('validates required fields', function () {
    $this->withoutMiddleware(TenantResolver::class)
        ->postJson(REGISTER_URL, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'terms']);
});
