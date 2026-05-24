<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\UserLoggedIn;
use App\Notifications\Auth\NewIpLoginNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendNewIpLoginNotification implements ShouldQueue
{
    public function handle(UserLoggedIn $event): void
    {
        if (! $event->newIp) {
            return;
        }

        Notification::send(
            $event->user,
            new NewIpLoginNotification(
                ipAddress: $event->ipAddress,
                userAgent: $event->userAgent,
                occurredAt: CarbonImmutable::now(),
            ),
        );
    }
}
