<?php

namespace App\Enums;

/**
 * Method by which a reviewer was assigned to an evaluation.
 */
enum AssignmentMethod: string
{
    case Automatic = 'automatic';
    case ManagerSelected = 'manager_selected';
    case HrAssigned = 'hr_assigned';

    /**
     * Get a human-readable label for the assignment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::ManagerSelected => 'Manager Selected',
            self::HrAssigned => 'HR Assigned',
        };
    }

    /**
     * Get a description for the assignment method.
     */
    public function description(): string
    {
        return match ($this) {
            self::Automatic => 'Reviewer was automatically assigned by the system',
            self::ManagerSelected => 'Reviewer was selected by the employee\'s manager',
            self::HrAssigned => 'Reviewer was assigned by HR personnel',
        };
    }

    /**
     * Get the CSS color class for UI display.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Automatic => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::ManagerSelected => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            self::HrAssigned => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        };
    }

    /**
     * Get all available methods as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid assignment method.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
