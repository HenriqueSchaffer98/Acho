<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Data\Auth\AcceptInviteData;
use App\Data\Auth\InviteData;
use App\Enums\UserRole;
use App\Exceptions\Auth\EmailAlreadyRegisteredException;
use App\Exceptions\Auth\InvalidTokenException;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Auth\CorretorInviteNotification;
use Illuminate\Support\Facades\Notification;

class InviteService
{
    private const INVITE_TTL_SECONDS = 48 * 3600;

    private const INVITE_PURPOSE = 'invite';

    public function __construct(private readonly TokenService $tokenService) {}

    /**
     * Issues a corretor invite by e-mail. Stateless — the JWT carries the
     * invitation (no DB table needed).
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function generate(InviteData $data, User $admin, Tenant $tenant): void
    {
        $existing = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $data->email)
            ->whereNull('deleted_at')
            ->exists();

        if ($existing) {
            throw new EmailAlreadyRegisteredException;
        }

        $token = $this->tokenService->generateAnonymousToken([
            'email' => $data->email,
            'role' => UserRole::Corretor->value,
            'tenant_id' => $tenant->id,
            'purpose' => self::INVITE_PURPOSE,
        ], self::INVITE_TTL_SECONDS);

        Notification::route('mail', $data->email)
            ->notify(new CorretorInviteNotification(
                inviteToken: $token,
                tenant: $tenant,
                invitedByName: $admin->name,
            ));
    }

    /**
     * Accepts a corretor invite: validates the JWT, creates the user, and
     * issues a fresh session.
     *
     * @return array{access_token: string, refresh_token: string, user: User}
     *
     * @throws InvalidTokenException when the token is invalid, expired, has
     *                               the wrong purpose, or the e-mail is
     *                               already taken
     */
    public function accept(AcceptInviteData $data, Tenant $tenant, string $ip, ?string $userAgent): array
    {
        $payload = $this->tokenService->validateAccessToken($data->token);

        if (($payload['purpose'] ?? null) !== self::INVITE_PURPOSE) {
            throw new InvalidTokenException('Convite inválido.');
        }

        $email = $payload['email'] ?? null;
        $role = $payload['role'] ?? null;
        $tokenTenantId = $payload['tenant_id'] ?? null;

        if (! is_string($email) || $role !== UserRole::Corretor->value || $tokenTenantId !== $tenant->id) {
            throw new InvalidTokenException('Convite inválido.');
        }

        $existing = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->exists();

        if ($existing) {
            throw new InvalidTokenException('Este convite já foi utilizado.');
        }

        $user = new User;
        $user->tenant_id = $tenant->id;
        $user->name = $data->name;
        $user->email = $email;
        $user->password = $data->password;
        $user->role = UserRole::Corretor;
        $user->password_changed_at = now();
        $user->save();

        $familyId = $this->tokenService->newFamilyId();

        return [
            'access_token' => $this->tokenService->generateAccessToken($user, $tenant),
            'refresh_token' => $this->tokenService->generateRefreshToken($user, $tenant, $familyId, $ip, $userAgent ?? ''),
            'user' => $user,
        ];
    }
}
