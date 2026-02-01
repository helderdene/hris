<?php

namespace App\Enums;

/**
 * Type of punch for time record entries.
 */
enum PunchType: string
{
    case In = 'in';
    case Out = 'out';
    case BreakOut = 'break_out';
    case BreakIn = 'break_in';

    /**
     * Get a human-readable label for the punch type.
     */
    public function label(): string
    {
        return match ($this) {
            self::In => 'Time In',
            self::Out => 'Time Out',
            self::BreakOut => 'Break Out',
            self::BreakIn => 'Break In',
        };
    }

    /**
     * Check if this is a clock-in type punch (In or BreakIn).
     */
    public function isInType(): bool
    {
        return match ($this) {
            self::In, self::BreakIn => true,
            self::Out, self::BreakOut => false,
        };
    }

    /**
     * Check if this is a clock-out type punch (Out or BreakOut).
     */
    public function isOutType(): bool
    {
        return ! $this->isInType();
    }

    /**
     * Check if this is a break-related punch.
     */
    public function isBreakType(): bool
    {
        return match ($this) {
            self::BreakOut, self::BreakIn => true,
            self::In, self::Out => false,
        };
    }

    /**
     * Get all available punch types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid punch type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
