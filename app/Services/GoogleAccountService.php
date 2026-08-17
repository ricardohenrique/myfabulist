<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GoogleAccountAlreadyLinkedException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use UnexpectedValueException;

class GoogleAccountService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Resolve, safely link, or create the local account represented by a
     * verified Google identity.
     */
    public function resolve(
        string $googleId,
        string $email,
        string $name,
        ?string $avatarUrl,
    ): User {
        $normalizedEmail = Str::lower(trim($email));
        $normalizedName = trim($name);

        if ($googleId === '' || $normalizedEmail === '' || $normalizedName === '') {
            throw new UnexpectedValueException('Google did not return the required account details.');
        }

        $user = $this->users->findByGoogleId($googleId);

        if ($user !== null) {
            return $this->refreshGoogleProfile($user, $avatarUrl);
        }

        $user = $this->users->findByEmail($normalizedEmail);

        if ($user !== null) {
            if ($user->google_id !== null && $user->google_id !== $googleId) {
                throw GoogleAccountAlreadyLinkedException::forEmail($normalizedEmail);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'avatar' => $avatarUrl,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            return $this->users->save($user);
        }

        $user = $this->users->create([
            'name' => $normalizedName,
            'email' => $normalizedEmail,
            'google_id' => $googleId,
            'avatar' => $avatarUrl,
            'email_verified_at' => now(),
            'password' => null,
        ]);

        // Keep Google registration aligned with Fortify registration: these
        // listeners provision the permanent Inbox and default workspace.
        $this->events->dispatch(new Registered($user));

        return $user->refresh();
    }

    private function refreshGoogleProfile(User $user, ?string $avatarUrl): User
    {
        if ($user->avatar === $avatarUrl) {
            return $user;
        }

        $user->forceFill(['avatar' => $avatarUrl]);

        return $this->users->save($user);
    }
}
