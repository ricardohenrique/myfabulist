<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeVerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function toMail(mixed $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new \UnexpectedValueException('Email verification can only be sent to a Purplelist user.');
        }

        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Welcome to Purplelist — confirm your email')
            ->view(
                ['html' => 'emails.auth.welcome-verify', 'text' => 'emails.auth.welcome-verify-text'],
                ['user' => $notifiable, 'verificationUrl' => $verificationUrl],
            );
    }
}
