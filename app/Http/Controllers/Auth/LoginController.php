<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Data\Auth\LoginData;
use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Cookie;

class LoginController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const REFRESH_COOKIE_TTL_MINUTES = 60 * 24 * 30;

    public function __construct(private readonly LoginService $loginService) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        try {
            $result = $this->loginService->login(
                LoginData::from($request->validated()),
                $tenant,
                (string) $request->ip(),
                $request->userAgent(),
            );
        } catch (AccountLockedException $e) {
            return response()->json([
                'message' => 'Conta bloqueada. Tente novamente em ' . max(1, (int) ceil($e->retryAfterSeconds / 60)) . ' minutos.',
            ], 429);
        } catch (InvalidCredentialsException) {
            return response()->json([
                'message' => 'E-mail ou senha inválidos.',
            ], 401);
        }

        return response()
            ->json([
                'access_token' => $result['access_token'],
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'role' => $result['user']->role->value,
                ],
            ])
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
}
