<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\InvalidTokenException;
use App\Exceptions\Auth\TokenReplayException;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Cookie;

class RefreshController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const REFRESH_COOKIE_TTL_MINUTES = 60 * 24 * 30;

    public function __construct(private readonly TokenService $tokenService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie(self::REFRESH_COOKIE);

        if (! is_string($refreshToken) || $refreshToken === '') {
            return $this->unauthorized();
        }

        try {
            $result = $this->tokenService->refreshTokens(
                $refreshToken,
                (string) $request->ip(),
                $request->userAgent() ?? '',
            );
        } catch (TokenReplayException|InvalidTokenException) {
            return $this->unauthorized();
        }

        return response()
            ->json(['access_token' => $result['access_token']])
            ->withCookie($this->refreshCookie($result['refresh_token']));
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

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Sessão expirada.'], 401);
    }
}
