<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $resetToken,
        public readonly Tenant $tenant,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->buildResetUrl();

        return (new MailMessage)
            ->subject('Recuperação de senha')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em 1 hora.')
            ->line('Se você não solicitou esta redefinição, ignore este e-mail — sua senha continua inalterada.')
            ->salutation('Equipe Acho');
    }

    private function buildResetUrl(): string
    {
        $base = sprintf('https://%s.%s', $this->tenant->slug, config('app.base_domain', 'acho.test'));

        return $base . '/auth/reset-password?token=' . $this->resetToken;
    }
}
