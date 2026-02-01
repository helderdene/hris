<?php

namespace App\Enums;

/**
 * Milestones for probationary employee evaluations.
 *
 * Probationary employees are evaluated at specific points during their
 * probation period to assess their performance and suitability.
 */
enum ProbationaryMilestone: string
{
    case ThirdMonth = 'third_month';
    case FifthMonth = 'fifth_month';

    /**
     * Get a human-readable label for the milestone.
     */
    public function label(): string
    {
        return match ($this) {
            self::ThirdMonth => '3rd Month Evaluation',
            self::FifthMonth => '5th Month Evaluation',
        };
    }

    /**
     * Get the short label for the milestone.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::ThirdMonth => '3rd Month',
            self::FifthMonth => '5th Month',
        };
    }

    /**
     * Get the number of months from hire date for this milestone.
     */
    public function monthsFromHire(): int
    {
        return match ($this) {
            self::ThirdMonth => 3,
            self::FifthMonth => 5,
        };
    }

    /**
     * Get the badge color class for this milestone.
     */
    public function color(): string
    {
        return match ($this) {
            self::ThirdMonth => 'blue',
            self::FifthMonth => 'purple',
        };
    }

    /**
     * Check if this is the final evaluation milestone.
     */
    public function isFinalEvaluation(): bool
    {
        return $this === self::FifthMonth;
    }

    /**
     * Get the next milestone after this one, or null if this is the final.
     */
    public function nextMilestone(): ?self
    {
        return match ($this) {
            self::ThirdMonth => self::FifthMonth,
            self::FifthMonth => null,
        };
    }

    /**
     * Get the previous milestone before this one, or null if this is the first.
     */
    public function previousMilestone(): ?self
    {
        return match ($this) {
            self::ThirdMonth => null,
            self::FifthMonth => self::ThirdMonth,
        };
    }

    /**
     * Get all available milestones as an array of values.
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
        return array_map(fn (self $milestone) => [
            'value' => $milestone->value,
            'label' => $milestone->label(),
            'color' => $milestone->color(),
        ], self::cases());
    }
}
