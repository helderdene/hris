<?php

namespace App\Enums;

/**
 * Types of development activities.
 *
 * Categorizes the different kinds of activities employees can undertake
 * for their professional development.
 */
enum DevelopmentActivityType: string
{
    case Training = 'training';
    case Mentoring = 'mentoring';
    case SelfStudy = 'self_study';
    case Project = 'project';
    case Certification = 'certification';
    case Other = 'other';

    /**
     * Get a human-readable label for the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Training => 'Training',
            self::Mentoring => 'Mentoring',
            self::SelfStudy => 'Self-Study',
            self::Project => 'Project',
            self::Certification => 'Certification',
            self::Other => 'Other',
        };
    }

    /**
     * Get a description for the activity type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Training => 'Formal training courses, workshops, or seminars',
            self::Mentoring => 'One-on-one guidance from an experienced colleague',
            self::SelfStudy => 'Independent learning through books, videos, or online resources',
            self::Project => 'Hands-on experience through work projects or assignments',
            self::Certification => 'Professional certification or accreditation programs',
            self::Other => 'Other development activities not covered by other categories',
        };
    }

    /**
     * Get the CSS color class for activity type badges.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Training => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::Mentoring => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            self::SelfStudy => 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
            self::Project => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
            self::Certification => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
            self::Other => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
        };
    }

    /**
     * Get an icon name for the activity type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Training => 'graduation-cap',
            self::Mentoring => 'users',
            self::SelfStudy => 'book-open',
            self::Project => 'briefcase',
            self::Certification => 'award',
            self::Other => 'folder',
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
     * Check if a given value is a valid activity type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
