<?php

namespace App\Enums;

/**
 * Status of a visitor visit throughout its lifecycle.
 */
enum VisitStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PreRegistered = 'pre_registered';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Get a human-readable label for the visit status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::PreRegistered => 'Pre-Registered',
            self::CheckedIn => 'Checked In',
            self::CheckedOut => 'Checked Out',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
        };
    }

    /**
     * Get all available status values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
