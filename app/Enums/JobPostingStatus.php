<?php

namespace App\Enums;

/**
 * Status of a job posting.
 *
 * Represents the lifecycle states from draft through archived.
 */
enum JobPostingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
        };
    }

    /**
     * Get the badge color class for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Published => 'green',
            self::Closed => 'red',
            self::Archived => 'slate',
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
            self::Draft => [self::Published],
            self::Published => [self::Closed],
            self::Closed => [self::Archived, self::Published],
            self::Archived => [],
        };
    }

    /**
     * Check if the posting can be edited in this status.
     */
    public function canBeEdited(): bool
    {
        return in_array($this, [self::Draft, self::Published], true);
    }

    /**
     * Check if the posting can be published from this status.
     */
    public function canBePublished(): bool
    {
        return in_array($this, [self::Draft, self::Closed], true);
    }

    /**
     * Check if the posting is publicly visible.
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
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
