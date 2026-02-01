<?php

namespace App\Enums;

/**
 * Content types for compliance training modules.
 */
enum ComplianceModuleContentType: string
{
    case Video = 'video';
    case Text = 'text';
    case Pdf = 'pdf';
    case Scorm = 'scorm';
    case Assessment = 'assessment';

    /**
     * Get a human-readable label for the content type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Text => 'Text Content',
            self::Pdf => 'PDF Document',
            self::Scorm => 'SCORM Package',
            self::Assessment => 'Assessment',
        };
    }

    /**
     * Get the icon name for the content type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Video => 'play-circle',
            self::Text => 'document-text',
            self::Pdf => 'document',
            self::Scorm => 'academic-cap',
            self::Assessment => 'clipboard-check',
        };
    }

    /**
     * Get all available content type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid content type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Check if the content type requires a file upload.
     */
    public function requiresFile(): bool
    {
        return in_array($this, [self::Pdf, self::Scorm], true);
    }

    /**
     * Check if the content type is gradable.
     */
    public function isGradable(): bool
    {
        return $this === self::Assessment;
    }

    /**
     * Check if the content type supports external URLs.
     */
    public function supportsExternalUrl(): bool
    {
        return $this === self::Video;
    }
}
