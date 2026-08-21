<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CompletionSound;
use Illuminate\Database\Eloquent\Collection;

interface CompletionSoundRepositoryInterface
{
    /** @return Collection<int, CompletionSound> */
    public function enabled(): Collection;

    public function findByKey(string $key): ?CompletionSound;

    public function findById(int $id): ?CompletionSound;

    public function default(): ?CompletionSound;
}
