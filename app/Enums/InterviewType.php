<?php

namespace App\Enums;

/**
 * Types of interviews in the recruitment process.
 */
enum InterviewType: string
{
    case PhoneScreen = 'phone_screen';
    case VideoInterview = 'video_interview';
    case InPerson = 'in_person';
    case PanelInterview = 'panel_interview';
    case TechnicalAssessment = 'technical_assessment';

    /**
     * Get a human-readable label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::PhoneScreen => 'Phone Screen',
            self::VideoInterview => 'Video Interview',
            self::InPerson => 'In-Person',
            self::PanelInterview => 'Panel Interview',
            self::TechnicalAssessment => 'Technical Assessment',
        };
    }

    /**
     * Get the badge color class for this type.
     */
    public function color(): string
    {
        return match ($this) {
            self::PhoneScreen => 'amber',
            self::VideoInterview => 'blue',
            self::InPerson => 'emerald',
            self::PanelInterview => 'purple',
            self::TechnicalAssessment => 'indigo',
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
