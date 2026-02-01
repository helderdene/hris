<?php

namespace App\Enums;

/**
 * Type of skills assessment administered to a candidate.
 */
enum AssessmentType: string
{
    case Technical = 'technical';
    case Personality = 'personality';
    case Aptitude = 'aptitude';
    case SkillsBased = 'skills_based';
    case CaseStudy = 'case_study';
    case Other = 'other';

    /**
     * Get a human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Technical',
            self::Personality => 'Personality',
            self::Aptitude => 'Aptitude',
            self::SkillsBased => 'Skills Based',
            self::CaseStudy => 'Case Study',
            self::Other => 'Other',
        };
    }

    /**
     * Get the badge color class for this type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Technical => 'blue',
            self::Personality => 'purple',
            self::Aptitude => 'amber',
            self::SkillsBased => 'emerald',
            self::CaseStudy => 'indigo',
            self::Other => 'slate',
        };
    }

    /**
     * Get all available types as an array of values.
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
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'color' => $type->color(),
        ], self::cases());
    }
}
