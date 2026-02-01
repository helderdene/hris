<?php

namespace App\Enums;

/**
 * Status of an employee certification.
 *
 * Represents the workflow states from draft through approval to active/expired.
 */
enum CertificationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Active => 'Active',
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
            self::PendingApproval => 'amber',
            self::Active => 'green',
            self::Expired => 'red',
            self::Revoked => 'slate',
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
            self::Draft => [self::PendingApproval],
            self::PendingApproval => [self::Active, self::Draft],
            self::Active => [self::Expired, self::Revoked],
            self::Expired => [],
            self::Revoked => [],
        };
    }

    /**
     * Check if this status can transition to the given status.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }

    /**
     * Check if this is a final (terminal) status.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Expired, self::Revoked], true);
    }

    /**
     * Check if certification can be edited in this status.
     */
    public function canBeEdited(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if certification can be submitted in this status.
     */
    public function canBeSubmitted(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if certification is valid for use.
     */
    public function isValid(): bool
    {
        return $this === self::Active;
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
