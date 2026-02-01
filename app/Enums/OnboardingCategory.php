<?php

namespace App\Enums;

/**
 * Category of an onboarding checklist item.
 */
enum OnboardingCategory: string
{
    case Provisioning = 'provisioning';
    case Equipment = 'equipment';
    case Orientation = 'orientation';
    case Training = 'training';

    /**
     * Get a human-readable label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Account Provisioning',
            self::Equipment => 'Equipment',
            self::Orientation => 'Orientation',
            self::Training => 'Training',
        };
    }

    /**
     * Get the icon name for this category.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Provisioning => 'key',
            self::Equipment => 'computer-desktop',
            self::Orientation => 'user-group',
            self::Training => 'academic-cap',
        };
    }

    /**
     * Get the default assigned role for this category.
     */
    public function defaultRole(): OnboardingAssignedRole
    {
        return match ($this) {
            self::Provisioning => OnboardingAssignedRole::IT,
            self::Equipment => OnboardingAssignedRole::Admin,
            self::Orientation => OnboardingAssignedRole::HR,
            self::Training => OnboardingAssignedRole::HR,
        };
    }

    /**
     * Get all available categories as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options formatted for frontend select components.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $category) => [
            'value' => $category->value,
            'label' => $category->label(),
        ], self::cases());
    }
}
