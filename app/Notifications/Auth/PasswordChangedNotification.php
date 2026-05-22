<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $ipAddress,
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

        return (new MailMessage)
            ->subject('Sua senha foi alterada')
            ->greeting('Olá!')
            ->line('A senha da sua conta foi alterada com sucesso.')
            ->line("**Data e hora:** {$when}")
            ->line("**IP:** {$this->ipAddress}")
            ->line('Todas as sessões ativas foram encerradas — você precisará entrar novamente em outros dispositivos.')
            ->line('Se não foi você que alterou, entre em contato com o suporte imediatamente.')
            ->salutation('Equipe Acho');
    }
}
