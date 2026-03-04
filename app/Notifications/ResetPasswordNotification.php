<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordBase;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPasswordBase
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Redefinir senha - tecDESK')
            ->greeting('Olá!')
            ->line('Você está recebendo este email porque foi solicitada a redefinição de senha da sua conta.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em ' . config('auth.passwords.users.expire') . ' minutos.')
            ->line('Se você não solicitou a redefinição, ignore este email.');
    }
}
