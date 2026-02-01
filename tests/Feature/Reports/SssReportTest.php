<?php

use App\Enums\LoanType;
use App\Enums\PayrollEntryStatus;
use App\Enums\SssReportType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\SssReportController;
use App\Http\Controllers\Reports\SssReportPageController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanPayment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\SssR3ReportGenerator;
use App\Services\Reports\SssR5ReportGenerator;
use App\Services\Reports\SssReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function bindTenantForSssReports(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserForSssReports(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('SssReportType Enum', function () {
    it('has correct report types', function () {
        expect(SssReportType::cases())->toHaveCount(4);
        expect(SssReportType::R3->value)->toBe('r3');
        expect(SssReportType::R5->value)->toBe('r5');
        expect(SssReportType::Sbr->value)->toBe('sbr');
        expect(SssReportType::Ecl->value)->toBe('ecl');
    });

    it('identifies monthly reports correctly', function () {
        expect(SssReportType::R3->isMonthlyReport())->toBeTrue();
        expect(SssReportType::Sbr->isMonthlyReport())->toBeTrue();
        expect(SssReportType::Ecl->isMonthlyReport())->toBeTrue();
        expect(SssReportType::R5->isMonthlyReport())->toBeFalse();
    });

    it('identifies quarterly reports correctly', function () {
        expect(SssReportType::R5->isQuarterlyReport())->toBeTrue();
        expect(SssReportType::R3->isQuarterlyReport())->toBeFalse();
    });

    it('provides options array for forms', function () {
        $options = SssReportType::options();

        expect($options)->toBeArray();
        expect($options)->toHaveCount(4);
        expect($options[0])->toHaveKeys(['value', 'label', 'shortLabel', 'description', 'periodType']);
    });
});

describe('LoanType SSS Methods', function () {
    it('returns SSS loan types', function () {
        $sssTypes = LoanType::sssLoanTypes();

        expect($sssTypes)->toHaveCount(5);
        expect(array_values($sssTypes))->toContain(LoanType::SssSalary);
        expect(array_values($sssTypes))->toContain(LoanType::SssCalamity);
        expect(array_values($sssTypes))->toContain(LoanType::SssEducational);
        expect(array_values($sssTypes))->toContain(LoanType::SssEmergency);
        expect(array_values($sssTypes))->toContain(LoanType::SssStockInvestment);
    });

    it('returns SSS loan type values', function () {
        $values = LoanType::sssLoanTypeValues();

        expect($values)->toContain('sss_salary');
        expect($values)->toContain('sss_calamity');
        expect($values)->toContain('sss_educational');
        expect($values)->not->toContain('pagibig_mpl');
    });

    it('identifies SSS loans correctly', function () {
        expect(LoanType::SssSalary->isSssLoan())->toBeTrue();
        expect(LoanType::SssEducational->isSssLoan())->toBeTrue();
        expect(LoanType::PagibigMpl->isSssLoan())->toBeFalse();
        expect(LoanType::CompanyCashAdvance->isSssLoan())->toBeFalse();
    });
});

describe('R3 Report Generator', function () {
    it('generates contribution data for employees with SSS numbers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $department = Department::factory()->create();

        $employee = Employee::factory()->create([
            'sss_number' => '1234567890',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'department_id' => $department->id,
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $payrollPeriod->id,
            'employee_name' => 'Dela Cruz, Juan',
            'gross_pay' => 25000,
            'sss_employee' => 900,
            'sss_employer' => 1800,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(SssR3ReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['employee_count'])->toBe(1);
        expect($result['totals']['sss_employee'])->toBe(900.0);
        expect($result['totals']['sss_employer'])->toBe(1800.0);
    });

    it('excludes employees without SSS numbers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $employeeWithSss = Employee::factory()->create([
            'sss_number' => '1234567890',
        ]);

        $employeeWithoutSss = Employee::factory()->create([
            'sss_number' => null,
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeWithSss->id,
            'payroll_period_id' => $payrollPeriod->id,
            'sss_employee' => 900,
            'sss_employer' => 1800,
            'status' => PayrollEntryStatus::Approved,
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeWithoutSss->id,
            'payroll_period_id' => $payrollPeriod->id,
            'sss_employee' => 800,
            'sss_employer' => 1600,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(SssR3ReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['employee_count'])->toBe(1);
    });

    it('aggregates multiple payroll entries for same employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $employee = Employee::factory()->create([
            'sss_number' => '1234567890',
        ]);

        $period1 = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        $period2 = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-16',
            'cutoff_end' => '2026-01-31',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period1->id,
            'sss_employee' => 450,
            'sss_employer' => 900,
            'status' => PayrollEntryStatus::Approved,
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period2->id,
            'sss_employee' => 450,
            'sss_employer' => 900,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(SssR3ReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['sss_employee'])->toBe(900.0);
        expect($result['totals']['sss_employer'])->toBe(1800.0);
    });
});

describe('R5 Report Generator', function () {
    it('generates quarterly loan payment data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $employee = Employee::factory()->create([
            'sss_number' => '1234567890',
        ]);

        $loan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::SssSalary,
            'reference_number' => 'SSS-2026-001',
            'principal_amount' => 50000,
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $loan->id,
            'amount' => 2000,
            'payment_date' => '2026-01-15',
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $loan->id,
            'amount' => 2000,
            'payment_date' => '2026-02-15',
        ]);

        $generator = app(SssR5ReportGenerator::class);
        $result = $generator->getData(year: 2026, quarter: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['total_payments'])->toBe(4000.0);
    });

    it('excludes non-SSS loans', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $employee = Employee::factory()->create([
            'sss_number' => '1234567890',
        ]);

        $sssLoan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::SssSalary,
        ]);

        $pagibigLoan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigMpl,
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $sssLoan->id,
            'amount' => 2000,
            'payment_date' => '2026-01-15',
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $pagibigLoan->id,
            'amount' => 1500,
            'payment_date' => '2026-01-15',
        ]);

        $generator = app(SssR5ReportGenerator::class);
        $result = $generator->getData(year: 2026, quarter: 1);

        expect($result['totals']['total_payments'])->toBe(2000.0);
    });
});

describe('SSS Report Service', function () {
    it('returns available periods', function () {
        $service = app(SssReportService::class);
        $periods = $service->getAvailablePeriods();

        expect($periods)->toHaveKeys(['years', 'months', 'quarters']);
        expect($periods['months'])->toHaveCount(12);
        expect($periods['quarters'])->toHaveCount(4);
        expect($periods['years'])->toContain((int) date('Y'));
    });
});

describe('SSS Report API Controller', function () {
    it('returns periods data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $hrManager = createUserForSssReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $controller = new SssReportController(app(SssReportService::class));
        $response = $controller->periods();

        expect($response->getData(true))->toHaveKeys(['years', 'months', 'quarters']);
    });

    it('returns preview data for R3 report', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $hrManager = createUserForSssReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create([
            'sss_number' => '1234567890',
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $payrollPeriod->id,
            'sss_employee' => 900,
            'sss_employer' => 1800,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $request = Request::create('/api/reports/sss/preview', 'POST', [
            'report_type' => 'r3',
            'year' => 2026,
            'month' => 1,
        ]);

        $controller = new SssReportController(app(SssReportService::class));
        $response = $controller->preview($request);

        $data = $response->getData(true);
        expect($data)->toHaveKeys(['data', 'totals', 'preview_limit']);
        expect($data['totals'])->toHaveKeys(['employee_count', 'sss_employee', 'sss_employer']);
    });

    it('rejects invalid report type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $hrManager = createUserForSssReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $request = Request::create('/api/reports/sss/preview', 'POST', [
            'report_type' => 'invalid',
            'year' => 2026,
            'month' => 1,
        ]);

        $controller = new SssReportController(app(SssReportService::class));
        $response = $controller->preview($request);

        expect($response->getStatusCode())->toBe(422);
    });
});

describe('SSS Reports Page Controller', function () {
    it('creates Inertia response with correct page component and props', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSssReports($tenant);

        $hrManager = createUserForSssReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $controller = app(SssReportPageController::class);
        $response = $controller->index();

        expect($response)->toBeInstanceOf(\Inertia\Response::class);

        // Get the page data from the Inertia response
        $reflection = new ReflectionClass($response);
        $property = $reflection->getProperty('component');
        $property->setAccessible(true);
        $component = $property->getValue($response);

        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($response);

        expect($component)->toBe('Reports/Sss/Index');
        expect($props)->toHaveKeys(['reportTypes', 'departments', 'years', 'months', 'quarters']);
    });
});
