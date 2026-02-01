<?php

use App\Enums\AccrualMethod;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveBalanceAdjustmentType;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->service = app(LeaveBalanceService::class);
});

describe('initializeBalancesForEmployee', function () {
    it('creates balance records for eligible leave types', function () {
        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => now()->subYears(2),
        ]);

        LeaveType::factory()->count(3)->create(['is_active' => true]);

        $balances = $this->service->initializeBalancesForEmployee($employee, now()->year);

        expect($balances)->toHaveCount(3);
        expect(LeaveBalance::forEmployee($employee)->forYear(now()->year)->count())->toBe(3);
    });

    it('does not create duplicate balances', function () {
        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => now()->subYears(2),
        ]);

        LeaveType::factory()->create(['is_active' => true]);

        // Initialize once
        $this->service->initializeBalancesForEmployee($employee, now()->year);

        // Initialize again
        $balances = $this->service->initializeBalancesForEmployee($employee, now()->year);

        expect(LeaveBalance::forEmployee($employee)->forYear(now()->year)->count())->toBe(1);
    });

    it('credits annual entitlement on initialization for annual accrual', function () {
        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => now()->subYears(2),
        ]);

        LeaveType::factory()->create([
            'is_active' => true,
            'accrual_method' => AccrualMethod::Annual,
            'default_days_per_year' => 15,
        ]);

        $balances = $this->service->initializeBalancesForEmployee($employee, now()->year);

        $balance = $balances->first();
        expect((float) $balance->earned)->toBe(15.0);
    });

    it('starts with zero earned for monthly accrual', function () {
        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => now()->subYears(2),
        ]);

        LeaveType::factory()->create([
            'is_active' => true,
            'accrual_method' => AccrualMethod::Monthly,
            'default_days_per_year' => 12,
            'monthly_accrual_rate' => 1,
        ]);

        $balances = $this->service->initializeBalancesForEmployee($employee, now()->year);

        $balance = $balances->first();
        expect((float) $balance->earned)->toBe(0.0);
    });
});

describe('recordAdjustment', function () {
    it('creates audit trail for credit adjustment', function () {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'adjustments' => 0,
        ]);

        $user = User::factory()->create();

        $adjustment = $this->service->recordAdjustment(
            balance: $balance,
            type: LeaveBalanceAdjustmentType::Credit,
            days: 5,
            reason: 'Manual credit adjustment for testing purposes',
            userId: $user->id
        );

        expect($adjustment->adjustment_type)->toBe(LeaveBalanceAdjustmentType::Credit);
        expect((float) $adjustment->days)->toBe(5.0);
        expect((float) $adjustment->previous_balance)->toBe(0.0);
        expect((float) $adjustment->new_balance)->toBe(5.0);

        $balance->refresh();
        expect((float) $balance->adjustments)->toBe(5.0);
    });

    it('creates audit trail for debit adjustment', function () {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'adjustments' => 10,
        ]);

        $user = User::factory()->create();

        $adjustment = $this->service->recordAdjustment(
            balance: $balance,
            type: LeaveBalanceAdjustmentType::Debit,
            days: 3,
            reason: 'Manual debit adjustment for testing purposes',
            userId: $user->id
        );

        expect($adjustment->adjustment_type)->toBe(LeaveBalanceAdjustmentType::Debit);
        expect((float) $adjustment->previous_balance)->toBe(10.0);
        expect((float) $adjustment->new_balance)->toBe(7.0);

        $balance->refresh();
        expect((float) $balance->adjustments)->toBe(7.0);
    });
});

describe('reserveBalance', function () {
    it('reserves balance when available', function () {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 10,
            'used' => 0,
            'pending' => 0,
        ]);

        $result = $this->service->reserveBalance($employee, $leaveType, now()->year, 3);

        expect($result)->toBeTrue();

        $balance->refresh();
        expect((float) $balance->pending)->toBe(3.0);
    });

    it('returns false when insufficient balance', function () {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 5,
            'used' => 4,
            'pending' => 0,
        ]);

        $result = $this->service->reserveBalance($employee, $leaveType, now()->year, 3);

        expect($result)->toBeFalse();

        $balance->refresh();
        expect((float) $balance->pending)->toBe(0.0);
    });
});

describe('releaseReservedBalance', function () {
    it('releases pending balance', function () {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'pending' => 5,
        ]);

        $this->service->releaseReservedBalance($employee, $leaveType, now()->year, 3);

        $balance->refresh();
        expect((float) $balance->pending)->toBe(2.0);
    });
});

describe('processYearEnd', function () {
    it('carries over unused balance when allowed', function () {
        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $leaveType = LeaveType::factory()->create([
            'allow_carry_over' => true,
            'max_carry_over_days' => 10,
            'carry_over_expiry_months' => 3,
        ]);

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year - 1,
            'earned' => 15,
            'used' => 5,
            'pending' => 0,
            'adjustments' => 0,
            'brought_forward' => 0,
            'expired' => 0,
        ]);

        $result = $this->service->processYearEnd(now()->year - 1);

        expect($result['carried_over'])->toBeGreaterThan(0);

        // Check new year balance
        $newBalance = LeaveBalance::query()
            ->forEmployee($employee)
            ->forLeaveType($leaveType)
            ->forYear(now()->year)
            ->first();

        expect($newBalance)->not->toBeNull();
        expect((float) $newBalance->brought_forward)->toBe(10.0); // max_carry_over_days
        expect($newBalance->carry_over_expiry_date)->not->toBeNull();
    });

    it('forfeits balance when carry-over not allowed', function () {
        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $leaveType = LeaveType::factory()->create([
            'allow_carry_over' => false,
        ]);

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year - 1,
            'earned' => 15,
            'used' => 5,
            'pending' => 0,
            'adjustments' => 0,
            'brought_forward' => 0,
            'expired' => 0,
        ]);

        $result = $this->service->processYearEnd(now()->year - 1);

        expect($result['forfeited'])->toBeGreaterThan(0);

        // Check new year balance has zero carry-over
        $newBalance = LeaveBalance::query()
            ->forEmployee($employee)
            ->forLeaveType($leaveType)
            ->forYear(now()->year)
            ->first();

        expect($newBalance)->not->toBeNull();
        expect((float) $newBalance->brought_forward)->toBe(0.0);
    });
});

describe('expireCarriedOverBalances', function () {
    it('expires carry-over balances past expiry date', function () {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'brought_forward' => 5,
            'carry_over_expiry_date' => now()->subDay()->toDateString(),
            'expired' => 0,
        ]);

        $count = $this->service->expireCarriedOverBalances();

        expect($count)->toBe(1);

        $balance->refresh();
        expect((float) $balance->expired)->toBe(5.0);
    });

    it('does not expire balances with future expiry date', function () {
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'brought_forward' => 5,
            'carry_over_expiry_date' => now()->addMonth()->toDateString(),
            'expired' => 0,
        ]);

        $count = $this->service->expireCarriedOverBalances();

        expect($count)->toBe(0);

        $balance->refresh();
        expect((float) $balance->expired)->toBe(0.0);
    });
});
