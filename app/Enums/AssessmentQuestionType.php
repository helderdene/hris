<?php

namespace App\Enums;

/**
 * Question types for compliance assessments.
 */
enum AssessmentQuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case MultiSelect = 'multi_select';

    /**
     * Get a human-readable label for the question type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple Choice',
            self::TrueFalse => 'True/False',
            self::MultiSelect => 'Multi-Select',
        };
    }

    /**
     * Get a description for the question type.
     */
    public function description(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Select one correct answer from multiple options',
            self::TrueFalse => 'Choose true or false',
            self::MultiSelect => 'Select all correct answers that apply',
        };
    }

    /**
     * Get all available question type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid question type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Check if the question type allows multiple answers.
     */
    public function allowsMultipleAnswers(): bool
    {
        return $this === self::MultiSelect;
    }

    /**
     * Get the minimum number of options required.
     */
    public function minOptions(): int
    {
        return match ($this) {
            self::MultipleChoice => 2,
            self::TrueFalse => 2,
            self::MultiSelect => 2,
        };
    }

    /**
     * Get the maximum number of options allowed (null for unlimited).
     */
    public function maxOptions(): ?int
    {
        return match ($this) {
            self::MultipleChoice => null,
            self::TrueFalse => 2,
            self::MultiSelect => null,
        };
    }
}
