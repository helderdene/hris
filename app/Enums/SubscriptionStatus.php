<?php

namespace App\Enums;

/**
 * PayMongo subscription status values.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Unpaid = 'unpaid';
    case Cancelled = 'cancelled';
    case Incomplete = 'incomplete';
    case IncompleteCancelled = 'incomplete_cancelled';

    /**
     * Get a human-readable label for the subscription status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::PastDue => 'Past Due',
            self::Unpaid => 'Unpaid',
            self::Cancelled => 'Cancelled',
            self::Incomplete => 'Incomplete',
            self::IncompleteCancelled => 'Incomplete Cancelled',
        };
    }

    /**
     * Get all available subscription status values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid subscription status.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a subscription status from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Check if this status represents an active subscription.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
