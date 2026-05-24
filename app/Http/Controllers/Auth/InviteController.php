<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Data\Auth\InviteData;
use App\Exceptions\Auth\EmailAlreadyRegisteredException;
use App\Http\Requests\Auth\InviteRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class InviteController extends Controller
{
    public function __construct(private readonly InviteService $inviteService) {}

    public function __invoke(InviteRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        /** @var User $admin */
        $admin = $request->user();

        try {
            $this->inviteService->generate(
                InviteData::from($request->validated()),
                $admin,
                $tenant,
            );
        } catch (EmailAlreadyRegisteredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Convite enviado com sucesso.',
        ], 201);
    }
}
