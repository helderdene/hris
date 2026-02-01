<?php

namespace App\Enums;

/**
 * Role assigned to handle an onboarding task.
 */
enum OnboardingAssignedRole: string
{
    case IT = 'it';
    case Admin = 'admin';
    case HR = 'hr';

    /**
     * Get a human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::IT => 'IT',
            self::Admin => 'Admin',
            self::HR => 'HR',
        };
    }

    /**
     * Get the badge color class for this role.
     */
    public function color(): string
    {
        return match ($this) {
            self::IT => 'purple',
            self::Admin => 'orange',
            self::HR => 'blue',
        };
    }

    /**
     * Get all available roles as an array of values.
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
     * @return array<array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $role) => [
            'value' => $role->value,
            'label' => $role->label(),
            'color' => $role->color(),
        ], self::cases());
    }
}
