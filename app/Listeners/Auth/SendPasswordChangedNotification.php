<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\PasswordChanged;
use App\Events\Auth\PasswordReset;
use App\Notifications\Auth\PasswordChangedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendPasswordChangedNotification implements ShouldQueue
{
    public function handle(PasswordReset|PasswordChanged $event): void
    {
        Notification::send(
            $event->user,
            new PasswordChangedNotification(
                ipAddress: $event->ipAddress,
                occurredAt: CarbonImmutable::now(),
            ),
        );
    }
}
