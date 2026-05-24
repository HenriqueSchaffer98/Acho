<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CorretorInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $inviteToken,
        public readonly Tenant $tenant,
        public readonly string $invitedByName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->buildAcceptUrl();

        return (new MailMessage)
            ->subject("Convite para ser corretor na {$this->tenant->name}")
            ->greeting('Olá!')
            ->line("Você foi convidado por {$this->invitedByName} para entrar como corretor na imobiliária **{$this->tenant->name}**.")
            ->action('Aceitar convite', $url)
            ->line('No próximo passo você vai definir seu nome e uma senha de acesso.')
            ->line('Este convite expira em 48 horas.')
            ->line('Se você não esperava receber este convite, basta ignorar este e-mail.')
            ->salutation('Equipe Acho');
    }

    private function buildAcceptUrl(): string
    {
        $base = sprintf('https://%s.%s', $this->tenant->slug, config('app.base_domain', 'acho.test'));

        return $base . '/auth/convite/aceitar?token=' . $this->inviteToken;
    }
}
