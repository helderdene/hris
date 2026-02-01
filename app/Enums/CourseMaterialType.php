<?php

namespace App\Enums;

/**
 * Type values for course materials.
 */
enum CourseMaterialType: string
{
    case Document = 'document';
    case Video = 'video';
    case Image = 'image';
    case Link = 'link';

    /**
     * Get a human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Document => 'Document',
            self::Video => 'Video',
            self::Image => 'Image',
            self::Link => 'Link',
        };
    }

    /**
     * Get the icon name for the type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Document => 'document',
            self::Video => 'video',
            self::Image => 'image',
            self::Link => 'link',
        };
    }

    /**
     * Get all available type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Get types that require file upload.
     *
     * @return array<self>
     */
    public static function fileTypes(): array
    {
        return [self::Document, self::Video, self::Image];
    }

    /**
     * Check if this type requires a file upload.
     */
    public function requiresFile(): bool
    {
        return in_array($this, self::fileTypes(), true);
    }
}
