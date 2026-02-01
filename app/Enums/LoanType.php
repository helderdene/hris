<?php

namespace App\Enums;

/**
 * Types of employee loans.
 *
 * Covers government loans (SSS, Pag-IBIG) and company loans.
 */
enum LoanType: string
{
    // SSS Loans
    case SssSalary = 'sss_salary';
    case SssCalamity = 'sss_calamity';
    case SssEducational = 'sss_educational';
    case SssEmergency = 'sss_emergency';
    case SssStockInvestment = 'sss_stock_investment';

    // Pag-IBIG Loans
    case PagibigMpl = 'pagibig_mpl';
    case PagibigCalamity = 'pagibig_calamity';
    case PagibigHousing = 'pagibig_housing';

    // Company Loans
    case CompanyCashAdvance = 'company_cash_advance';
    case CompanyEmergency = 'company_emergency';
    case CompanyOther = 'company_other';

    /**
     * Get a human-readable label for the loan type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SssSalary => 'SSS Salary Loan',
            self::SssCalamity => 'SSS Calamity Loan',
            self::SssEducational => 'SSS Educational Loan',
            self::SssEmergency => 'SSS Emergency Loan',
            self::SssStockInvestment => 'SSS Stock Investment Loan',
            self::PagibigMpl => 'Pag-IBIG MPL',
            self::PagibigCalamity => 'Pag-IBIG Calamity Loan',
            self::PagibigHousing => 'Pag-IBIG Housing Loan',
            self::CompanyCashAdvance => 'Cash Advance',
            self::CompanyEmergency => 'Emergency Loan',
            self::CompanyOther => 'Other Company Loan',
        };
    }

    /**
     * Get the category for grouping loan types.
     */
    public function category(): string
    {
        return match ($this) {
            self::SssSalary, self::SssCalamity, self::SssEducational, self::SssEmergency, self::SssStockInvestment => 'SSS',
            self::PagibigMpl, self::PagibigCalamity, self::PagibigHousing => 'Pag-IBIG',
            self::CompanyCashAdvance, self::CompanyEmergency, self::CompanyOther => 'Company',
        };
    }

    /**
     * Check if this is a government loan (SSS or Pag-IBIG).
     */
    public function isGovernmentLoan(): bool
    {
        return match ($this) {
            self::SssSalary, self::SssCalamity, self::SssEducational, self::SssEmergency, self::SssStockInvestment,
            self::PagibigMpl, self::PagibigCalamity, self::PagibigHousing => true,
            self::CompanyCashAdvance, self::CompanyEmergency, self::CompanyOther => false,
        };
    }

    /**
     * Check if this is an SSS loan type.
     */
    public function isSssLoan(): bool
    {
        return $this->category() === 'SSS';
    }

    /**
     * Check if this is a company loan.
     */
    public function isCompanyLoan(): bool
    {
        return ! $this->isGovernmentLoan();
    }

    /**
     * Get all available loan types as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get loan types grouped by category.
     *
     * @return array<string, array<array{value: string, label: string}>>
     */
    public static function groupedOptions(): array
    {
        $grouped = [];

        foreach (self::cases() as $case) {
            $category = $case->category();
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        return $grouped;
    }

    /**
     * Get all government loan types.
     *
     * @return array<self>
     */
    public static function governmentLoans(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->isGovernmentLoan());
    }

    /**
     * Get all company loan types.
     *
     * @return array<self>
     */
    public static function companyLoans(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->isCompanyLoan());
    }

    /**
     * Get all SSS loan types.
     *
     * @return array<self>
     */
    public static function sssLoanTypes(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->isSssLoan());
    }

    /**
     * Get SSS loan type values as strings for database queries.
     *
     * @return array<string>
     */
    public static function sssLoanTypeValues(): array
    {
        return array_map(fn (self $type) => $type->value, self::sssLoanTypes());
    }
}
