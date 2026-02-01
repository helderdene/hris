<?php

namespace App\Enums;

/**
 * Types of reviewers in a 360-degree performance evaluation.
 */
enum ReviewerType: string
{
    case Self = 'self';
    case Manager = 'manager';
    case Peer = 'peer';
    case DirectReport = 'direct_report';

    /**
     * Get a human-readable label for the reviewer type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Self => 'Self',
            self::Manager => 'Manager',
            self::Peer => 'Peer',
            self::DirectReport => 'Direct Report',
        };
    }

    /**
     * Get a description for the reviewer type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Self => 'The employee evaluates their own performance',
            self::Manager => 'The employee\'s direct manager provides feedback',
            self::Peer => 'A colleague at similar level provides feedback',
            self::DirectReport => 'An employee reporting to this person provides feedback',
        };
    }

    /**
     * Get the CSS color class for UI display.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Self => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            self::Manager => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
            self::Peer => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            self::DirectReport => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
        };
    }

    /**
     * Check if this reviewer type can view and rate KPIs.
     */
    public function canViewKpis(): bool
    {
        return match ($this) {
            self::Self, self::Manager => true,
            self::Peer, self::DirectReport => false,
        };
    }

    /**
     * Get all available types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid reviewer type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
