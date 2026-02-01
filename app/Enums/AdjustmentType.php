<?php

namespace App\Enums;

/**
 * Types of payroll adjustments.
 *
 * Covers various allowances, bonuses, and deductions
 * that can be applied to employee payroll.
 */
enum AdjustmentType: string
{
    // Allowances (Earnings)
    case AllowanceTransportation = 'allowance_transportation';
    case AllowanceMeal = 'allowance_meal';
    case AllowancePhone = 'allowance_phone';
    case AllowanceHousing = 'allowance_housing';
    case AllowanceClothing = 'allowance_clothing';
    case AllowanceOther = 'allowance_other';

    // Bonuses (Earnings)
    case BonusPerformance = 'bonus_performance';
    case BonusHoliday = 'bonus_holiday';
    case BonusAttendance = 'bonus_attendance';
    case BonusIncentive = 'bonus_incentive';
    case BonusOther = 'bonus_other';

    // Deductions
    case DeductionUnpaidLeave = 'deduction_unpaid_leave';
    case DeductionTardiness = 'deduction_tardiness';
    case DeductionAbsence = 'deduction_absence';
    case DeductionOther = 'deduction_other';

    // Loans/Advances (Deductions with balance tracking)
    case LoanSalaryAdvance = 'loan_salary_advance';
    case LoanCompanyLoan = 'loan_company_loan';
    case LoanEmergencyLoan = 'loan_emergency_loan';
    case LoanOther = 'loan_other';

    /**
     * Get a human-readable label for the adjustment type.
     */
    public function label(): string
    {
        return match ($this) {
            // Allowances
            self::AllowanceTransportation => 'Transportation Allowance',
            self::AllowanceMeal => 'Meal Allowance',
            self::AllowancePhone => 'Phone Allowance',
            self::AllowanceHousing => 'Housing Allowance',
            self::AllowanceClothing => 'Clothing Allowance',
            self::AllowanceOther => 'Other Allowance',

            // Bonuses
            self::BonusPerformance => 'Performance Bonus',
            self::BonusHoliday => 'Holiday Bonus',
            self::BonusAttendance => 'Attendance Bonus',
            self::BonusIncentive => 'Incentive Bonus',
            self::BonusOther => 'Other Bonus',

            // Deductions
            self::DeductionUnpaidLeave => 'Unpaid Leave Deduction',
            self::DeductionTardiness => 'Tardiness Deduction',
            self::DeductionAbsence => 'Absence Deduction',
            self::DeductionOther => 'Other Deduction',

            // Loans
            self::LoanSalaryAdvance => 'Salary Advance',
            self::LoanCompanyLoan => 'Company Loan',
            self::LoanEmergencyLoan => 'Emergency Loan',
            self::LoanOther => 'Other Loan',
        };
    }

    /**
     * Get the category for this adjustment type.
     */
    public function category(): AdjustmentCategory
    {
        return match ($this) {
            self::AllowanceTransportation, self::AllowanceMeal, self::AllowancePhone,
            self::AllowanceHousing, self::AllowanceClothing, self::AllowanceOther,
            self::BonusPerformance, self::BonusHoliday, self::BonusAttendance,
            self::BonusIncentive, self::BonusOther => AdjustmentCategory::Earning,

            self::DeductionUnpaidLeave, self::DeductionTardiness, self::DeductionAbsence,
            self::DeductionOther, self::LoanSalaryAdvance, self::LoanCompanyLoan,
            self::LoanEmergencyLoan, self::LoanOther => AdjustmentCategory::Deduction,
        };
    }

    /**
     * Get the group name for organizing types.
     */
    public function group(): string
    {
        return match ($this) {
            self::AllowanceTransportation, self::AllowanceMeal, self::AllowancePhone,
            self::AllowanceHousing, self::AllowanceClothing, self::AllowanceOther => 'Allowances',

            self::BonusPerformance, self::BonusHoliday, self::BonusAttendance,
            self::BonusIncentive, self::BonusOther => 'Bonuses',

            self::DeductionUnpaidLeave, self::DeductionTardiness,
            self::DeductionAbsence, self::DeductionOther => 'Deductions',

            self::LoanSalaryAdvance, self::LoanCompanyLoan,
            self::LoanEmergencyLoan, self::LoanOther => 'Loans/Advances',
        };
    }

    /**
     * Check if this adjustment type supports balance tracking.
     *
     * Loan-type adjustments can have a total amount and track remaining balance.
     */
    public function supportsBalanceTracking(): bool
    {
        return match ($this) {
            self::LoanSalaryAdvance, self::LoanCompanyLoan,
            self::LoanEmergencyLoan, self::LoanOther => true,
            default => false,
        };
    }

    /**
     * Check if this is an allowance type.
     */
    public function isAllowance(): bool
    {
        return str_starts_with($this->value, 'allowance_');
    }

    /**
     * Check if this is a bonus type.
     */
    public function isBonus(): bool
    {
        return str_starts_with($this->value, 'bonus_');
    }

    /**
     * Check if this is a deduction type.
     */
    public function isDeduction(): bool
    {
        return str_starts_with($this->value, 'deduction_');
    }

    /**
     * Check if this is a loan type.
     */
    public function isLoan(): bool
    {
        return str_starts_with($this->value, 'loan_');
    }

    /**
     * Get default earning type for payroll line items.
     */
    public function earningType(): ?EarningType
    {
        if ($this->category() !== AdjustmentCategory::Earning) {
            return null;
        }

        if ($this->isAllowance()) {
            return EarningType::Allowance;
        }

        if ($this->isBonus()) {
            return EarningType::Bonus;
        }

        return EarningType::Adjustment;
    }

    /**
     * Get default deduction type for payroll line items.
     */
    public function deductionType(): ?DeductionType
    {
        if ($this->category() !== AdjustmentCategory::Deduction) {
            return null;
        }

        return DeductionType::Other;
    }

    /**
     * Get all available adjustment types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get adjustment types by category.
     *
     * @return array<self>
     */
    public static function byCategory(AdjustmentCategory $category): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type) => $type->category() === $category
        ));
    }

    /**
     * Get all earning types (allowances and bonuses).
     *
     * @return array<self>
     */
    public static function earningTypes(): array
    {
        return self::byCategory(AdjustmentCategory::Earning);
    }

    /**
     * Get all deduction types (deductions and loans).
     *
     * @return array<self>
     */
    public static function deductionTypes(): array
    {
        return self::byCategory(AdjustmentCategory::Deduction);
    }

    /**
     * Get adjustment types grouped by category and group.
     *
     * @return array<string, array<string, array<array{value: string, label: string}>>>
     */
    public static function groupedOptions(): array
    {
        $grouped = [];

        foreach (self::cases() as $case) {
            $categoryLabel = $case->category()->label();
            $group = $case->group();

            if (! isset($grouped[$categoryLabel])) {
                $grouped[$categoryLabel] = [];
            }

            if (! isset($grouped[$categoryLabel][$group])) {
                $grouped[$categoryLabel][$group] = [];
            }

            $grouped[$categoryLabel][$group][] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        return $grouped;
    }
}
