<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class AccountService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function updateProfile(User $user, string $name, string $email): User
    {
        $user->forceFill([
            'name' => $name,
            'email' => $email,
        ]);

        return $this->users->save($user);
    }

    public function updatePassword(User $user, string $password): User
    {
        $user->forceFill(['password' => $password]);

        return $this->users->save($user);
    }
}
