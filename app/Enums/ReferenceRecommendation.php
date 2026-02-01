<?php

namespace App\Enums;

/**
 * Recommendation level from a reference check.
 */
enum ReferenceRecommendation: string
{
    case StronglyRecommend = 'strongly_recommend';
    case Recommend = 'recommend';
    case Neutral = 'neutral';
    case NotRecommend = 'not_recommend';

    /**
     * Get a human-readable label for the recommendation.
     */
    public function label(): string
    {
        return match ($this) {
            self::StronglyRecommend => 'Strongly Recommend',
            self::Recommend => 'Recommend',
            self::Neutral => 'Neutral',
            self::NotRecommend => 'Not Recommend',
        };
    }

    /**
     * Get the badge color class for this recommendation.
     */
    public function color(): string
    {
        return match ($this) {
            self::StronglyRecommend => 'green',
            self::Recommend => 'emerald',
            self::Neutral => 'amber',
            self::NotRecommend => 'red',
        };
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
     * @return array<array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $recommendation) => [
            'value' => $recommendation->value,
            'label' => $recommendation->label(),
            'color' => $recommendation->color(),
        ], self::cases());
    }
}
