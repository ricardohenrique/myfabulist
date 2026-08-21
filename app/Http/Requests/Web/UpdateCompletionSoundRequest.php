<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Concerns\CompletionSoundValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompletionSoundRequest extends FormRequest
{
    use CompletionSoundValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->completionSoundRules();
    }

    public function completionSoundKey(): ?string
    {
        return $this->validated('completion_sound_key');
    }
}
