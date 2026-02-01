<?php

use App\Enums\LoanType;
use App\Enums\PagibigReportType;
use App\Enums\PayrollEntryStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PagibigReportController;
use App\Http\Controllers\Reports\PagibigReportPageController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanPayment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\PagibigHdlReportGenerator;
use App\Services\Reports\PagibigMcrfReportGenerator;
use App\Services\Reports\PagibigReportService;
use App\Services\Reports\PagibigStlReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function bindTenantForPagibigReports(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserForPagibigReports(Tenant $tenant, TenantUserRole $role): User
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

describe('PagibigReportType Enum', function () {
    it('has correct report types', function () {
        expect(PagibigReportType::cases())->toHaveCount(3);
        expect(PagibigReportType::Mcrf->value)->toBe('mcrf');
        expect(PagibigReportType::Stl->value)->toBe('stl');
        expect(PagibigReportType::Hdl->value)->toBe('hdl');
    });

    it('identifies all reports as monthly', function () {
        expect(PagibigReportType::Mcrf->isMonthlyReport())->toBeTrue();
        expect(PagibigReportType::Stl->isMonthlyReport())->toBeTrue();
        expect(PagibigReportType::Hdl->isMonthlyReport())->toBeTrue();
    });

    it('identifies no reports as quarterly', function () {
        expect(PagibigReportType::Mcrf->isQuarterlyReport())->toBeFalse();
        expect(PagibigReportType::Stl->isQuarterlyReport())->toBeFalse();
        expect(PagibigReportType::Hdl->isQuarterlyReport())->toBeFalse();
    });

    it('provides options array for forms', function () {
        $options = PagibigReportType::options();

        expect($options)->toBeArray();
        expect($options)->toHaveCount(3);
        expect($options[0])->toHaveKeys(['value', 'label', 'shortLabel', 'description', 'periodType']);
    });
});

describe('MCRF Report Generator', function () {
    it('generates contribution data for employees with Pag-IBIG numbers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $department = Department::factory()->create();

        $employee = Employee::factory()->create([
            'pagibig_number' => '1234567890',
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
            'pagibig_employee' => 200,
            'pagibig_employer' => 200,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(PagibigMcrfReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['employee_count'])->toBe(1);
        expect($result['totals']['pagibig_employee'])->toBe(200.0);
        expect($result['totals']['pagibig_employer'])->toBe(200.0);
    });

    it('excludes employees without Pag-IBIG numbers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $employeeWithPagibig = Employee::factory()->create([
            'pagibig_number' => '1234567890',
        ]);

        $employeeWithoutPagibig = Employee::factory()->create([
            'pagibig_number' => null,
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeWithPagibig->id,
            'payroll_period_id' => $payrollPeriod->id,
            'pagibig_employee' => 200,
            'pagibig_employer' => 200,
            'status' => PayrollEntryStatus::Approved,
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeWithoutPagibig->id,
            'payroll_period_id' => $payrollPeriod->id,
            'pagibig_employee' => 150,
            'pagibig_employer' => 150,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(PagibigMcrfReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['employee_count'])->toBe(1);
    });

    it('aggregates multiple payroll entries for same employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $employee = Employee::factory()->create([
            'pagibig_number' => '1234567890',
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
            'pagibig_employee' => 100,
            'pagibig_employer' => 100,
            'status' => PayrollEntryStatus::Approved,
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period2->id,
            'pagibig_employee' => 100,
            'pagibig_employer' => 100,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(PagibigMcrfReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['pagibig_employee'])->toBe(200.0);
        expect($result['totals']['pagibig_employer'])->toBe(200.0);
    });
});

describe('STL Report Generator', function () {
    it('generates monthly short-term loan payment data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $employee = Employee::factory()->create([
            'pagibig_number' => '1234567890',
        ]);

        $loan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigMpl,
            'reference_number' => 'PAGIBIG-2026-001',
            'principal_amount' => 50000,
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $loan->id,
            'amount' => 2000,
            'payment_date' => '2026-01-15',
        ]);

        $generator = app(PagibigStlReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['total_payments'])->toBe(2000.0);
    });

    it('includes both MPL and Calamity loans', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $employee = Employee::factory()->create([
            'pagibig_number' => '1234567890',
        ]);

        $mplLoan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigMpl,
        ]);

        $calamityLoan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigCalamity,
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $mplLoan->id,
            'amount' => 2000,
            'payment_date' => '2026-01-15',
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $calamityLoan->id,
            'amount' => 1500,
            'payment_date' => '2026-01-15',
        ]);

        $generator = app(PagibigStlReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(2);
        expect($result['totals']['total_payments'])->toBe(3500.0);
    });

    it('excludes housing loans', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $employee = Employee::factory()->create([
            'pagibig_number' => '1234567890',
        ]);

        $mplLoan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigMpl,
        ]);

        $housingLoan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigHousing,
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $mplLoan->id,
            'amount' => 2000,
            'payment_date' => '2026-01-15',
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $housingLoan->id,
            'amount' => 5000,
            'payment_date' => '2026-01-15',
        ]);

        $generator = app(PagibigStlReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['totals']['total_payments'])->toBe(2000.0);
    });
});

describe('HDL Report Generator', function () {
    it('generates monthly housing loan payment data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $employee = Employee::factory()->create([
            'pagibig_number' => '1234567890',
        ]);

        $loan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigHousing,
            'reference_number' => 'HOUSING-2026-001',
            'principal_amount' => 500000,
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $loan->id,
            'amount' => 5000,
            'payment_date' => '2026-01-15',
        ]);

        $generator = app(PagibigHdlReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['total_payments'])->toBe(5000.0);
    });

    it('excludes short-term loans', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $employee = Employee::factory()->create([
            'pagibig_number' => '1234567890',
        ]);

        $housingLoan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigHousing,
        ]);

        $mplLoan = EmployeeLoan::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::PagibigMpl,
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $housingLoan->id,
            'amount' => 5000,
            'payment_date' => '2026-01-15',
        ]);

        LoanPayment::factory()->create([
            'employee_loan_id' => $mplLoan->id,
            'amount' => 2000,
            'payment_date' => '2026-01-15',
        ]);

        $generator = app(PagibigHdlReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['totals']['total_payments'])->toBe(5000.0);
    });
});

describe('Pag-IBIG Report Service', function () {
    it('returns available periods without quarters', function () {
        $service = app(PagibigReportService::class);
        $periods = $service->getAvailablePeriods();

        expect($periods)->toHaveKeys(['years', 'months']);
        expect($periods['months'])->toHaveCount(12);
        expect($periods['years'])->toContain((int) date('Y'));
    });
});

describe('Pag-IBIG Report API Controller', function () {
    it('returns periods data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $hrManager = createUserForPagibigReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $controller = new PagibigReportController(app(PagibigReportService::class));
        $response = $controller->periods();

        expect($response->getData(true))->toHaveKeys(['years', 'months']);
    });

    it('returns preview data for MCRF report', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $hrManager = createUserForPagibigReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create([
            'pagibig_number' => '1234567890',
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $payrollPeriod->id,
            'pagibig_employee' => 200,
            'pagibig_employer' => 200,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $request = Request::create('/api/reports/pagibig/preview', 'POST', [
            'report_type' => 'mcrf',
            'year' => 2026,
            'month' => 1,
        ]);

        $controller = new PagibigReportController(app(PagibigReportService::class));
        $response = $controller->preview($request);

        $data = $response->getData(true);
        expect($data)->toHaveKeys(['data', 'totals', 'preview_limit']);
        expect($data['totals'])->toHaveKeys(['employee_count', 'pagibig_employee', 'pagibig_employer']);
    });

    it('rejects invalid report type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $hrManager = createUserForPagibigReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $request = Request::create('/api/reports/pagibig/preview', 'POST', [
            'report_type' => 'invalid',
            'year' => 2026,
            'month' => 1,
        ]);

        $controller = new PagibigReportController(app(PagibigReportService::class));
        $response = $controller->preview($request);

        expect($response->getStatusCode())->toBe(422);
    });
});

describe('Pag-IBIG Reports Page Controller', function () {
    it('creates Inertia response with correct page component and props', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForPagibigReports($tenant);

        $hrManager = createUserForPagibigReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $controller = app(PagibigReportPageController::class);
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

        expect($component)->toBe('Reports/Pagibig/Index');
        expect($props)->toHaveKeys(['reportTypes', 'departments', 'years', 'months']);
    });
});
