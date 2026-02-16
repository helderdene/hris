<?php

use App\Enums\EmploymentStatus;
use App\Enums\OvertimeRequestStatus;
use App\Enums\TenantUserRole;
use App\Http\Requests\StoreOvertimeRequestRequest;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApprovalChainResolver;
use App\Services\OvertimeRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function bindTenantContextForOTApi(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForOTApi(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
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

describe('OvertimeRequest CRUD Operations', function () {
    it('creates a draft overtime request via factory', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOTApi($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $request = OvertimeRequest::factory()->draft()->create([
            'employee_id' => $employee->id,
            'expected_minutes' => 120,
            'overtime_type' => 'regular',
            'reason' => 'Project deadline approaching',
        ]);

        expect($request->status)->toBe(OvertimeRequestStatus::Draft);
        expect($request->expected_minutes)->toBe(120);
        expect($request->reference_number)->toStartWith('OT-');
        expect($request->employee_id)->toBe($employee->id);
    });

    it('can update a draft overtime request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOTApi($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $request = OvertimeRequest::factory()->draft()->create([
            'employee_id' => $employee->id,
            'expected_minutes' => 120,
        ]);

        $request->update([
            'expected_minutes' => 180,
            'reason' => 'Updated reason',
        ]);

        $request->refresh();
        expect($request->expected_minutes)->toBe(180);
        expect($request->reason)->toBe('Updated reason');
    });

    it('can soft delete a draft overtime request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOTApi($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $request = OvertimeRequest::factory()->draft()->create([
            'employee_id' => $employee->id,
        ]);

        expect(OvertimeRequest::count())->toBe(1);

        $request->delete();

        expect(OvertimeRequest::count())->toBe(0);
    });

    it('queries overtime requests by status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOTApi($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        OvertimeRequest::factory()->draft()->create(['employee_id' => $employee->id]);
        OvertimeRequest::factory()->pending()->create(['employee_id' => $employee->id]);
        OvertimeRequest::factory()->approved()->create(['employee_id' => $employee->id]);

        expect(OvertimeRequest::pending()->count())->toBe(1);
        expect(OvertimeRequest::approved()->count())->toBe(1);
        expect(OvertimeRequest::forEmployee($employee->id)->count())->toBe(3);
    });

    it('queries overtime requests by date', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOTApi($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $targetDate = now()->addDay();
        OvertimeRequest::factory()->create([
            'employee_id' => $employee->id,
            'overtime_date' => $targetDate,
        ]);
        OvertimeRequest::factory()->create([
            'employee_id' => $employee->id,
            'overtime_date' => now()->addDays(5),
        ]);

        expect(OvertimeRequest::forDate($targetDate)->count())->toBe(1);
    });
});

describe('OvertimeRequest Validation', function () {
    it('validates store request required fields', function () {
        $rules = (new StoreOvertimeRequestRequest)->rules();
        $validator = Validator::make([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('employee_id'))->toBeTrue();
        expect($validator->errors()->has('overtime_date'))->toBeTrue();
        expect($validator->errors()->has('expected_minutes'))->toBeTrue();
        expect($validator->errors()->has('overtime_type'))->toBeTrue();
        expect($validator->errors()->has('reason'))->toBeTrue();
    });

    it('validates expected minutes range', function () {
        $rules = (new StoreOvertimeRequestRequest)->rules();

        // Too low
        $validator = Validator::make(['expected_minutes' => 10], $rules);
        expect($validator->errors()->has('expected_minutes'))->toBeTrue();

        // Too high
        $validator = Validator::make(['expected_minutes' => 800], $rules);
        expect($validator->errors()->has('expected_minutes'))->toBeTrue();

        // Valid
        $validator = Validator::make(['expected_minutes' => 120], $rules);
        expect($validator->errors()->has('expected_minutes'))->toBeFalse();
    });

    it('validates overtime type enum values', function () {
        $rules = (new StoreOvertimeRequestRequest)->rules();

        $validator = Validator::make(['overtime_type' => 'invalid_type'], $rules);
        expect($validator->errors()->has('overtime_type'))->toBeTrue();

        $validator = Validator::make(['overtime_type' => 'regular'], $rules);
        expect($validator->errors()->has('overtime_type'))->toBeFalse();
    });
});

describe('OvertimeRequest Workflow via Service', function () {
    it('submits and then approves via service', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOTApi($tenant);

        $employeeUser = createTenantUserForOTApi($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approverUser = createTenantUserForOTApi($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);
        $employee->update(['supervisor_id' => $approver->id]);

        $request = OvertimeRequest::factory()->draft()->create([
            'employee_id' => $employee->id,
        ]);

        $service = new OvertimeRequestService(new ApprovalChainResolver);

        // Submit
        $submitted = $service->submit($request);
        expect($submitted->status)->toBe(OvertimeRequestStatus::Pending);
        expect($submitted->submitted_at)->not->toBeNull();
        expect($submitted->approvals()->count())->toBeGreaterThan(0);

        // Approve
        $approved = $service->approve($submitted, $approver, 'Looks good');
        expect($approved->status)->toBe(OvertimeRequestStatus::Approved);
        expect($approved->approved_at)->not->toBeNull();
    });

    it('submits and then rejects via service', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOTApi($tenant);

        $employeeUser = createTenantUserForOTApi($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approverUser = createTenantUserForOTApi($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);
        $employee->update(['supervisor_id' => $approver->id]);

        $request = OvertimeRequest::factory()->draft()->create([
            'employee_id' => $employee->id,
        ]);

        $service = new OvertimeRequestService(new ApprovalChainResolver);

        // Submit
        $submitted = $service->submit($request);

        // Reject
        $rejected = $service->reject($submitted, $approver, 'Not needed');
        expect($rejected->status)->toBe(OvertimeRequestStatus::Rejected);
        expect($rejected->rejected_at)->not->toBeNull();
    });

    it('full lifecycle: create → submit → approve → verify', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOTApi($tenant);

        $employeeUser = createTenantUserForOTApi($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approverUser = createTenantUserForOTApi($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);
        $employee->update(['supervisor_id' => $approver->id]);

        // 1. Create draft
        $request = OvertimeRequest::factory()->draft()->create([
            'employee_id' => $employee->id,
            'overtime_date' => now()->addDay(),
            'expected_minutes' => 120,
            'overtime_type' => 'regular',
            'reason' => 'Project deadline',
        ]);

        expect($request->status)->toBe(OvertimeRequestStatus::Draft);
        expect($request->can_be_edited)->toBeTrue();

        // 2. Submit
        $service = new OvertimeRequestService(new ApprovalChainResolver);
        $submitted = $service->submit($request);
        expect($submitted->status)->toBe(OvertimeRequestStatus::Pending);
        expect($submitted->can_be_edited)->toBeFalse();
        expect($submitted->can_be_cancelled)->toBeTrue();

        // 3. Approve
        $approved = $service->approve($submitted, $approver, 'Approved');
        expect($approved->status)->toBe(OvertimeRequestStatus::Approved);

        // 4. Verify approval records
        $approvalRecord = $approved->approvals()->first();
        expect($approvalRecord->decision->value)->toBe('approved');
        expect($approvalRecord->decided_at)->not->toBeNull();
    });
});
