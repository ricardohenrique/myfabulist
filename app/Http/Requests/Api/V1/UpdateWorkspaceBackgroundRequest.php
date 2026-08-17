<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Concerns\WorkspaceBackgroundValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceBackgroundRequest extends FormRequest
{
    use WorkspaceBackgroundValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->workspaceBackgroundRules();
    }

    /**
     * Null clears the background back to "no preference".
     */
    public function optionKey(): ?string
    {
        return $this->validated('option_key');
    }

    /**
     * @return array<string, mixed>
     */
    public function backgroundConfig(): array
    {
        return $this->workspaceBackgroundConfig();
    }
}
