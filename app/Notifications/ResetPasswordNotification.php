<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function toMail(mixed $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new \UnexpectedValueException('Password resets can only be sent to a Purplelist user.');
        }

        return (new MailMessage)
            ->subject('Reset your Purplelist password')
            ->view(
                ['html' => 'emails.auth.reset-password', 'text' => 'emails.auth.reset-password-text'],
                [
                    'user' => $notifiable,
                    'resetUrl' => $this->resetUrl($notifiable),
                    'expiresInMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ],
            );
    }
}
