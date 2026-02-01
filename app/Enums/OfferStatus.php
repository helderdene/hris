<?php

namespace App\Enums;

/**
 * Status of an offer through its lifecycle.
 *
 * Represents the full offer journey from draft creation through to acceptance,
 * decline, expiry, or revocation.
 */
enum OfferStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Revoked = 'revoked';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Viewed => 'Viewed',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }

    /**
     * Get the badge color class for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Pending => 'amber',
            self::Sent => 'blue',
            self::Viewed => 'purple',
            self::Accepted => 'green',
            self::Declined => 'red',
            self::Expired => 'orange',
            self::Revoked => 'rose',
        };
    }

    /**
     * Get the allowed status transitions from this status.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Sent],
            self::Pending => [self::Sent, self::Revoked],
            self::Sent => [self::Viewed, self::Accepted, self::Declined, self::Expired, self::Revoked],
            self::Viewed => [self::Accepted, self::Declined, self::Expired, self::Revoked],
            self::Accepted => [],
            self::Declined => [],
            self::Expired => [],
            self::Revoked => [],
        };
    }

    /**
     * Check if this is a terminal (final) status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Declined, self::Expired, self::Revoked], true);
    }

    /**
     * Get all available statuses as an array of values.
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
        return array_map(fn (self $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
        ], self::cases());
    }
}
