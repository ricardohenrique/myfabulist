<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function toMail(mixed $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new \UnexpectedValueException('Email verification can only be sent to a Purplelist user.');
        }

        return (new MailMessage)
            ->subject('Confirm your Purplelist email')
            ->view(
                ['html' => 'emails.auth.verify-email', 'text' => 'emails.auth.verify-email-text'],
                ['user' => $notifiable, 'verificationUrl' => $this->verificationUrl($notifiable)],
            );
    }
}
