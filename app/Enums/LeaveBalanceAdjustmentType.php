<?php

namespace App\Enums;

/**
 * Types of leave balance adjustments.
 *
 * Credits increase the balance, debits decrease it.
 * Used for manual HR adjustments with audit trail.
 */
enum LeaveBalanceAdjustmentType: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    /**
     * Get a human-readable label for the adjustment type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Credit',
            self::Debit => 'Debit',
        };
    }

    /**
     * Get all available adjustment types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid adjustment type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Determine if this adjustment type increases the balance.
     */
    public function isCredit(): bool
    {
        return $this === self::Credit;
    }

    /**
     * Determine if this adjustment type decreases the balance.
     */
    public function isDebit(): bool
    {
        return $this === self::Debit;
    }

    /**
     * Get the sign multiplier for calculations.
     *
     * Returns 1 for credits (add to balance), -1 for debits (subtract from balance).
     */
    public function sign(): int
    {
        return match ($this) {
            self::Credit => 1,
            self::Debit => -1,
        };
    }
}
