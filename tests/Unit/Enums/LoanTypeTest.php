<?php

use App\Enums\LoanType;

it('has all expected loan types', function () {
    expect(LoanType::cases())->toHaveCount(8);

    expect(LoanType::SssSalary->value)->toBe('sss_salary');
    expect(LoanType::SssCalamity->value)->toBe('sss_calamity');
    expect(LoanType::PagibigMpl->value)->toBe('pagibig_mpl');
    expect(LoanType::PagibigCalamity->value)->toBe('pagibig_calamity');
    expect(LoanType::PagibigHousing->value)->toBe('pagibig_housing');
    expect(LoanType::CompanyCashAdvance->value)->toBe('company_cash_advance');
    expect(LoanType::CompanyEmergency->value)->toBe('company_emergency');
    expect(LoanType::CompanyOther->value)->toBe('company_other');
});

it('returns correct labels for each type', function () {
    expect(LoanType::SssSalary->label())->toBe('SSS Salary Loan');
    expect(LoanType::PagibigMpl->label())->toBe('Pag-IBIG MPL');
    expect(LoanType::CompanyCashAdvance->label())->toBe('Cash Advance');
});

it('returns correct categories', function () {
    expect(LoanType::SssSalary->category())->toBe('SSS');
    expect(LoanType::SssCalamity->category())->toBe('SSS');

    expect(LoanType::PagibigMpl->category())->toBe('Pag-IBIG');
    expect(LoanType::PagibigCalamity->category())->toBe('Pag-IBIG');
    expect(LoanType::PagibigHousing->category())->toBe('Pag-IBIG');

    expect(LoanType::CompanyCashAdvance->category())->toBe('Company');
    expect(LoanType::CompanyEmergency->category())->toBe('Company');
    expect(LoanType::CompanyOther->category())->toBe('Company');
});

it('correctly identifies government loans', function () {
    expect(LoanType::SssSalary->isGovernmentLoan())->toBeTrue();
    expect(LoanType::SssCalamity->isGovernmentLoan())->toBeTrue();
    expect(LoanType::PagibigMpl->isGovernmentLoan())->toBeTrue();
    expect(LoanType::PagibigCalamity->isGovernmentLoan())->toBeTrue();
    expect(LoanType::PagibigHousing->isGovernmentLoan())->toBeTrue();

    expect(LoanType::CompanyCashAdvance->isGovernmentLoan())->toBeFalse();
    expect(LoanType::CompanyEmergency->isGovernmentLoan())->toBeFalse();
    expect(LoanType::CompanyOther->isGovernmentLoan())->toBeFalse();
});

it('correctly identifies company loans', function () {
    expect(LoanType::CompanyCashAdvance->isCompanyLoan())->toBeTrue();
    expect(LoanType::CompanyEmergency->isCompanyLoan())->toBeTrue();
    expect(LoanType::CompanyOther->isCompanyLoan())->toBeTrue();

    expect(LoanType::SssSalary->isCompanyLoan())->toBeFalse();
    expect(LoanType::PagibigMpl->isCompanyLoan())->toBeFalse();
});

it('returns grouped options', function () {
    $grouped = LoanType::groupedOptions();

    expect($grouped)->toHaveKeys(['SSS', 'Pag-IBIG', 'Company']);
    expect($grouped['SSS'])->toHaveCount(2);
    expect($grouped['Pag-IBIG'])->toHaveCount(3);
    expect($grouped['Company'])->toHaveCount(3);

    expect($grouped['SSS'][0])->toMatchArray([
        'value' => 'sss_salary',
        'label' => 'SSS Salary Loan',
    ]);
});

it('returns government loan types', function () {
    $governmentLoans = LoanType::governmentLoans();

    expect($governmentLoans)->toHaveCount(5);
    expect($governmentLoans)->toContain(LoanType::SssSalary);
    expect($governmentLoans)->toContain(LoanType::PagibigMpl);
});

it('returns company loan types', function () {
    $companyLoans = LoanType::companyLoans();

    expect($companyLoans)->toHaveCount(3);
    expect($companyLoans)->toContain(LoanType::CompanyCashAdvance);
    expect($companyLoans)->toContain(LoanType::CompanyEmergency);
});
