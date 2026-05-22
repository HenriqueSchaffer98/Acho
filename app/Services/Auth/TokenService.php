<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidTokenException;
use App\Exceptions\Auth\TokenReplayException;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Throwable;

class TokenService
{
    private const ALGORITHM = 'HS256';

    private const ACCESS_TTL_SECONDS = 15 * 60;

    /** @var array<string, int> */
    private const REFRESH_TTL_BY_ROLE = [
        'admin' => 8 * 3600,
        'corretor' => 12 * 3600,
        'cliente' => 30 * 86400,
        'super_admin' => 4 * 3600,
    ];

    public function generateAccessToken(User $user, Tenant $tenant, string $purpose = 'access'): string
    {
        return $this->encodePayload($user, $tenant, $purpose, self::ACCESS_TTL_SECONDS);
    }

    /**
     * Generates a purpose-scoped JWT (e.g. password_reset, invite) with a
     * custom TTL. Caller must validate the `purpose` claim.
     */
    public function generatePurposeToken(User $user, Tenant $tenant, string $purpose, int $ttlSeconds): string
    {
        return $this->encodePayload($user, $tenant, $purpose, $ttlSeconds);
    }

    /**
     * Generates a JWT with a fully custom payload — used for invite tokens
     * where the recipient does NOT yet exist as a User. Adds iat/exp.
     *
     * @param array<string, mixed> $payload
     */
    public function generateAnonymousToken(array $payload, int $ttlSeconds): string
    {
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;

        return JWT::encode($payload, $this->signingKey(), self::ALGORITHM);
    }

    private function encodePayload(User $user, Tenant $tenant, string $purpose, int $ttlSeconds): string
    {
        $now = time();

        $payload = [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => $user->role->value,
            'purpose' => $purpose,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ];

        return JWT::encode($payload, $this->signingKey(), self::ALGORITHM);
    }

    public function generateRefreshToken(User $user, Tenant $tenant, string $familyId, string $ip, string $ua): string
    {
        $token = (string) Str::uuid();
        $ttl = self::REFRESH_TTL_BY_ROLE[$user->role->value];

        RefreshToken::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'family_id' => $familyId,
            'token_hash' => $this->hashToken($token),
            'expires_at' => now()->addSeconds($ttl),
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);

        return $token;
    }

    /** @return array<string, mixed> */
    public function validateAccessToken(string $token): array
    {
        try {
            $payload = JWT::decode($token, new Key($this->signingKey(), self::ALGORITHM));
        } catch (Throwable $e) {
            throw new InvalidTokenException('Invalid or expired access token.', 0, $e);
        }

        return (array) $payload;
    }

    /** @return array{access_token: string, refresh_token: string} */
    public function refreshTokens(string $refreshToken, string $ip, string $ua): array
    {
        $hash = $this->hashToken($refreshToken);

        /** @var RefreshToken|null $stored */
        $stored = RefreshToken::where('token_hash', $hash)->first();

        if ($stored === null) {
            throw new InvalidTokenException('Refresh token not found.');
        }

        if ($stored->revoked) {
            $this->revokeFamily($stored->family_id);
            throw new TokenReplayException('Token replay detected. Family revoked.');
        }

        if ($stored->expires_at->isPast()) {
            throw new InvalidTokenException('Refresh token expired.');
        }

        $stored->update(['revoked' => true, 'revoked_at' => now()]);

        /** @var User $user */
        $user = $stored->user;
        /** @var Tenant $tenant */
        $tenant = $stored->tenant;

        return [
            'access_token' => $this->generateAccessToken($user, $tenant),
            'refresh_token' => $this->generateRefreshToken($user, $tenant, $stored->family_id, $ip, $ua),
        ];
    }

    public function revokeRefreshToken(string $refreshToken): void
    {
        RefreshToken::where('token_hash', $this->hashToken($refreshToken))
            ->where('revoked', false)
            ->update(['revoked' => true, 'revoked_at' => now()]);
    }

    public function revokeFamily(string $familyId): void
    {
        RefreshToken::byFamily($familyId)
            ->where('revoked', false)
            ->update(['revoked' => true, 'revoked_at' => now()]);
    }

    public function revokeAllUserTokens(string $userId, ?string $exceptTokenHash = null): void
    {
        $query = RefreshToken::where('user_id', $userId)
            ->where('revoked', false);

        if ($exceptTokenHash !== null) {
            $query->where('token_hash', '!=', $exceptTokenHash);
        }

        $query->update(['revoked' => true, 'revoked_at' => now()]);
    }

    public function newFamilyId(): string
    {
        return (string) Str::uuid();
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function signingKey(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false) {
                throw new \RuntimeException('Invalid APP_KEY: base64 decode failed.');
            }

            return $decoded;
        }

        return $key;
    }
}
