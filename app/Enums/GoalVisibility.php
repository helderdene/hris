<?php

namespace App\Enums;

/**
 * Visibility levels for goals.
 *
 * Controls who can view a goal within the organization.
 */
enum GoalVisibility: string
{
    case Private = 'private';
    case Team = 'team';
    case Organization = 'organization';

    /**
     * Get a human-readable label for the visibility level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Team => 'Team',
            self::Organization => 'Organization',
        };
    }

    /**
     * Get a description for the visibility level.
     */
    public function description(): string
    {
        return match ($this) {
            self::Private => 'Only visible to you and your manager',
            self::Team => 'Visible to your team members',
            self::Organization => 'Visible to everyone in the organization',
        };
    }

    /**
     * Get the CSS color class for visibility badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Private => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            self::Team => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::Organization => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        };
    }

    /**
     * Get all available visibility levels as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid visibility level.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
