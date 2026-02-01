<?php

namespace App\Enums;

/**
 * Education level of a candidate.
 */
enum EducationLevel: string
{
    case HighSchool = 'high_school';
    case Associate = 'associate';
    case Bachelor = 'bachelor';
    case Master = 'master';
    case Doctorate = 'doctorate';
    case Other = 'other';

    /**
     * Get a human-readable label for the education level.
     */
    public function label(): string
    {
        return match ($this) {
            self::HighSchool => 'High School',
            self::Associate => 'Associate Degree',
            self::Bachelor => 'Bachelor\'s Degree',
            self::Master => 'Master\'s Degree',
            self::Doctorate => 'Doctorate',
            self::Other => 'Other',
        };
    }

    /**
     * Get all available levels as an array of values.
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
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $level) => [
            'value' => $level->value,
            'label' => $level->label(),
        ], self::cases());
    }
}
