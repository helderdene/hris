<?php

namespace App\Enums;

/**
 * Regularization recommendation options for probationary evaluation.
 *
 * Used by managers to indicate their recommendation for the employee's
 * transition from probationary to regular status.
 */
enum RegularizationRecommendation: string
{
    case Recommend = 'recommend';
    case RecommendWithConditions = 'recommend_with_conditions';
    case ExtendProbation = 'extend_probation';
    case NotRecommend = 'not_recommend';

    /**
     * Get a human-readable label for the recommendation.
     */
    public function label(): string
    {
        return match ($this) {
            self::Recommend => 'Recommend for Regularization',
            self::RecommendWithConditions => 'Recommend with Conditions',
            self::ExtendProbation => 'Extend Probation',
            self::NotRecommend => 'Do Not Recommend',
        };
    }

    /**
     * Get a short label for the recommendation.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Recommend => 'Recommend',
            self::RecommendWithConditions => 'Conditional',
            self::ExtendProbation => 'Extend',
            self::NotRecommend => 'Not Recommend',
        };
    }

    /**
     * Get the badge color class for this recommendation.
     */
    public function color(): string
    {
        return match ($this) {
            self::Recommend => 'green',
            self::RecommendWithConditions => 'amber',
            self::ExtendProbation => 'blue',
            self::NotRecommend => 'red',
        };
    }

    /**
     * Get the description for this recommendation.
     */
    public function description(): string
    {
        return match ($this) {
            self::Recommend => 'Employee has met all requirements and is ready for regularization.',
            self::RecommendWithConditions => 'Employee shows promise but needs to meet specific conditions before regularization.',
            self::ExtendProbation => 'Employee needs more time to demonstrate competency. Probation period will be extended.',
            self::NotRecommend => 'Employee has not met the requirements for regularization.',
        };
    }

    /**
     * Check if this recommendation requires conditions to be specified.
     */
    public function requiresConditions(): bool
    {
        return $this === self::RecommendWithConditions;
    }

    /**
     * Check if this recommendation requires extension months to be specified.
     */
    public function requiresExtensionMonths(): bool
    {
        return $this === self::ExtendProbation;
    }

    /**
     * Check if this recommendation requires a reason to be specified.
     */
    public function requiresReason(): bool
    {
        return $this === self::NotRecommend;
    }

    /**
     * Check if this is a positive recommendation (leading to regularization).
     */
    public function isPositive(): bool
    {
        return in_array($this, [self::Recommend, self::RecommendWithConditions], true);
    }

    /**
     * Get all available recommendations as an array of values.
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
     * @return array<array{value: string, label: string, shortLabel: string, color: string, description: string, requiresConditions: bool, requiresExtensionMonths: bool, requiresReason: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $recommendation) => [
            'value' => $recommendation->value,
            'label' => $recommendation->label(),
            'shortLabel' => $recommendation->shortLabel(),
            'color' => $recommendation->color(),
            'description' => $recommendation->description(),
            'requiresConditions' => $recommendation->requiresConditions(),
            'requiresExtensionMonths' => $recommendation->requiresExtensionMonths(),
            'requiresReason' => $recommendation->requiresReason(),
        ], self::cases());
    }
}
