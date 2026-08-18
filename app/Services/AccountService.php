<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OnboardingUseCase;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Str;

class AccountService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function updateProfile(User $user, string $name, string $email): User
    {
        $emailChanged = Str::lower($user->email) !== Str::lower($email);

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ]);

        $user = $this->users->save($user);

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    public function updatePassword(User $user, string $password): User
    {
        $user->forceFill(['password' => $password]);

        return $this->users->save($user);
    }

    /**
     * Record the optional onboarding answer exactly once. A null use case is
     * an explicit skip, distinguished from a pending prompt by the timestamp.
     */
    public function completeOnboarding(User $user, ?OnboardingUseCase $useCase): User
    {
        if (! $user->needsOnboarding()) {
            return $user;
        }

        $user->forceFill([
            'onboarding_use_case' => $useCase,
            'onboarding_completed_at' => now(),
        ]);

        return $this->users->save($user);
    }
}
