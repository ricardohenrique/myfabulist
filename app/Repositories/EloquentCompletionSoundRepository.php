<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CompletionSound;
use App\Repositories\Contracts\CompletionSoundRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentCompletionSoundRepository implements CompletionSoundRepositoryInterface
{
    public function enabled(): Collection
    {
        return CompletionSound::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findByKey(string $key): ?CompletionSound
    {
        return CompletionSound::query()->where('key', $key)->first();
    }

    public function findById(int $id): ?CompletionSound
    {
        return CompletionSound::query()->find($id);
    }

    public function default(): ?CompletionSound
    {
        return CompletionSound::query()->where('is_default', true)->first();
    }
}
