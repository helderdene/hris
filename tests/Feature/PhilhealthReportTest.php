<?php

use App\Enums\PayrollEntryStatus;
use App\Enums\PhilhealthReportType;
use App\Enums\TenantUserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\PhilhealthReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

/**
 * Helper to bind tenant to the application container for tests.
 */
function setupPhilhealthReportTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createPhilhealthReportUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to create an employee with PhilHealth number.
 */
function createPhilhealthEmployee(array $attributes = []): Employee
{
    return Employee::factory()->active()->create(array_merge([
        'philhealth_number' => fake()->numerify('##-#########-#'),
    ], $attributes));
}

/**
 * Helper to create a payroll entry with PhilHealth contributions.
 */
function createPhilhealthPayrollEntry(
    Employee $employee,
    PayrollPeriod $period,
    float $philhealthEmployee = 500.00,
    float $philhealthEmployer = 500.00
): PayrollEntry {
    return PayrollEntry::factory()->create([
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'employee_name' => "{$employee->last_name}, {$employee->first_name}",
        'gross_pay' => 30000.00,
        'philhealth_employee' => $philhealthEmployee,
        'philhealth_employer' => $philhealthEmployer,
        'status' => PayrollEntryStatus::Approved,
    ]);
}

/*
|--------------------------------------------------------------------------
| PhilhealthReportType Enum Tests
|--------------------------------------------------------------------------
*/

it('returns correct labels for philhealth report types', function () {
    expect(PhilhealthReportType::Rf1->label())->toBe('RF1 - Electronic Remittance Form');
    expect(PhilhealthReportType::Er2->label())->toBe('ER2 - Employer Remittance Report');
    expect(PhilhealthReportType::Mdr->label())->toBe('MDR - Member Data Record');
});

it('returns correct short labels for philhealth report types', function () {
    expect(PhilhealthReportType::Rf1->shortLabel())->toBe('RF1');
    expect(PhilhealthReportType::Er2->shortLabel())->toBe('ER2');
    expect(PhilhealthReportType::Mdr->shortLabel())->toBe('MDR');
});

it('returns correct period type for philhealth report types', function () {
    expect(PhilhealthReportType::Rf1->periodType())->toBe('monthly');
    expect(PhilhealthReportType::Er2->periodType())->toBe('monthly');
    expect(PhilhealthReportType::Mdr->periodType())->toBe('monthly');
});

it('correctly identifies date range support', function () {
    expect(PhilhealthReportType::Rf1->supportsDateRange())->toBeFalse();
    expect(PhilhealthReportType::Er2->supportsDateRange())->toBeFalse();
    expect(PhilhealthReportType::Mdr->supportsDateRange())->toBeTrue();
});

it('returns all options for forms', function () {
    $options = PhilhealthReportType::options();

    expect($options)->toHaveCount(3);
    expect($options[0]['value'])->toBe('rf1');
    expect($options[1]['value'])->toBe('er2');
    expect($options[2]['value'])->toBe('mdr');
});

/*
|--------------------------------------------------------------------------
| RF1 Report Generator Tests
|--------------------------------------------------------------------------
*/

it('generates rf1 report data for employees with philhealth contributions', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    $department = Department::factory()->create();
    $employee = createPhilhealthEmployee([
        'department_id' => $department->id,
    ]);

    $period = PayrollPeriod::factory()->create([
        'cutoff_start' => Carbon::create(2025, 1, 1),
        'cutoff_end' => Carbon::create(2025, 1, 15),
        'year' => 2025,
    ]);

    createPhilhealthPayrollEntry($employee, $period, 500.00, 500.00);

    $service = app(PhilhealthReportService::class);
    $result = $service->preview(
        PhilhealthReportType::Rf1,
        year: 2025,
        month: 1
    );

    expect($result['data'])->toHaveCount(1);
    expect($result['totals']['employee_count'])->toBe(1);
    expect($result['totals']['philhealth_employee'])->toBe(500.00);
    expect($result['totals']['philhealth_employer'])->toBe(500.00);
    expect($result['totals']['total_contribution'])->toBe(1000.00);
});

it('aggregates multiple payroll entries for same employee in rf1', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    $employee = createPhilhealthEmployee();

    $period1 = PayrollPeriod::factory()->create([
        'cutoff_start' => Carbon::create(2025, 1, 1),
        'cutoff_end' => Carbon::create(2025, 1, 15),
        'year' => 2025,
    ]);

    $period2 = PayrollPeriod::factory()->create([
        'cutoff_start' => Carbon::create(2025, 1, 16),
        'cutoff_end' => Carbon::create(2025, 1, 31),
        'year' => 2025,
    ]);

    createPhilhealthPayrollEntry($employee, $period1, 250.00, 250.00);
    createPhilhealthPayrollEntry($employee, $period2, 250.00, 250.00);

    $service = app(PhilhealthReportService::class);
    $result = $service->preview(
        PhilhealthReportType::Rf1,
        year: 2025,
        month: 1
    );

    expect($result['data'])->toHaveCount(1);
    expect($result['totals']['philhealth_employee'])->toBe(500.00);
    expect($result['totals']['total_contribution'])->toBe(1000.00);
});

it('excludes employees without philhealth number from rf1', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    $employeeWithPhilhealth = createPhilhealthEmployee();
    $employeeWithoutPhilhealth = Employee::factory()->active()->create([
        'philhealth_number' => null,
    ]);

    $period = PayrollPeriod::factory()->create([
        'cutoff_start' => Carbon::create(2025, 1, 1),
        'cutoff_end' => Carbon::create(2025, 1, 15),
        'year' => 2025,
    ]);

    createPhilhealthPayrollEntry($employeeWithPhilhealth, $period);
    createPhilhealthPayrollEntry($employeeWithoutPhilhealth, $period);

    $service = app(PhilhealthReportService::class);
    $result = $service->preview(
        PhilhealthReportType::Rf1,
        year: 2025,
        month: 1
    );

    expect($result['data'])->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| ER2 Report Generator Tests
|--------------------------------------------------------------------------
*/

it('generates er2 report data for active employees with philhealth number', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    $employee = createPhilhealthEmployee([
        'basic_salary' => 30000.00,
    ]);

    $service = app(PhilhealthReportService::class);
    $result = $service->preview(
        PhilhealthReportType::Er2,
        year: 2025,
        month: 1
    );

    expect($result['data'])->toHaveCount(1);
    expect($result['totals']['employee_count'])->toBe(1);
    expect($result['totals']['total_salary'])->toBe(30000.00);
});

it('excludes terminated employees from er2', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    createPhilhealthEmployee();
    Employee::factory()->create([
        'philhealth_number' => fake()->numerify('##-#########-#'),
        'termination_date' => now()->subMonth(),
    ]);

    $service = app(PhilhealthReportService::class);
    $result = $service->preview(
        PhilhealthReportType::Er2,
        year: 2025,
        month: 1
    );

    expect($result['data'])->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| MDR Report Generator Tests
|--------------------------------------------------------------------------
*/

it('generates mdr report data for new hires in selected month', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    Employee::factory()->create([
        'hire_date' => Carbon::create(2025, 1, 15),
    ]);
    Employee::factory()->create([
        'hire_date' => Carbon::create(2025, 2, 1),
    ]);

    $service = app(PhilhealthReportService::class);
    $result = $service->preview(
        PhilhealthReportType::Mdr,
        year: 2025,
        month: 1
    );

    expect($result['data'])->toHaveCount(1);
    expect($result['totals']['employee_count'])->toBe(1);
});

it('generates mdr report data for custom date range', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    Employee::factory()->create([
        'hire_date' => Carbon::create(2025, 1, 15),
    ]);
    Employee::factory()->create([
        'hire_date' => Carbon::create(2025, 2, 10),
    ]);
    Employee::factory()->create([
        'hire_date' => Carbon::create(2025, 3, 1),
    ]);

    $service = app(PhilhealthReportService::class);
    $result = $service->preview(
        PhilhealthReportType::Mdr,
        year: 2025,
        startDate: Carbon::create(2025, 1, 1),
        endDate: Carbon::create(2025, 2, 28)
    );

    expect($result['data'])->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| Report Generation Tests
|--------------------------------------------------------------------------
*/

it('generates excel file for rf1 report', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    $employee = createPhilhealthEmployee();
    $period = PayrollPeriod::factory()->create([
        'cutoff_start' => Carbon::create(2025, 1, 1),
        'cutoff_end' => Carbon::create(2025, 1, 15),
        'year' => 2025,
    ]);
    createPhilhealthPayrollEntry($employee, $period);

    $service = app(PhilhealthReportService::class);
    $result = $service->generate(
        PhilhealthReportType::Rf1,
        'xlsx',
        year: 2025,
        month: 1
    );

    expect($result['filename'])->toContain('philhealth_rf1_2025-01.xlsx');
    expect($result['contentType'])->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($result['content'])->not->toBeEmpty();
});

it('generates pdf file for rf1 report', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    $employee = createPhilhealthEmployee();
    $period = PayrollPeriod::factory()->create([
        'cutoff_start' => Carbon::create(2025, 1, 1),
        'cutoff_end' => Carbon::create(2025, 1, 15),
        'year' => 2025,
    ]);
    createPhilhealthPayrollEntry($employee, $period);

    $service = app(PhilhealthReportService::class);
    $result = $service->generate(
        PhilhealthReportType::Rf1,
        'pdf',
        year: 2025,
        month: 1
    );

    expect($result['filename'])->toContain('philhealth_rf1_2025-01.pdf');
    expect($result['contentType'])->toBe('application/pdf');
    expect($result['content'])->not->toBeEmpty();
});

it('filters rf1 report by department', function () {
    $tenant = Tenant::factory()->create();
    setupPhilhealthReportTenant($tenant);

    $dept1 = Department::factory()->create(['name' => 'Engineering']);
    $dept2 = Department::factory()->create(['name' => 'Sales']);

    $emp1 = createPhilhealthEmployee(['department_id' => $dept1->id]);
    $emp2 = createPhilhealthEmployee(['department_id' => $dept2->id]);

    $period = PayrollPeriod::factory()->create([
        'cutoff_start' => Carbon::create(2025, 1, 1),
        'cutoff_end' => Carbon::create(2025, 1, 15),
        'year' => 2025,
    ]);

    createPhilhealthPayrollEntry($emp1, $period);
    createPhilhealthPayrollEntry($emp2, $period);

    $service = app(PhilhealthReportService::class);
    $result = $service->preview(
        PhilhealthReportType::Rf1,
        year: 2025,
        month: 1,
        departmentIds: [$dept1->id]
    );

    expect($result['data'])->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Available Periods Tests
|--------------------------------------------------------------------------
*/

it('returns available periods for report generation', function () {
    $service = app(PhilhealthReportService::class);
    $periods = $service->getAvailablePeriods();

    expect($periods)->toHaveKey('years');
    expect($periods)->toHaveKey('months');
    expect($periods['years'])->toHaveCount(6);
    expect($periods['months'])->toHaveCount(12);
});
