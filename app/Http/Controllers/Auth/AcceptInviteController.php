<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Data\Auth\AcceptInviteData;
use App\Exceptions\Auth\InvalidTokenException;
use App\Http\Requests\Auth\AcceptInviteRequest;
use App\Models\Tenant;
use App\Services\Auth\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Cookie;

class AcceptInviteController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const REFRESH_COOKIE_TTL_MINUTES = 60 * 24 * 30;

    public function __construct(private readonly InviteService $inviteService) {}

    public function __invoke(AcceptInviteRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        try {
            $result = $this->inviteService->accept(
                AcceptInviteData::from($request->validated()),
                $tenant,
                (string) $request->ip(),
                $request->userAgent(),
            );
        } catch (InvalidTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 410);
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
            ], 201)
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
