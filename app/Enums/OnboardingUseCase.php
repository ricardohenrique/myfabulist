<?php

declare(strict_types=1);

namespace App\Enums;

enum OnboardingUseCase: string
{
    case PersonalTasks = 'personal_tasks';
    case Work = 'work';
    case SchoolStudies = 'school_studies';
    case HouseholdFamily = 'household_family';
    case Projects = 'projects';
    case Everything = 'everything';

    public function label(): string
    {
        return match ($this) {
            self::PersonalTasks => 'Personal tasks',
            self::Work => 'Work',
            self::SchoolStudies => 'School / Studies',
            self::HouseholdFamily => 'Household / Family',
            self::Projects => 'Projects',
            self::Everything => 'A bit of everything',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $useCase): array => [
                'value' => $useCase->value,
                'label' => $useCase->label(),
            ],
            self::cases(),
        );
    }
}
