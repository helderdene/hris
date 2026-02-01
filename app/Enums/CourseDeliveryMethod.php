<?php

namespace App\Enums;

/**
 * Delivery methods for training courses.
 */
enum CourseDeliveryMethod: string
{
    case InPerson = 'in_person';
    case Virtual = 'virtual';
    case ELearning = 'e_learning';
    case Blended = 'blended';

    /**
     * Get a human-readable label for the delivery method.
     */
    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'In-Person',
            self::Virtual => 'Virtual',
            self::ELearning => 'E-Learning',
            self::Blended => 'Blended',
        };
    }

    /**
     * Get all available delivery method values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid delivery method.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
