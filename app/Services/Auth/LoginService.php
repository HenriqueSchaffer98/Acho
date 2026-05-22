<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Data\Auth\LoginData;
use App\Events\Auth\UserLoggedIn;
use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class LoginService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 15 * 60;

    public function __construct(private readonly TokenService $tokenService) {}

    /**
     * @return array{access_token: string, refresh_token: string, user: User}
     */
    public function login(LoginData $data, Tenant $tenant, string $ip, ?string $userAgent): array
    {
        $key = $this->throttleKey($tenant, $data->email);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new AccountLockedException(RateLimiter::availableIn($key));
        }

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $data->email)
            ->whereNull('deleted_at')
            ->first();

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            throw new InvalidCredentialsException;
        }

        RateLimiter::clear($key);

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $data->password])->saveQuietly();
        }

        $newIp = $user->last_login_ip !== null && $user->last_login_ip !== $ip;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->saveQuietly();

        $familyId = $this->tokenService->newFamilyId();

        $tokens = [
            'access_token' => $this->tokenService->generateAccessToken($user, $tenant),
            'refresh_token' => $this->tokenService->generateRefreshToken($user, $tenant, $familyId, $ip, $userAgent ?? ''),
            'user' => $user,
        ];

        UserLoggedIn::dispatch($user, $tenant, $ip, $userAgent, $newIp);

        return $tokens;
    }

    private function throttleKey(Tenant $tenant, string $email): string
    {
        return 'login:' . $tenant->id . ':' . strtolower($email);
    }
}
