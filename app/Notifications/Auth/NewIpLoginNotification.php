<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewIpLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
        public readonly CarbonImmutable $occurredAt,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->occurredAt->setTimezone(config('app.timezone'))->format('d/m/Y H:i');
        $device = $this->userAgent !== null && $this->userAgent !== ''
            ? $this->userAgent
            : 'Dispositivo desconhecido';

        return (new MailMessage)
            ->subject('Novo acesso à sua conta')
            ->greeting('Olá!')
            ->line('Detectamos um acesso à sua conta a partir de um IP diferente do habitual.')
            ->line("**IP:** {$this->ipAddress}")
            ->line("**Data e hora:** {$when}")
            ->line("**Dispositivo:** {$device}")
            ->line('Se foi você, pode ignorar este e-mail.')
            ->line('Se não reconhece este acesso, troque sua senha imediatamente e entre em contato com o suporte.')
            ->salutation('Equipe Acho');
    }
}
