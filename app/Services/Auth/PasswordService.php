<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Data\Auth\ResetPasswordData;
use App\Events\Auth\PasswordChanged;
use App\Events\Auth\PasswordReset;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidTokenException;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Auth\PasswordResetNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class PasswordService
{
    private const RESET_TTL_SECONDS = 3600;

    private const RESET_PURPOSE = 'password_reset';

    public function __construct(private readonly TokenService $tokenService) {}

    /**
     * Issues a reset link to the user's e-mail. Always returns void to avoid
     * leaking whether an account exists for the given e-mail (ADR-009).
     */
    public function forgot(string $email, Tenant $tenant): void
    {
        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->first();

        if ($user === null) {
            return;
        }

        $token = $this->tokenService->generatePurposeToken(
            $user,
            $tenant,
            self::RESET_PURPOSE,
            self::RESET_TTL_SECONDS,
        );

        Notification::send($user, new PasswordResetNotification($token, $tenant));
    }

    /**
     * Validates the reset token, updates the user's password (Argon2id+Pepper
     * via the hashing layer), revokes all active sessions, and issues a fresh
     * authenticated session.
     *
     * @return array{access_token: string, refresh_token: string, user: User}
     *
     * @throws InvalidTokenException
     */
    public function reset(ResetPasswordData $data, Tenant $tenant, string $ip, ?string $userAgent): array
    {
        $payload = $this->tokenService->validateAccessToken($data->token);

        if (($payload['purpose'] ?? null) !== self::RESET_PURPOSE) {
            throw new InvalidTokenException('Token de redefinição inválido.');
        }

        $userId = $payload['user_id'] ?? null;
        $tokenTenantId = $payload['tenant_id'] ?? null;

        if (! is_string($userId) || $tokenTenantId !== $tenant->id) {
            throw new InvalidTokenException('Token de redefinição inválido.');
        }

        $user = User::query()
            ->where('id', $userId)
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->first();

        if ($user === null) {
            throw new InvalidTokenException('Token de redefinição inválido.');
        }

        $user->forceFill([
            'password' => $data->password,
            'password_changed_at' => now(),
        ])->saveQuietly();

        $this->tokenService->revokeAllUserTokens($user->id);

        PasswordReset::dispatch($user, $tenant, $ip);

        $familyId = $this->tokenService->newFamilyId();

        return [
            'access_token' => $this->tokenService->generateAccessToken($user, $tenant),
            'refresh_token' => $this->tokenService->generateRefreshToken($user, $tenant, $familyId, $ip, $userAgent ?? ''),
            'user' => $user,
        ];
    }

    /**
     * Authenticated password change. Verifies the current password, updates
     * the hash, revokes every refresh token EXCEPT the one the caller is
     * currently using (preserves the active session), and dispatches a
     * PasswordChanged event for downstream notifications.
     *
     * @throws InvalidCredentialsException when $currentPassword does not match
     */
    public function change(User $user, Tenant $tenant, string $currentPassword, string $newPassword, string $ip, ?string $currentRefreshToken = null): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new InvalidCredentialsException();
        }

        $user->forceFill([
            'password' => $newPassword,
            'password_changed_at' => now(),
        ])->saveQuietly();

        $exceptHash = $currentRefreshToken !== null && $currentRefreshToken !== ''
            ? $this->tokenService->hashToken($currentRefreshToken)
            : null;

        $this->tokenService->revokeAllUserTokens($user->id, $exceptHash);

        PasswordChanged::dispatch($user, $tenant, $ip);
    }
}
