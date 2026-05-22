<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Jobs\Auth\CleanupExpiredTokens;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
});

it('deletes refresh tokens whose expires_at is in the past', function () {
    $tokenService = app(TokenService::class);
    $familyId = $tokenService->newFamilyId();

    // Active token
    $active = RefreshToken::create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'family_id' => $familyId,
        'token_hash' => 'active-hash',
        'expires_at' => now()->addDays(7),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
    ]);

    // Expired token
    $expired = RefreshToken::create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'family_id' => $familyId,
        'token_hash' => 'expired-hash',
        'expires_at' => now()->subDay(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
    ]);

    expect(RefreshToken::count())->toBe(2);

    (new CleanupExpiredTokens)->handle();

    expect(RefreshToken::find($active->id))->not->toBeNull();
    expect(RefreshToken::find($expired->id))->toBeNull();
});

it('leaves the table untouched when no tokens are expired', function () {
    $tokenService = app(TokenService::class);
    $familyId = $tokenService->newFamilyId();

    RefreshToken::create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'family_id' => $familyId,
        'token_hash' => 'hash-1',
        'expires_at' => now()->addDays(7),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
    ]);

    (new CleanupExpiredTokens)->handle();

    expect(RefreshToken::count())->toBe(1);
});

it('is registered to run daily on the scheduler', function () {
    $schedule = app(Schedule::class);

    $hasJob = collect($schedule->events())
        ->contains(fn ($event) => str_contains($event->description ?? '', 'CleanupExpiredTokens')
            || str_contains((string) $event->getSummaryForDisplay(), 'CleanupExpiredTokens'));

    expect($hasJob)->toBeTrue();
});
