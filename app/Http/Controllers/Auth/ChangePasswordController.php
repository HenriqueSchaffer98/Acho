<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ChangePasswordController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    public function __construct(private readonly PasswordService $passwordService) {}

    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        /** @var User $user */
        $user = $request->user();

        $refreshCookie = $request->cookie(self::REFRESH_COOKIE);

        try {
            $this->passwordService->change(
                $user,
                $tenant,
                (string) $request->validated('current_password'),
                (string) $request->validated('password'),
                (string) $request->ip(),
                is_string($refreshCookie) ? $refreshCookie : null,
            );
        } catch (InvalidCredentialsException) {
            return response()->json([
                'message' => 'Senha atual incorreta.',
            ], 422);
        }

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }
}
