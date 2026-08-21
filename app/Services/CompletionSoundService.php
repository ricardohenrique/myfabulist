<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidCompletionSoundSelectionException;
use App\Models\CompletionSound;
use App\Models\User;
use App\Repositories\Contracts\CompletionSoundRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Data\CompletionSoundData;
use Illuminate\Database\Eloquent\Collection;

class CompletionSoundService
{
    public function __construct(
        private readonly CompletionSoundRepositoryInterface $sounds,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** @return Collection<int, CompletionSound> */
    public function availableOptionsFor(User $user): Collection
    {
        $enabled = $this->sounds->enabled();

        if ($user->completion_sound_id === null || $enabled->contains('id', $user->completion_sound_id)) {
            return $enabled;
        }

        $current = $this->sounds->findById($user->completion_sound_id);

        return $current === null ? $enabled : $enabled->push($current);
    }

    public function resolvedSoundFor(User $user): ?CompletionSoundData
    {
        if ($user->completion_sound_id === null) {
            return null;
        }

        $sound = $this->sounds->findById($user->completion_sound_id);

        return $sound === null ? null : $this->toData($sound);
    }

    public function updateSelection(User $user, ?string $key): User
    {
        if ($key === null) {
            $user->forceFill(['completion_sound_id' => null]);

            return $this->users->save($user);
        }

        $sound = $this->sounds->findByKey($key);

        if ($sound === null) {
            throw InvalidCompletionSoundSelectionException::becauseUnknownKey($key);
        }

        $isCurrentSelection = $user->completion_sound_id === $sound->id;

        if (! $sound->enabled && ! $isCurrentSelection) {
            throw InvalidCompletionSoundSelectionException::becauseDisabled($key);
        }

        $user->forceFill(['completion_sound_id' => $sound->id]);

        return $this->users->save($user);
    }

    public function assignDefaultTo(User $user): void
    {
        if ($user->completion_sound_id !== null) {
            return;
        }

        $default = $this->sounds->default();

        if ($default === null) {
            return;
        }

        $user->forceFill(['completion_sound_id' => $default->id]);
        $this->users->save($user);
    }

    private function toData(CompletionSound $sound): CompletionSoundData
    {
        return new CompletionSoundData(
            key: $sound->key,
            label: $sound->label,
            url: $sound->publicUrl(),
        );
    }
}
