<?php

declare(strict_types=1);

namespace App\Services\Data;

final readonly class CompletionSoundData
{
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
    ) {}
}
