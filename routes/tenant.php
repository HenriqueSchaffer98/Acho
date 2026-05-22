<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AcceptInviteController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RefreshController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Tenant routes — {slug}.acho.test
// TenantResolver resolves and binds the tenant; SetTenantContext injects
// app.tenant_id into the Postgres connection (ADR-001, ADR-016).
Route::domain('{slug}.' . config('app.base_domain', 'acho.test'))
    ->middleware(['tenant.resolve', 'tenant.context'])
    ->group(function () {
        // Placeholder until the public storefront (ADR-006) is implemented.
        Route::get('/', function (string $slug) {
            return response("Tenant {$slug} resolvido", 200);
        });

        // Auth pages (Inertia / React) — ADR-009 + ADR-014.
        Route::get('/auth/login', fn () => Inertia::render('Auth/Login'))->name('auth.login.page');
        Route::get('/auth/cadastro', fn () => Inertia::render('Auth/Register'))->name('auth.register.page');
        Route::get('/auth/esqueci-senha', fn () => Inertia::render('Auth/ForgotPassword'))->name('auth.forgot-password.page');
        Route::get('/auth/reset-password', fn () => Inertia::render('Auth/ResetPassword'))->name('auth.reset-password.page');
        Route::get('/auth/convite/aceitar', fn () => Inertia::render('Auth/AcceptInvite'))->name('auth.invite.accept.page');

        // Auth API (JSON) — ADR-009 + ADR-014.
        Route::post('/auth/login', LoginController::class)->name('auth.login');
        Route::post('/auth/register', RegisterController::class)->name('auth.register');
        Route::post('/auth/refresh', RefreshController::class)->name('auth.refresh');
        Route::post('/auth/forgot-password', ForgotPasswordController::class)->name('auth.forgot-password');
        Route::post('/auth/reset-password', ResetPasswordController::class)->name('auth.reset-password');
        Route::post('/auth/convite/aceitar', AcceptInviteController::class)->name('auth.invite.accept');

        Route::middleware('auth.jwt')->group(function () {
            Route::post('/auth/logout', LogoutController::class)->name('auth.logout');
            Route::post('/auth/change-password', ChangePasswordController::class)->name('auth.change-password');
            Route::post('/admin/corretores/convite', InviteController::class)->name('admin.corretores.invite');
        });
    });
