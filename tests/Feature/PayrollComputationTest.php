<?php

use App\Enums\DtrStatus;
use App\Enums\PayrollCycleType;
use App\Enums\PayrollEntryStatus;
use App\Enums\PayType;
use App\Enums\TenantUserRole;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\OvertimeRequest;
use App\Models\PagibigContributionTable;
use App\Models\PayrollCycle;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PhilhealthContributionTable;
use App\Models\SssContributionTable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payroll\DeductionsCalculator;
use App\Services\Payroll\DtrAggregationService;
use App\Services\Payroll\EarningsCalculator;
use App\Services\Payroll\PayrollComputationService;
use App\Services\Payroll\PayrollRateCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForPayroll(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPayroll(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create all contribution tables for testing.
 */
function createAllContributionTablesForPayroll(): void
{
    SssContributionTable::factory()->year2025()->withBrackets()->create();
    PhilhealthContributionTable::factory()->year2025()->create();
    PagibigContributionTable::factory()->year2025()->withTiers()->create();
}

/**
 * Helper to create an employee with compensation.
 */
function createEmployeeWithCompensation(float $basicPay, PayType $payType): Employee
{
    $employee = Employee::factory()->active()->create();
    EmployeeCompensation::factory()
        ->for($employee)
        ->withBasicPay($basicPay)
        ->state(['pay_type' => $payType])
        ->create();

    return $employee->refresh();
}

/**
 * Helper to create a payroll period with a cycle.
 */
function createPayrollPeriodWithCycle(int $periodNumber = 2, PayrollCycleType $cycleType = PayrollCycleType::SemiMonthly): PayrollPeriod
{
    $cycle = PayrollCycle::factory()->create(['cycle_type' => $cycleType]);

    $year = 2025;
    $month = (int) ceil($periodNumber / 2);
    $isFirstHalf = $periodNumber % 2 === 1;

    $cutoffStart = Carbon::create($year, $month, $isFirstHalf ? 1 : 16);
    $cutoffEnd = $isFirstHalf
        ? Carbon::create($year, $month, 15)
        : Carbon::create($year, $month, 1)->endOfMonth();
    $payDate = $isFirstHalf
        ? Carbon::create($year, $month, 25)
        : Carbon::create($year, $month, 1)->addMonth()->startOfMonth()->addDays(9);

    return PayrollPeriod::factory()
        ->forCycle($cycle)
        ->open()
        ->withDateRange($cutoffStart, $cutoffEnd, $payDate)
        ->create([
            'year' => $year,
            'period_number' => $periodNumber,
        ]);
}

/**
 * Helper to create DTR records for an employee.
 */
function createDtrRecordsForPeriod(
    Employee $employee,
    PayrollPeriod $period,
    int $daysWorked = 10,
    int $lateMinutes = 0,
    int $undertimeMinutes = 0,
    int $overtimeMinutes = 0,
    int $nightDiffMinutes = 0,
    int $absentDays = 0
): void {
    $currentDate = $period->cutoff_start->copy();
    $workDaysCreated = 0;
    $absentDaysCreated = 0;

    while ($currentDate->lte($period->cutoff_end) && ($workDaysCreated < $daysWorked || $absentDaysCreated < $absentDays)) {
        if ($currentDate->isWeekend()) {
            $currentDate->addDay();

            continue;
        }

        if ($absentDaysCreated < $absentDays) {
            DailyTimeRecord::factory()->create([
                'employee_id' => $employee->id,
                'date' => $currentDate->copy(),
                'status' => DtrStatus::Absent,
                'total_work_minutes' => 0,
                'late_minutes' => 0,
                'undertime_minutes' => 0,
                'overtime_minutes' => 0,
                'overtime_approved' => false,
                'night_diff_minutes' => 0,
            ]);
            $absentDaysCreated++;
        } elseif ($workDaysCreated < $daysWorked) {
            $hasOt = $workDaysCreated === 0 && $overtimeMinutes > 0;
            $otRequestId = null;

            if ($hasOt) {
                $otRequest = OvertimeRequest::factory()->approved()->create([
                    'employee_id' => $employee->id,
                    'overtime_date' => $currentDate->copy()->toDateString(),
                    'expected_minutes' => $overtimeMinutes,
                ]);
                $otRequestId = $otRequest->id;
            }

            DailyTimeRecord::factory()->create([
                'employee_id' => $employee->id,
                'date' => $currentDate->copy(),
                'status' => DtrStatus::Present,
                'total_work_minutes' => 480,
                'late_minutes' => $workDaysCreated === 0 ? $lateMinutes : 0,
                'undertime_minutes' => $workDaysCreated === 0 ? $undertimeMinutes : 0,
                'overtime_minutes' => $workDaysCreated === 0 ? $overtimeMinutes : 0,
                'overtime_approved' => $hasOt,
                'overtime_request_id' => $otRequestId,
                'night_diff_minutes' => $workDaysCreated === 0 ? $nightDiffMinutes : 0,
            ]);
            $workDaysCreated++;
        }

        $currentDate->addDay();
    }
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('PayrollRateCalculator', function () {
    it('calculates daily rate for monthly employee', function () {
        $calculator = new PayrollRateCalculator;

        $dailyRate = $calculator->calculateDailyRate(44000, PayType::Monthly);

        expect($dailyRate)->toBe(2000.0);
    });

    it('calculates daily rate for semi-monthly employee', function () {
        $calculator = new PayrollRateCalculator;

        $dailyRate = $calculator->calculateDailyRate(22000, PayType::SemiMonthly);

        expect($dailyRate)->toBe(2000.0);
    });

    it('calculates daily rate for daily employee', function () {
        $calculator = new PayrollRateCalculator;

        $dailyRate = $calculator->calculateDailyRate(800, PayType::Daily);

        expect($dailyRate)->toBe(800.0);
    });

    it('calculates hourly rate from daily rate', function () {
        $calculator = new PayrollRateCalculator;

        $hourlyRate = $calculator->calculateHourlyRate(2000);

        expect($hourlyRate)->toBe(250.0);
    });

    it('calculates minute rate from daily rate', function () {
        $calculator = new PayrollRateCalculator;

        $minuteRate = $calculator->calculateMinuteRate(2400);

        expect($minuteRate)->toBe(5.0);
    });

    it('calculates overtime pay with regular multiplier', function () {
        $calculator = new PayrollRateCalculator;

        $overtimePay = $calculator->calculateOvertimePay(120, 250, 1.25);

        expect($overtimePay)->toBe(625.0);
    });

    it('calculates night differential pay', function () {
        $calculator = new PayrollRateCalculator;

        $nightDiffPay = $calculator->calculateNightDifferentialPay(120, 250);

        expect($nightDiffPay)->toBe(50.0);
    });

    it('calculates absence deduction', function () {
        $calculator = new PayrollRateCalculator;

        $absenceDeduction = $calculator->calculateAbsenceDeduction(2000, 2);

        expect($absenceDeduction)->toBe(4000.0);
    });

    it('calculates tardiness deduction', function () {
        $calculator = new PayrollRateCalculator;

        $tardinessDeduction = $calculator->calculateTardinessDeduction(5, 30, 15);

        expect($tardinessDeduction)->toBe(225.0);
    });

    it('returns correct overtime multipliers', function () {
        $calculator = new PayrollRateCalculator;

        expect($calculator->getOvertimeMultiplier())->toBe(1.25);
        expect($calculator->getOvertimeMultiplier(isRestDay: true))->toBe(1.30);
    });
});

describe('DtrAggregationService', function () {
    it('aggregates DTR data for a payroll period', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle();

        createDtrRecordsForPeriod(
            $employee,
            $period,
            daysWorked: 10,
            lateMinutes: 30,
            overtimeMinutes: 120,
            nightDiffMinutes: 60
        );

        $service = new DtrAggregationService;
        $result = $service->aggregateForPeriod($employee, $period);

        expect($result['days_worked'])->toBe(10.0);
        expect($result['total_late_minutes'])->toBe(30);
        expect($result['total_overtime_minutes'])->toBe(120);
        expect($result['total_night_diff_minutes'])->toBe(60);
    });

    it('counts absent days correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle();

        createDtrRecordsForPeriod(
            $employee,
            $period,
            daysWorked: 8,
            absentDays: 2
        );

        $service = new DtrAggregationService;
        $result = $service->aggregateForPeriod($employee, $period);

        expect($result['days_worked'])->toBe(8.0);
        expect($result['absent_days'])->toBe(2.0);
    });
});

describe('EarningsCalculator', function () {
    it('calculates basic pay for monthly employee on semi-monthly period', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $dtrService = new DtrAggregationService;
        $calculator = app(EarningsCalculator::class);

        $dtrSummary = $dtrService->aggregateForPeriod($employee, $period);
        $result = $calculator->calculate($employee, $period, $dtrSummary);

        expect($result['basic_pay'])->toBe(22000.0);
        expect($result['gross_pay'])->toBe(22000.0);
    });

    it('calculates basic pay for daily employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $employee = createEmployeeWithCompensation(800, PayType::Daily);
        $period = createPayrollPeriodWithCycle();

        createDtrRecordsForPeriod($employee, $period, daysWorked: 10);

        $dtrService = new DtrAggregationService;
        $calculator = app(EarningsCalculator::class);

        $dtrSummary = $dtrService->aggregateForPeriod($employee, $period);
        $result = $calculator->calculate($employee, $period, $dtrSummary);

        expect($result['basic_pay'])->toBe(8000.0);
    });

    it('calculates overtime pay', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle();

        createDtrRecordsForPeriod($employee, $period, daysWorked: 10, overtimeMinutes: 120);

        $dtrService = new DtrAggregationService;
        $calculator = app(EarningsCalculator::class);

        $dtrSummary = $dtrService->aggregateForPeriod($employee, $period);
        $result = $calculator->calculate($employee, $period, $dtrSummary);

        expect($result['overtime_pay'])->toBeGreaterThan(0);
    });

    it('calculates night differential pay', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle();

        createDtrRecordsForPeriod($employee, $period, daysWorked: 10, nightDiffMinutes: 120);

        $dtrService = new DtrAggregationService;
        $calculator = app(EarningsCalculator::class);

        $dtrSummary = $dtrService->aggregateForPeriod($employee, $period);
        $result = $calculator->calculate($employee, $period, $dtrSummary);

        expect($result['night_diff_pay'])->toBeGreaterThan(0);
    });

    it('deducts for absences from monthly salary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle();

        createDtrRecordsForPeriod($employee, $period, daysWorked: 9, absentDays: 1);

        $dtrService = new DtrAggregationService;
        $calculator = app(EarningsCalculator::class);

        $dtrSummary = $dtrService->aggregateForPeriod($employee, $period);
        $result = $calculator->calculate($employee, $period, $dtrSummary);

        expect($result['basic_pay'])->toBeLessThan(22000.0);
    });

    it('creates earning line items', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle();

        createDtrRecordsForPeriod($employee, $period, daysWorked: 10, overtimeMinutes: 60);

        $dtrService = new DtrAggregationService;
        $calculator = app(EarningsCalculator::class);

        $dtrSummary = $dtrService->aggregateForPeriod($employee, $period);
        $result = $calculator->calculate($employee, $period, $dtrSummary);

        expect($result['line_items'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($result['line_items'])->not->toBeEmpty();
    });
});

describe('DeductionsCalculator', function () {
    it('calculates SSS contribution on second cutoff only', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $periodFirst = createPayrollPeriodWithCycle(periodNumber: 1);
        $periodSecond = createPayrollPeriodWithCycle(periodNumber: 2);

        $calculator = app(DeductionsCalculator::class);

        $resultFirst = $calculator->calculate($employee, $periodFirst, 22000);
        $resultSecond = $calculator->calculate($employee, $periodSecond, 22000);

        expect($resultFirst['sss_employee'])->toBe(0.0);
        expect($resultSecond['sss_employee'])->toBeGreaterThan(0);
    });

    it('splits PhilHealth contribution across both cutoffs', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $periodFirst = createPayrollPeriodWithCycle(periodNumber: 1);
        $periodSecond = createPayrollPeriodWithCycle(periodNumber: 2);

        $calculator = app(DeductionsCalculator::class);

        $resultFirst = $calculator->calculate($employee, $periodFirst, 22000);
        $resultSecond = $calculator->calculate($employee, $periodSecond, 22000);

        expect($resultFirst['philhealth_employee'])->toBeGreaterThan(0);
        expect($resultSecond['philhealth_employee'])->toBeGreaterThan(0);
        expect($resultFirst['philhealth_employee'])->toBe($resultSecond['philhealth_employee']);
    });

    it('calculates Pag-IBIG contribution on second cutoff only', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $periodFirst = createPayrollPeriodWithCycle(periodNumber: 1);
        $periodSecond = createPayrollPeriodWithCycle(periodNumber: 2);

        $calculator = app(DeductionsCalculator::class);

        $resultFirst = $calculator->calculate($employee, $periodFirst, 22000);
        $resultSecond = $calculator->calculate($employee, $periodSecond, 22000);

        expect($resultFirst['pagibig_employee'])->toBe(0.0);
        expect($resultSecond['pagibig_employee'])->toBeGreaterThan(0);
    });

    it('creates deduction line items', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        $calculator = app(DeductionsCalculator::class);
        $result = $calculator->calculate($employee, $period, 22000);

        expect($result['line_items'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($result['line_items'])->not->toBeEmpty();
    });
});

describe('PayrollComputationService', function () {
    it('computes payroll for a single employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        expect($entry)->toBeInstanceOf(PayrollEntry::class);
        expect($entry->employee_id)->toBe($employee->id);
        expect($entry->status)->toBe(PayrollEntryStatus::Computed);
        expect($entry->gross_pay)->toBeGreaterThan(0);
        expect($entry->net_pay)->toBeGreaterThan(0);
    });

    it('creates earning and deduction line items', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        expect($entry->earnings)->not->toBeEmpty();
        expect($entry->deductions)->not->toBeEmpty();
    });

    it('calculates net pay correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        $expectedNetPay = (float) $entry->gross_pay - (float) $entry->total_deductions;
        expect((float) $entry->net_pay)->toEqual(round($expectedNetPay, 2));
    });

    it('previews payroll without saving', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $preview = $service->preview($period, $employee);

        expect($preview)->toBeArray();
        expect($preview)->toHaveKey('employee');
        expect($preview)->toHaveKey('period');
        expect($preview)->toHaveKey('earnings');
        expect($preview)->toHaveKey('deductions');
        expect($preview)->toHaveKey('net_pay');

        expect(PayrollEntry::count())->toBe(0);
    });

    it('computes payroll for multiple employees in a period', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employees = collect([
            createEmployeeWithCompensation(30000, PayType::Monthly),
            createEmployeeWithCompensation(44000, PayType::Monthly),
            createEmployeeWithCompensation(60000, PayType::Monthly),
        ]);

        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        foreach ($employees as $employee) {
            createDtrRecordsForPeriod($employee, $period, daysWorked: 11);
        }

        $service = app(PayrollComputationService::class);
        $entries = $service->computeForPeriod($period);

        expect($entries)->toHaveCount(3);
    });

    it('recomputes an existing entry', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $originalEntry = $service->computeForEmployee($period, $employee);
        $originalNetPay = $originalEntry->net_pay;

        // Add overtime to existing DTR record instead of creating a new one
        $existingDtr = DailyTimeRecord::where('employee_id', $employee->id)->first();
        $existingDtr->update([
            'overtime_minutes' => 120,
            'overtime_approved' => true,
        ]);

        $recomputedEntry = $service->recompute($originalEntry->refresh());

        expect($recomputedEntry)->not->toBeNull();
        expect($recomputedEntry->id)->toBe($originalEntry->id);
    });

    it('does not recompute approved entries', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        $entry->update([
            'status' => PayrollEntryStatus::Approved,
            'approved_at' => now(),
        ]);

        $result = $service->recompute($entry->refresh());

        expect($result)->toBeNull();
    });

    it('stores employee snapshot data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        expect($entry->employee_number)->toBe($employee->employee_number);
        expect($entry->employee_name)->toBe($employee->full_name);
        expect((float) $entry->basic_salary_snapshot)->toBe(44000.0);
        expect($entry->pay_type_snapshot)->toBe(PayType::Monthly);
    });
});

describe('Payroll Computation Accuracy', function () {
    it('calculates correct gross pay for typical monthly employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        expect((float) $entry->basic_pay)->toEqual(22000.0);
        expect((float) $entry->gross_pay)->toEqual(22000.0);
    });

    it('calculates correct deductions for ₱25,000 monthly salary on second cutoff', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(50000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        expect($entry->sss_employee)->toBeGreaterThan(0);
        expect($entry->philhealth_employee)->toBeGreaterThan(0);
        expect($entry->pagibig_employee)->toBeGreaterThan(0);
    });

    it('verifies total deductions formula', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        $expectedTotalDeductions = (float) $entry->sss_employee
            + (float) $entry->philhealth_employee
            + (float) $entry->pagibig_employee
            + (float) $entry->withholding_tax
            + (float) $entry->other_deductions_total;

        expect((float) $entry->total_deductions)->toEqual(round($expectedTotalDeductions, 2));
    });

    it('verifies net pay formula', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        createAllContributionTablesForPayroll();

        $employee = createEmployeeWithCompensation(44000, PayType::Monthly);
        $period = createPayrollPeriodWithCycle(periodNumber: 2);

        createDtrRecordsForPeriod($employee, $period, daysWorked: 11);

        $service = app(PayrollComputationService::class);
        $entry = $service->computeForEmployee($period, $employee);

        $expectedNetPay = (float) $entry->gross_pay - (float) $entry->total_deductions;
        expect((float) $entry->net_pay)->toEqual(round($expectedNetPay, 2));
    });
});

describe('PayrollEntry Model', function () {
    it('transitions status correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $entry = PayrollEntry::factory()->draft()->create();

        expect($entry->status)->toBe(PayrollEntryStatus::Draft);
        expect($entry->canTransitionTo(PayrollEntryStatus::Computed))->toBeTrue();
        expect($entry->canTransitionTo(PayrollEntryStatus::Approved))->toBeFalse();
    });

    it('allows recompute for draft and computed entries', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPayroll($tenant);

        $draftEntry = PayrollEntry::factory()->draft()->create();
        $computedEntry = PayrollEntry::factory()->computed()->create();
        $approvedEntry = PayrollEntry::factory()->approved()->create();

        expect($draftEntry->canRecompute())->toBeTrue();
        expect($computedEntry->canRecompute())->toBeTrue();
        expect($approvedEntry->canRecompute())->toBeFalse();
    });
});
