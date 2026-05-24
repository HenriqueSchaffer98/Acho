<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Auth\InvalidTokenException;
use App\Models\User;
use App\Services\Auth\TokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJWT
{
    public function __construct(private readonly TokenService $tokenService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->unauthorized();
        }

        try {
            $payload = $this->tokenService->validateAccessToken($token);
        } catch (InvalidTokenException) {
            return $this->unauthorized();
        }

        $userId = $payload['user_id'] ?? null;

        if (! is_string($userId)) {
            return $this->unauthorized();
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return $this->unauthorized();
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('auth.payload', $payload);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Sessão expirada.'], 401);
    }
}
