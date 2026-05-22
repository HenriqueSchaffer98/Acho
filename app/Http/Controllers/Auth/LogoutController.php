<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Cookie;

class LogoutController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    public function __construct(private readonly TokenService $tokenService) {}

    public function __invoke(Request $request): Response
    {
        $refreshToken = $request->cookie(self::REFRESH_COOKIE);

        if (is_string($refreshToken) && $refreshToken !== '') {
            $this->tokenService->revokeRefreshToken($refreshToken);
        }

        return response()
            ->noContent()
            ->withCookie($this->clearRefreshCookie());
    }

    private function clearRefreshCookie(): Cookie
    {
        return cookie(
            name: self::REFRESH_COOKIE,
            value: '',
            minutes: -1,
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }
}
