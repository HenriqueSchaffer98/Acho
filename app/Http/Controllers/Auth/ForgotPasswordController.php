<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\Tenant;
use App\Services\Auth\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ForgotPasswordController extends Controller
{
    public function __construct(private readonly PasswordService $passwordService) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        $this->passwordService->forgot((string) $request->validated('email'), $tenant);

        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, você receberá as instruções para redefinir sua senha.',
        ]);
    }
}
