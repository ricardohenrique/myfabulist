<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** @phpstan-require-extends FormRequest */
trait CompletionSoundValidationRules
{
    /** @return array<string, array<int, ValidationRule|Exists|string>> */
    protected function completionSoundRules(): array
    {
        return [
            'completion_sound_key' => [
                'nullable',
                'string',
                Rule::exists('completion_sounds', 'key'),
            ],
        ];
    }
}
