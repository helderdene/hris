<?php

namespace App\Models;

use App\Enums\AccrualMethod;
use App\Enums\GenderRestriction;
use App\Enums\LeaveCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LeaveType model for leave configuration management.
 *
 * Supports Philippine statutory leaves (SIL, Maternity, Paternity, etc.)
 * and custom company leave types with configurable accrual rules,
 * carry-over settings, and cash conversion options.
 */
class LeaveType extends TenantModel
{
    /** @use HasFactory<\Database\Factories\LeaveTypeFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'leave_category',
        'accrual_method',
        'default_days_per_year',
        'monthly_accrual_rate',
        'tenure_brackets',
        'allow_carry_over',
        'max_carry_over_days',
        'carry_over_expiry_months',
        'is_convertible_to_cash',
        'cash_conversion_rate',
        'max_convertible_days',
        'min_tenure_months',
        'eligible_employment_types',
        'gender_restriction',
        'requires_attachment',
        'requires_approval',
        'max_consecutive_days',
        'min_days_advance_notice',
        'is_statutory',
        'statutory_reference',
        'is_template',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_category' => LeaveCategory::class,
            'accrual_method' => AccrualMethod::class,
            'gender_restriction' => GenderRestriction::class,
            'default_days_per_year' => 'decimal:2',
            'monthly_accrual_rate' => 'decimal:4',
            'tenure_brackets' => 'array',
            'allow_carry_over' => 'boolean',
            'max_carry_over_days' => 'decimal:2',
            'carry_over_expiry_months' => 'integer',
            'is_convertible_to_cash' => 'boolean',
            'cash_conversion_rate' => 'decimal:4',
            'max_convertible_days' => 'decimal:2',
            'min_tenure_months' => 'integer',
            'eligible_employment_types' => 'array',
            'requires_attachment' => 'boolean',
            'requires_approval' => 'boolean',
            'max_consecutive_days' => 'integer',
            'min_days_advance_notice' => 'integer',
            'is_statutory' => 'boolean',
            'is_template' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to filter only active leave types.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter only statutory leave types.
     */
    public function scopeStatutory(Builder $query): Builder
    {
        return $query->where('is_statutory', true);
    }

    /**
     * Scope to filter leave types by category.
     */
    public function scopeByCategory(Builder $query, LeaveCategory|string $category): Builder
    {
        $value = $category instanceof LeaveCategory ? $category->value : $category;

        return $query->where('leave_category', $value);
    }

    /**
     * Check if an employee is eligible for this leave type.
     *
     * Validates tenure requirements, employment type, and gender restrictions.
     */
    public function isEmployeeEligible(Employee $employee): bool
    {
        // Check tenure requirement
        if ($this->min_tenure_months !== null) {
            $tenureMonths = $employee->getTenureInMonths();
            if ($tenureMonths < $this->min_tenure_months) {
                return false;
            }
        }

        // Check employment type eligibility
        if ($this->eligible_employment_types !== null && count($this->eligible_employment_types) > 0) {
            $employmentType = $employee->employment_type?->value ?? $employee->employment_type;
            if (! in_array($employmentType, $this->eligible_employment_types, true)) {
                return false;
            }
        }

        // Check gender restriction
        if ($this->gender_restriction !== null) {
            $employeeGender = $employee->gender?->value ?? $employee->gender;
            if ($employeeGender !== $this->gender_restriction->value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate the number of leave days an employee is entitled to based on tenure.
     */
    public function calculateEntitlement(Employee $employee): float
    {
        if ($this->accrual_method === AccrualMethod::TenureBased && $this->tenure_brackets !== null) {
            $tenureYears = $employee->getTenureInYears();

            // Sort brackets by years descending to find the highest applicable tier
            $brackets = collect($this->tenure_brackets)
                ->sortByDesc('years');

            foreach ($brackets as $bracket) {
                if ($tenureYears >= ($bracket['years'] ?? 0)) {
                    return (float) ($bracket['days'] ?? 0);
                }
            }
        }

        return (float) $this->default_days_per_year;
    }

    /**
     * Get all leave balances for this leave type.
     */
    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
