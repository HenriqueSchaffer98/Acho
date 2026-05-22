<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Events\Auth\UserRegistered;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Cookie;

class RegisterController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const REFRESH_COOKIE_TTL_MINUTES = 60 * 24 * 30;

    public function __construct(private readonly TokenService $tokenService) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        $user = new User;
        $user->tenant_id = $tenant->id;
        $user->name = (string) $request->validated('name');
        $user->email = (string) $request->validated('email');
        $user->password = (string) $request->validated('password');
        $user->role = UserRole::Cliente;
        $user->password_changed_at = now();
        $user->save();

        $ip = (string) $request->ip();
        $familyId = $this->tokenService->newFamilyId();
        $accessToken = $this->tokenService->generateAccessToken($user, $tenant);
        $refreshToken = $this->tokenService->generateRefreshToken(
            $user,
            $tenant,
            $familyId,
            $ip,
            $request->userAgent() ?? '',
        );

        UserRegistered::dispatch($user, $tenant, $ip);

        return response()
            ->json([
                'access_token' => $accessToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ],
            ], 201)
            ->withCookie($this->refreshCookie($refreshToken));
    }

    private function refreshCookie(string $token): Cookie
    {
        return cookie(
            name: self::REFRESH_COOKIE,
            value: $token,
            minutes: self::REFRESH_COOKIE_TTL_MINUTES,
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }
}
