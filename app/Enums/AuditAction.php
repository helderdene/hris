<?php

namespace App\Enums;

/**
 * Types of audit actions that can be logged.
 *
 * Represents the type of change made to a model.
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    /**
     * Get a human-readable label for the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
        };
    }

    /**
     * Get the badge color class for this action.
     */
    public function color(): string
    {
        return match ($this) {
            self::Created => 'green',
            self::Updated => 'blue',
            self::Deleted => 'red',
        };
    }

    /**
     * Get all available actions as an array of values.
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
        return array_map(fn (self $action) => [
            'value' => $action->value,
            'label' => $action->label(),
            'color' => $action->color(),
        ], self::cases());
    }
}
