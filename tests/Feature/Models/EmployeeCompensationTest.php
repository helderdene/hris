<?php

use App\Enums\BankAccountType;
use App\Enums\PayType;
use App\Models\CompensationHistory;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('EmployeeCompensation Model', function () {
    it('can be created with all required fields', function () {
        $employee = Employee::factory()->create();

        $compensation = EmployeeCompensation::factory()->create([
            'employee_id' => $employee->id,
            'basic_pay' => 50000.00,
            'currency' => 'PHP',
            'pay_type' => PayType::Monthly,
            'effective_date' => now()->toDateString(),
        ]);

        expect($compensation)->toBeInstanceOf(EmployeeCompensation::class);
        expect($compensation->employee_id)->toBe($employee->id);
        expect($compensation->basic_pay)->toBe('50000.00');
        expect($compensation->currency)->toBe('PHP');
        expect($compensation->pay_type)->toBe(PayType::Monthly);
        expect($compensation->effective_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
    });

    it('has one-to-one relationship with Employee', function () {
        $employee = Employee::factory()->create();

        $compensation = EmployeeCompensation::factory()->create([
            'employee_id' => $employee->id,
        ]);

        expect($compensation->employee)->toBeInstanceOf(Employee::class);
        expect($compensation->employee->id)->toBe($employee->id);
        expect($employee->compensation)->toBeInstanceOf(EmployeeCompensation::class);
        expect($employee->compensation->id)->toBe($compensation->id);
    });

    it('stores bank account details with nullable fields', function () {
        $employee = Employee::factory()->create();

        $compensation = EmployeeCompensation::factory()->create([
            'employee_id' => $employee->id,
            'bank_name' => 'BDO',
            'account_name' => 'John Doe',
            'account_number' => '1234567890',
            'account_type' => BankAccountType::Savings,
        ]);

        expect($compensation->bank_name)->toBe('BDO');
        expect($compensation->account_name)->toBe('John Doe');
        expect($compensation->account_number)->toBe('1234567890');
        expect($compensation->account_type)->toBe(BankAccountType::Savings);
    });

    it('allows null bank account fields for initial setup', function () {
        $employee = Employee::factory()->create();

        $compensation = EmployeeCompensation::factory()->create([
            'employee_id' => $employee->id,
            'bank_name' => null,
            'account_name' => null,
            'account_number' => null,
            'account_type' => null,
        ]);

        expect($compensation->bank_name)->toBeNull();
        expect($compensation->account_name)->toBeNull();
        expect($compensation->account_number)->toBeNull();
        expect($compensation->account_type)->toBeNull();
    });
});

describe('CompensationHistory Model', function () {
    it('can be created with ended_at pattern for tracking changes', function () {
        $employee = Employee::factory()->create();

        $history = CompensationHistory::factory()->create([
            'employee_id' => $employee->id,
            'previous_basic_pay' => null,
            'new_basic_pay' => 50000.00,
            'previous_pay_type' => null,
            'new_pay_type' => PayType::Monthly,
            'effective_date' => now()->toDateString(),
            'changed_by' => 1,
            'remarks' => 'Initial compensation',
            'ended_at' => null,
        ]);

        expect($history)->toBeInstanceOf(CompensationHistory::class);
        expect($history->employee_id)->toBe($employee->id);
        expect($history->new_basic_pay)->toBe('50000.00');
        expect($history->new_pay_type)->toBe(PayType::Monthly);
        expect($history->ended_at)->toBeNull();
    });

    it('returns only current records using scopeCurrent', function () {
        $employee = Employee::factory()->create();

        // Create past history record (ended)
        CompensationHistory::factory()->create([
            'employee_id' => $employee->id,
            'previous_basic_pay' => null,
            'new_basic_pay' => 40000.00,
            'previous_pay_type' => null,
            'new_pay_type' => PayType::Monthly,
            'effective_date' => now()->subYear(),
            'ended_at' => now()->subMonth(),
        ]);

        // Create current history record (not ended)
        $currentHistory = CompensationHistory::factory()->create([
            'employee_id' => $employee->id,
            'previous_basic_pay' => 40000.00,
            'new_basic_pay' => 50000.00,
            'previous_pay_type' => PayType::Monthly,
            'new_pay_type' => PayType::Monthly,
            'effective_date' => now()->subMonth(),
            'ended_at' => null,
        ]);

        $currentRecords = CompensationHistory::current()->get();

        expect($currentRecords)->toHaveCount(1);
        expect($currentRecords->first()->id)->toBe($currentHistory->id);
    });

    it('filters by employee using scopeForEmployee', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        $history1 = CompensationHistory::factory()->create([
            'employee_id' => $employee1->id,
        ]);

        $history2 = CompensationHistory::factory()->create([
            'employee_id' => $employee2->id,
        ]);

        $employee1Records = CompensationHistory::forEmployee($employee1->id)->get();
        $employee2Records = CompensationHistory::forEmployee($employee2->id)->get();

        expect($employee1Records)->toHaveCount(1);
        expect($employee1Records->first()->id)->toBe($history1->id);

        expect($employee2Records)->toHaveCount(1);
        expect($employee2Records->first()->id)->toBe($history2->id);
    });

    it('allows access to compensation history from employee model', function () {
        $employee = Employee::factory()->create();

        CompensationHistory::factory()->create([
            'employee_id' => $employee->id,
            'ended_at' => now()->subMonth(),
        ]);

        CompensationHistory::factory()->create([
            'employee_id' => $employee->id,
            'ended_at' => null,
        ]);

        expect($employee->compensationHistory)->toHaveCount(2);
    });
});

describe('PayType Enum', function () {
    it('has all 4 pay type cases', function () {
        $cases = PayType::cases();

        expect($cases)->toHaveCount(4);
        expect(PayType::Monthly->value)->toBe('monthly');
        expect(PayType::SemiMonthly->value)->toBe('semi_monthly');
        expect(PayType::Weekly->value)->toBe('weekly');
        expect(PayType::Daily->value)->toBe('daily');
    });

    it('provides human-readable labels with Semi-Monthly formatting', function () {
        expect(PayType::Monthly->label())->toBe('Monthly');
        expect(PayType::SemiMonthly->label())->toBe('Semi-Monthly');
        expect(PayType::Weekly->label())->toBe('Weekly');
        expect(PayType::Daily->label())->toBe('Daily');
    });

    it('returns all pay type values as array', function () {
        $values = PayType::values();

        expect($values)->toBeArray();
        expect($values)->toContain('monthly');
        expect($values)->toContain('semi_monthly');
        expect($values)->toContain('weekly');
        expect($values)->toContain('daily');
    });
});

describe('BankAccountType Enum', function () {
    it('has all 2 bank account type cases', function () {
        $cases = BankAccountType::cases();

        expect($cases)->toHaveCount(2);
        expect(BankAccountType::Savings->value)->toBe('savings');
        expect(BankAccountType::Checking->value)->toBe('checking');
    });

    it('provides human-readable labels', function () {
        expect(BankAccountType::Savings->label())->toBe('Savings');
        expect(BankAccountType::Checking->label())->toBe('Checking');
    });

    it('returns all bank account type values as array', function () {
        $values = BankAccountType::values();

        expect($values)->toBeArray();
        expect($values)->toContain('savings');
        expect($values)->toContain('checking');
    });
});
