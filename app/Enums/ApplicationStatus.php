<?php

namespace App\Enums;

/**
 * Status of a job application through the hiring pipeline.
 *
 * Represents the lifecycle from initial application through to hire or rejection.
 */
enum ApplicationStatus: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Interview = 'interview';
    case Assessment = 'assessment';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::Screening => 'Screening',
            self::Interview => 'Interview',
            self::Assessment => 'Assessment',
            self::Offer => 'Offer',
            self::Hired => 'Hired',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Get the badge color class for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Applied => 'blue',
            self::Screening => 'amber',
            self::Interview => 'purple',
            self::Assessment => 'indigo',
            self::Offer => 'emerald',
            self::Hired => 'green',
            self::Rejected => 'red',
            self::Withdrawn => 'slate',
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
            self::Applied => [self::Screening, self::Rejected, self::Withdrawn],
            self::Screening => [self::Interview, self::Rejected, self::Withdrawn],
            self::Interview => [self::Assessment, self::Offer, self::Rejected, self::Withdrawn],
            self::Assessment => [self::Offer, self::Rejected, self::Withdrawn],
            self::Offer => [self::Hired, self::Rejected, self::Withdrawn],
            self::Hired => [],
            self::Rejected => [],
            self::Withdrawn => [],
        };
    }

    /**
     * Check if this is a terminal (final) status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected, self::Withdrawn], true);
    }

    /**
     * Check if this status is at or past the given stage in the pipeline.
     */
    public function isAtOrPast(self $stage): bool
    {
        $pipeline = [
            self::Applied,
            self::Screening,
            self::Interview,
            self::Assessment,
            self::Offer,
            self::Hired,
        ];

        $currentIndex = array_search($this, $pipeline, true);
        $stageIndex = array_search($stage, $pipeline, true);

        if ($currentIndex === false || $stageIndex === false) {
            return false;
        }

        return $currentIndex >= $stageIndex;
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
