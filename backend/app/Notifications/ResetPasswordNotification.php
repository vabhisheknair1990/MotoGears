<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Reset your '.config('app.name').' password')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('We received a request to reset your password.')
            ->action('Reset password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes. If you did not request it, you can ignore this email.');
    }
}
