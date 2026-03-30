<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly string $resetUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your BoldMark PMS password')
            ->view('emails.reset-password', [
                'name'     => $notifiable->name,
                'resetUrl' => $this->resetUrl,
            ]);
    }
}
