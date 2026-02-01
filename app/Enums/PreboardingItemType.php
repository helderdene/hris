<?php

namespace App\Enums;

/**
 * Type of a preboarding checklist item.
 */
enum PreboardingItemType: string
{
    case DocumentUpload = 'document_upload';
    case FormField = 'form_field';
    case Acknowledgment = 'acknowledgment';

    /**
     * Get a human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::DocumentUpload => 'Document Upload',
            self::FormField => 'Form Field',
            self::Acknowledgment => 'Acknowledgment',
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
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::cases());
    }
}
