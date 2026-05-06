<?php

use App\Enums\EmploymentStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanType;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\LoanApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LoanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantForLoanApp(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForLoanApp(Tenant $tenant, TenantUserRole $role): User
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

describe('LoanApplication Model', function () {
    it('generates a unique reference number', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $refNumber = LoanApplication::generateReferenceNumber();

        expect($refNumber)->toStartWith('LA-'.now()->year.'-');
    });

    it('generates sequential reference numbers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $app1 = LoanApplication::factory()->create();
        $app2 = LoanApplication::factory()->create();

        $num1 = (int) substr($app1->reference_number, -5);
        $num2 = (int) substr($app2->reference_number, -5);

        expect($num2)->toBe($num1 + 1);
    });

    it('creates a loan application as draft', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $application = LoanApplication::factory()->create([
            'employee_id' => $employee->id,
            'loan_type' => LoanType::SssSalary,
            'amount_requested' => 50000,
            'term_months' => 24,
            'purpose' => 'Personal use',
        ]);

        expect($application)->toBeInstanceOf(LoanApplication::class);
        expect($application->status)->toBe(LoanApplicationStatus::Draft);
        expect($application->reference_number)->toStartWith('LA-');
        expect($application->employee_id)->toBe($employee->id);
        expect((float) $application->amount_requested)->toBe(50000.0);
    });

    it('has correct relationships', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $application = LoanApplication::factory()->forEmployee($employee)->create();

        expect($application->employee->id)->toBe($employee->id);
    });

    it('has can_be_edited attribute', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $draft = LoanApplication::factory()->draft()->create();
        $pending = LoanApplication::factory()->pending()->create();

        expect($draft->can_be_edited)->toBeTrue();
        expect($pending->can_be_edited)->toBeFalse();
    });

    it('has can_be_cancelled attribute', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $draft = LoanApplication::factory()->draft()->create();
        $pending = LoanApplication::factory()->pending()->create();
        $approved = LoanApplication::factory()->approved()->create();

        expect($draft->can_be_cancelled)->toBeTrue();
        expect($pending->can_be_cancelled)->toBeTrue();
        expect($approved->can_be_cancelled)->toBeFalse();
    });

    it('scopes by employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $employee = Employee::factory()->create();
        LoanApplication::factory()->count(3)->forEmployee($employee)->create();
        LoanApplication::factory()->count(2)->create(); // different employees

        $count = LoanApplication::query()->forEmployee($employee)->count();

        expect($count)->toBe(3);
    });

    it('scopes by pending status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        LoanApplication::factory()->pending()->count(2)->create();
        LoanApplication::factory()->draft()->count(3)->create();

        $count = LoanApplication::query()->pending()->count();

        expect($count)->toBe(2);
    });
});

describe('LoanApplication Workflow', function () {
    it('submits a draft application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $user = createTenantUserForLoanApp($tenant, TenantUserRole::Employee);
        $this->actingAs($user);

        $application = LoanApplication::factory()->draft()->create();

        $service = app(LoanApplicationService::class);
        $result = $service->submit($application);

        expect($result->status)->toBe(LoanApplicationStatus::Pending);
        expect($result->submitted_at)->not->toBeNull();
    });

    it('cannot submit a non-draft application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $application = LoanApplication::factory()->pending()->create();

        $service = app(LoanApplicationService::class);

        expect(fn () => $service->submit($application))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('cancels a pending application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $application = LoanApplication::factory()->pending()->create();

        $service = app(LoanApplicationService::class);
        $result = $service->cancel($application, 'Changed my mind');

        expect($result->status)->toBe(LoanApplicationStatus::Cancelled);
        expect($result->cancellation_reason)->toBe('Changed my mind');
    });

    it('cancels a draft application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $application = LoanApplication::factory()->draft()->create();

        $service = app(LoanApplicationService::class);
        $result = $service->cancel($application, 'No longer needed');

        expect($result->status)->toBe(LoanApplicationStatus::Cancelled);
    });

    it('cannot cancel an approved application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $application = LoanApplication::factory()->approved()->create();

        $service = app(LoanApplicationService::class);

        expect(fn () => $service->cancel($application))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('LoanApplication new submission fields', function () {
    it('rejects missing deduction_schedule, urgency_level, and purpose', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $employee = Employee::factory()->create();

        $rules = (new \App\Http\Requests\StoreLoanApplicationRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([
            'employee_id' => $employee->id,
            'loan_type' => \App\Enums\LoanType::CompanyCashAdvance->value,
            'amount_requested' => 5000,
            'term_months' => 12,
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('deduction_schedule'))->toBeTrue();
        expect($validator->errors()->has('urgency_level'))->toBeTrue();
        expect($validator->errors()->has('purpose'))->toBeTrue();
    });

    it('rejects term_months that is not in the allowed presets', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $employee = Employee::factory()->create();

        $rules = (new \App\Http\Requests\StoreLoanApplicationRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([
            'employee_id' => $employee->id,
            'loan_type' => \App\Enums\LoanType::CompanyCashAdvance->value,
            'amount_requested' => 5000,
            'term_months' => 9,
            'deduction_schedule' => \App\Enums\LoanDeductionSchedule::Monthly->value,
            'urgency_level' => 3,
            'purpose' => 'Family expense',
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('term_months'))->toBeTrue();
    });

    it('accepts a complete submission and persists the new fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $employee = Employee::factory()->create();

        $rules = (new \App\Http\Requests\StoreLoanApplicationRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([
            'employee_id' => $employee->id,
            'loan_type' => \App\Enums\LoanType::CompanyCashAdvance->value,
            'amount_requested' => 10000,
            'term_months' => 6,
            'deduction_schedule' => \App\Enums\LoanDeductionSchedule::SemiMonthly->value,
            'urgency_level' => 4,
            'purpose' => 'Medical expenses',
        ], $rules);

        expect($validator->passes())->toBeTrue();

        $application = LoanApplication::create([
            'employee_id' => $employee->id,
            'loan_type' => \App\Enums\LoanType::CompanyCashAdvance->value,
            'amount_requested' => 10000,
            'term_months' => 6,
            'deduction_schedule' => \App\Enums\LoanDeductionSchedule::SemiMonthly->value,
            'urgency_level' => 4,
            'purpose' => 'Medical expenses',
        ]);

        $application->refresh();

        expect($application->deduction_schedule)->toBe(\App\Enums\LoanDeductionSchedule::SemiMonthly);
        expect($application->urgency_level)->toBe(4);
        expect($application->term_months)->toBe(6);
    });

    it('rejects urgency_level outside 1-5', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForLoanApp($tenant);

        $employee = Employee::factory()->create();

        $rules = (new \App\Http\Requests\StoreLoanApplicationRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([
            'employee_id' => $employee->id,
            'loan_type' => \App\Enums\LoanType::CompanyCashAdvance->value,
            'amount_requested' => 5000,
            'term_months' => 12,
            'deduction_schedule' => \App\Enums\LoanDeductionSchedule::Monthly->value,
            'urgency_level' => 7,
            'purpose' => 'Test',
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('urgency_level'))->toBeTrue();
    });
});
