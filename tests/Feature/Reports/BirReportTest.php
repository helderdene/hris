<?php

use App\Enums\BirReportType;
use App\Enums\PayrollEntryStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\BirReportController;
use App\Http\Controllers\Reports\BirReportPageController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\Bir1601cReportGenerator;
use App\Services\Reports\BirReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function bindTenantForBirReports(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserForBirReports(Tenant $tenant, TenantUserRole $role): User
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

describe('BirReportType Enum', function () {
    it('has correct report types', function () {
        expect(BirReportType::cases())->toHaveCount(4);
        expect(BirReportType::Form1601c->value)->toBe('1601c');
    });

    it('identifies monthly reports correctly', function () {
        expect(BirReportType::Form1601c->isMonthlyReport())->toBeTrue();
    });

    it('identifies quarterly reports correctly', function () {
        expect(BirReportType::Form1601c->isQuarterlyReport())->toBeFalse();
    });

    it('provides options array for forms', function () {
        $options = BirReportType::options();

        expect($options)->toBeArray();
        expect($options)->toHaveCount(4);
        expect($options[0])->toHaveKeys(['value', 'label', 'shortLabel', 'description', 'periodType']);
    });

    it('has correct labels and descriptions', function () {
        expect(BirReportType::Form1601c->label())->toContain('1601-C');
        expect(BirReportType::Form1601c->shortLabel())->toBe('1601-C');
        expect(BirReportType::Form1601c->description())->toContain('withholding tax');
    });
});

describe('1601-C Report Generator', function () {
    it('generates withholding tax data for employees with TIN', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $department = Department::factory()->create();

        $employee = Employee::factory()->create([
            'tin' => '123-456-789-000',
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
            'philhealth_employee' => 500,
            'pagibig_employee' => 200,
            'withholding_tax' => 1500,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(Bir1601cReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['employee_count'])->toBe(1);
        expect($result['totals']['withholding_tax'])->toBe(1500.0);
        expect($result['totals']['gross_compensation'])->toBe(25000.0);
        // Taxable = gross - government contributions
        expect($result['totals']['taxable_compensation'])->toBe(23400.0);
    });

    it('excludes employees without TIN', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $employeeWithTin = Employee::factory()->create([
            'tin' => '123-456-789-000',
        ]);

        $employeeWithoutTin = Employee::factory()->create([
            'tin' => null,
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeWithTin->id,
            'payroll_period_id' => $payrollPeriod->id,
            'withholding_tax' => 1500,
            'status' => PayrollEntryStatus::Approved,
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeWithoutTin->id,
            'payroll_period_id' => $payrollPeriod->id,
            'withholding_tax' => 1200,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(Bir1601cReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['employee_count'])->toBe(1);
    });

    it('excludes entries with zero withholding tax', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $employeeWithTax = Employee::factory()->create([
            'tin' => '123-456-789-001',
        ]);

        $employeeWithoutTax = Employee::factory()->create([
            'tin' => '123-456-789-002',
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeWithTax->id,
            'payroll_period_id' => $payrollPeriod->id,
            'withholding_tax' => 1500,
            'status' => PayrollEntryStatus::Approved,
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeWithoutTax->id,
            'payroll_period_id' => $payrollPeriod->id,
            'withholding_tax' => 0,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(Bir1601cReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['withholding_tax'])->toBe(1500.0);
    });

    it('aggregates multiple payroll entries for same employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $employee = Employee::factory()->create([
            'tin' => '123-456-789-000',
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
            'gross_pay' => 12500,
            'withholding_tax' => 750,
            'status' => PayrollEntryStatus::Approved,
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period2->id,
            'gross_pay' => 12500,
            'withholding_tax' => 750,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(Bir1601cReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['withholding_tax'])->toBe(1500.0);
        expect($result['totals']['gross_compensation'])->toBe(25000.0);
    });

    it('filters by department when specified', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $department1 = Department::factory()->create(['name' => 'Engineering']);
        $department2 = Department::factory()->create(['name' => 'Sales']);

        $employeeInDept1 = Employee::factory()->create([
            'tin' => '123-456-789-001',
            'department_id' => $department1->id,
        ]);

        $employeeInDept2 = Employee::factory()->create([
            'tin' => '123-456-789-002',
            'department_id' => $department2->id,
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeInDept1->id,
            'payroll_period_id' => $payrollPeriod->id,
            'withholding_tax' => 1500,
            'status' => PayrollEntryStatus::Approved,
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employeeInDept2->id,
            'payroll_period_id' => $payrollPeriod->id,
            'withholding_tax' => 1200,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $generator = app(Bir1601cReportGenerator::class);
        $result = $generator->getData(year: 2026, month: 1, departmentIds: [$department1->id]);

        expect($result['data'])->toHaveCount(1);
        expect($result['totals']['withholding_tax'])->toBe(1500.0);
    });
});

describe('BIR Report Service', function () {
    it('returns available periods', function () {
        $service = app(BirReportService::class);
        $periods = $service->getAvailablePeriods();

        expect($periods)->toHaveKeys(['years', 'months']);
        expect($periods['months'])->toHaveCount(12);
        expect($periods['years'])->toContain((int) date('Y'));
    });
});

describe('BIR Report API Controller', function () {
    it('returns periods data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $hrManager = createUserForBirReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $controller = new BirReportController(app(BirReportService::class));
        $response = $controller->periods();

        expect($response->getData(true))->toHaveKeys(['years', 'months']);
    });

    it('returns preview data for 1601-C report', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $hrManager = createUserForBirReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create([
            'tin' => '123-456-789-000',
        ]);

        $payrollPeriod = PayrollPeriod::factory()->create([
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
        ]);

        PayrollEntry::factory()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $payrollPeriod->id,
            'withholding_tax' => 1500,
            'status' => PayrollEntryStatus::Approved,
        ]);

        $request = Request::create('/api/reports/bir/preview', 'POST', [
            'report_type' => '1601c',
            'year' => 2026,
            'month' => 1,
        ]);

        $controller = new BirReportController(app(BirReportService::class));
        $response = $controller->preview($request);

        $data = $response->getData(true);
        expect($data)->toHaveKeys(['data', 'totals', 'preview_limit']);
        expect($data['totals'])->toHaveKeys(['employee_count', 'gross_compensation', 'taxable_compensation', 'withholding_tax']);
    });

    it('rejects invalid report type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $hrManager = createUserForBirReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $request = Request::create('/api/reports/bir/preview', 'POST', [
            'report_type' => 'invalid',
            'year' => 2026,
            'month' => 1,
        ]);

        $controller = new BirReportController(app(BirReportService::class));
        $response = $controller->preview($request);

        expect($response->getStatusCode())->toBe(422);
    });
});

describe('BIR Reports Page Controller', function () {
    it('creates Inertia response with correct page component and props', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForBirReports($tenant);

        $hrManager = createUserForBirReports($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        Gate::define('can-manage-organization', fn () => true);

        $controller = app(BirReportPageController::class);
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

        expect($component)->toBe('Reports/Bir/Index');
        expect($props)->toHaveKeys(['reportTypes', 'departments', 'years', 'months']);
    });
});
