<?php

namespace App\Enums;

/**
 * Types of rules for auto-assigning compliance training.
 */
enum ComplianceRuleType: string
{
    case Department = 'department';
    case Position = 'position';
    case JobLevel = 'job_level';
    case WorkLocation = 'work_location';
    case EmploymentType = 'employment_type';
    case AllEmployees = 'all_employees';

    /**
     * Get a human-readable label for the rule type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Department => 'Department',
            self::Position => 'Position',
            self::JobLevel => 'Job Level',
            self::WorkLocation => 'Work Location',
            self::EmploymentType => 'Employment Type',
            self::AllEmployees => 'All Employees',
        };
    }

    /**
     * Get a description for the rule type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Department => 'Assign to employees in specific departments',
            self::Position => 'Assign to employees in specific positions',
            self::JobLevel => 'Assign to employees at specific job levels',
            self::WorkLocation => 'Assign to employees at specific work locations',
            self::EmploymentType => 'Assign to employees with specific employment types',
            self::AllEmployees => 'Assign to all active employees',
        };
    }

    /**
     * Get all available rule type values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid rule type.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Check if the rule type requires condition values.
     */
    public function requiresConditions(): bool
    {
        return $this !== self::AllEmployees;
    }

    /**
     * Get the condition field name for this rule type.
     */
    public function conditionField(): ?string
    {
        return match ($this) {
            self::Department => 'department_ids',
            self::Position => 'position_ids',
            self::JobLevel => 'job_levels',
            self::WorkLocation => 'work_location_ids',
            self::EmploymentType => 'employment_types',
            self::AllEmployees => null,
        };
    }

    /**
     * Get the employee attribute this rule type matches against.
     */
    public function employeeAttribute(): ?string
    {
        return match ($this) {
            self::Department => 'department_id',
            self::Position => 'position_id',
            self::JobLevel => 'job_level',
            self::WorkLocation => 'work_location_id',
            self::EmploymentType => 'employment_type',
            self::AllEmployees => null,
        };
    }
}
