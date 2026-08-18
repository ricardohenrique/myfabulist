<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Enums\OnboardingUseCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'use_case' => ['nullable', Rule::enum(OnboardingUseCase::class)],
        ];
    }

    public function useCase(): ?OnboardingUseCase
    {
        $value = $this->validated('use_case');

        return is_string($value) ? OnboardingUseCase::from($value) : null;
    }
}
