<?php

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApprovalChainResolver;
use App\Services\LeaveApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function bindTenantContextForLeaveApp(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForLeaveApp(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('LeaveApplication Model', function () {
    it('generates a unique reference number', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $refNumber = LeaveApplication::generateReferenceNumber();

        expect($refNumber)->toStartWith('LV-'.now()->year.'-');
    });

    it('calculates total days correctly', function () {
        // Same day
        expect(LeaveApplication::calculateTotalDays('2026-02-01', '2026-02-01'))->toBe(1.0);

        // Multiple days
        expect(LeaveApplication::calculateTotalDays('2026-02-01', '2026-02-03'))->toBe(3.0);

        // Half day start
        expect(LeaveApplication::calculateTotalDays('2026-02-01', '2026-02-03', true, false))->toBe(2.5);

        // Half day end
        expect(LeaveApplication::calculateTotalDays('2026-02-01', '2026-02-03', false, true))->toBe(2.5);

        // Both half days
        expect(LeaveApplication::calculateTotalDays('2026-02-01', '2026-02-03', true, true))->toBe(2.0);
    });

    it('has correct status transitions', function () {
        expect(LeaveApplicationStatus::Draft->canTransitionTo(LeaveApplicationStatus::Pending))->toBeTrue();
        expect(LeaveApplicationStatus::Draft->canTransitionTo(LeaveApplicationStatus::Approved))->toBeFalse();
        expect(LeaveApplicationStatus::Pending->canTransitionTo(LeaveApplicationStatus::Approved))->toBeTrue();
        expect(LeaveApplicationStatus::Pending->canTransitionTo(LeaveApplicationStatus::Rejected))->toBeTrue();
        expect(LeaveApplicationStatus::Approved->isFinal())->toBeTrue();
        expect(LeaveApplicationStatus::Rejected->isFinal())->toBeTrue();
    });

    it('identifies which statuses reserve balance', function () {
        expect(LeaveApplicationStatus::Draft->reservesBalance())->toBeFalse();
        expect(LeaveApplicationStatus::Pending->reservesBalance())->toBeTrue();
        expect(LeaveApplicationStatus::Approved->reservesBalance())->toBeFalse();
    });
});

describe('LeaveApplication Creation', function () {
    it('creates a leave application as draft', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $leaveType = LeaveType::factory()->create();

        $application = LeaveApplication::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(9)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Family vacation',
        ]);

        expect($application)->toBeInstanceOf(LeaveApplication::class);
        expect($application->status)->toBe(LeaveApplicationStatus::Draft);
        expect($application->reference_number)->toStartWith('LV-');
        expect($application->employee_id)->toBe($employee->id);
    });

    it('detects overlapping leave applications', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        // Create an approved application
        LeaveApplication::factory()->approved()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-02-10',
            'end_date' => '2026-02-15',
        ]);

        // Check for overlap
        $hasOverlap = LeaveApplication::overlapping(
            $employee->id,
            '2026-02-12',
            '2026-02-17'
        )->exists();

        expect($hasOverlap)->toBeTrue();

        // Check for no overlap
        $noOverlap = LeaveApplication::overlapping(
            $employee->id,
            '2026-02-20',
            '2026-02-25'
        )->exists();

        expect($noOverlap)->toBeFalse();
    });
});

describe('ApprovalChainResolver', function () {
    it('resolves a two-step chain: department head then admin manager', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $departmentHead = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $adminManager = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);

        $department = \App\Models\Department::factory()->create([
            'department_head_id' => $departmentHead->id,
        ]);
        $departmentHead->update(['department_id' => $department->id]);

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $resolver = new ApprovalChainResolver;
        $chain = $resolver->resolveChain($employee, 2);

        expect($chain)->toHaveCount(2);
        expect($chain->first()['employee']->id)->toBe($departmentHead->id);
        expect($chain->first()['type'])->toBe('department_head');
        expect($chain->last()['employee']->id)->toBe($adminManager->id);
        expect($chain->last()['type'])->toBe('admin_manager');
    });

    it('skips an inactive department head', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $inactiveHead = Employee::factory()->create(['employment_status' => EmploymentStatus::Resigned]);
        $adminManager = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);

        $department = \App\Models\Department::factory()->create([
            'department_head_id' => $inactiveHead->id,
        ]);

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $resolver = new ApprovalChainResolver;
        $chain = $resolver->resolveChain($employee, 2);

        expect($chain)->toHaveCount(1);
        expect($chain->first()['employee']->id)->toBe($adminManager->id);
        expect($chain->first()['type'])->toBe('admin_manager');
    });

    it('collapses to a single step when department head is also the admin manager', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $head = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);

        $department = \App\Models\Department::factory()->create([
            'department_head_id' => $head->id,
        ]);
        $head->update(['department_id' => $department->id]);

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $resolver = new ApprovalChainResolver;
        $chain = $resolver->resolveChain($employee, 2);

        expect($chain)->toHaveCount(1);
        expect($chain->first()['employee']->id)->toBe($head->id);
    });

    it('skips the department head when applicant is the head, and routes to admin manager', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $adminManager = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);

        $applicant = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $department = \App\Models\Department::factory()->create([
            'department_head_id' => $applicant->id,
        ]);
        $applicant->update(['department_id' => $department->id]);

        $resolver = new ApprovalChainResolver;
        $chain = $resolver->resolveChain($applicant, 2);

        expect($chain)->toHaveCount(1);
        expect($chain->first()['employee']->id)->toBe($adminManager->id);
        expect($chain->first()['type'])->toBe('admin_manager');
    });

    it('prevents self-approval', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $resolver = new ApprovalChainResolver;

        expect($resolver->canApprove($employee, $employee))->toBeFalse();
    });
});

describe('LeaveApplicationService', function () {
    it('submits a leave application and reserves balance', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $departmentHead = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $headUser = createTenantUserForLeaveApp($tenant, TenantUserRole::Admin);
        $departmentHead->update(['user_id' => $headUser->id]);

        $department = \App\Models\Department::factory()->create([
            'department_head_id' => $departmentHead->id,
        ]);
        $departmentHead->update(['department_id' => $department->id]);

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 15,
            'used' => 0,
            'pending' => 0,
        ]);

        $application = LeaveApplication::factory()->draft()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(9)->format('Y-m-d'),
            'total_days' => 3,
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);
        $submitted = $service->submit($application);

        expect($submitted->status)->toBe(LeaveApplicationStatus::Pending);
        expect($submitted->submitted_at)->not->toBeNull();
        expect($submitted->approvals)->toHaveCount(1);
        expect($submitted->current_approval_level)->toBe(1);

        // Check balance was reserved
        $balance->refresh();
        expect((float) $balance->pending)->toBe(3.0);
    });

    it('rejects submission when balance is insufficient', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $departmentHead = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $department = \App\Models\Department::factory()->create([
            'department_head_id' => $departmentHead->id,
        ]);
        $departmentHead->update(['department_id' => $department->id]);

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $leaveType = LeaveType::factory()->create();
        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 2,
            'used' => 0,
            'pending' => 0,
        ]);

        $application = LeaveApplication::factory()->draft()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(12)->format('Y-m-d'),
            'total_days' => 6,
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);

        expect(fn () => $service->submit($application))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });

    it('cancels a pending application and releases balance', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $leaveType = LeaveType::factory()->create();

        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 15,
            'used' => 0,
            'pending' => 3, // Already has pending balance
        ]);

        $application = LeaveApplication::factory()->pending()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => $balance->id,
            'total_days' => 3,
        ]);

        // Create an approval record
        LeaveApplicationApproval::factory()->create([
            'leave_application_id' => $application->id,
            'approval_level' => 1,
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);
        $cancelled = $service->cancel($application, 'Plans changed');

        expect($cancelled->status)->toBe(LeaveApplicationStatus::Cancelled);
        expect($cancelled->cancellation_reason)->toBe('Plans changed');

        // Check balance was released
        $balance->refresh();
        expect((float) $balance->pending)->toBe(0.0);
    });
});

describe('LeaveApplication Store Validation', function () {
    it('validates required fields when creating application', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $rules = (new \App\Http\Requests\StoreLeaveApplicationRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('employee_id'))->toBeTrue();
        expect($validator->errors()->has('leave_type_id'))->toBeTrue();
        expect($validator->errors()->has('start_date'))->toBeTrue();
        expect($validator->errors()->has('end_date'))->toBeTrue();
        expect($validator->errors()->has('reason'))->toBeTrue();
    });

    it('validates date range is correct', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        $rules = (new \App\Http\Requests\StoreLeaveApplicationRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'), // End before start
            'reason' => 'Test reason',
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('end_date'))->toBeTrue();
    });

    it('retrieves employee leave applications via service', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $user = createTenantUserForLeaveApp($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $leaveType = LeaveType::factory()->create();

        // Create applications for this employee
        LeaveApplication::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'reference_number' => 'LV-2026-TEST1',
        ]);
        LeaveApplication::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'reference_number' => 'LV-2026-TEST2',
        ]);
        LeaveApplication::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'reference_number' => 'LV-2026-TEST3',
        ]);

        // Query directly
        $applications = LeaveApplication::forEmployee($employee->id)->get();

        expect($applications->count())->toBe(3);
    });

    it('submits draft application successfully via service', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $headUser = createTenantUserForLeaveApp($tenant, TenantUserRole::Admin);
        $departmentHead = Employee::factory()->create([
            'user_id' => $headUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $department = \App\Models\Department::factory()->create([
            'department_head_id' => $departmentHead->id,
        ]);
        $departmentHead->update(['department_id' => $department->id]);

        $user = createTenantUserForLeaveApp($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $leaveType = LeaveType::factory()->create();
        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 15,
        ]);

        $application = LeaveApplication::factory()->draft()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(9)->format('Y-m-d'),
            'total_days' => 3,
            'reference_number' => 'LV-2026-SUBMIT1',
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);
        $submitted = $service->submit($application);

        expect($submitted->status)->toBe(LeaveApplicationStatus::Pending);
        expect($submitted->submitted_at)->not->toBeNull();
    });

    it('cancels pending application successfully via service', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $user = createTenantUserForLeaveApp($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $leaveType = LeaveType::factory()->create();

        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'pending' => 3,
        ]);

        $application = LeaveApplication::factory()->pending()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => $balance->id,
            'total_days' => 3,
            'reference_number' => 'LV-2026-CANCEL1',
        ]);

        LeaveApplicationApproval::factory()->create([
            'leave_application_id' => $application->id,
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);
        $cancelled = $service->cancel($application, 'Plans changed');

        expect($cancelled->status)->toBe(LeaveApplicationStatus::Cancelled);
        expect($cancelled->cancellation_reason)->toBe('Plans changed');

        // Check balance was released
        $balance->refresh();
        expect((float) $balance->pending)->toBe(0.0);
    });
});

describe('LeaveApplication Supporting Document', function () {
    it('rejects attachments with disallowed mime types', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $leaveType = LeaveType::factory()->create();

        $rules = (new \App\Http\Requests\StoreLeaveApplicationRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(3)->format('Y-m-d'),
            'end_date' => now()->addDays(4)->format('Y-m-d'),
            'reason' => 'Test',
            'attachment' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('attachment'))->toBeTrue();
    });

    it('persists attachment metadata on the leave application model', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $leaveType = LeaveType::factory()->create();

        $application = LeaveApplication::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'attachment_path' => "leave-applications/{$employee->id}/medical-cert.pdf",
            'attachment_name' => 'medical-cert.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => 12345,
        ]);

        $application->refresh();

        expect($application->attachment_path)->toBe("leave-applications/{$employee->id}/medical-cert.pdf");
        expect($application->attachment_name)->toBe('medical-cert.pdf');
        expect($application->attachment_mime)->toBe('application/pdf');
        expect((int) $application->attachment_size)->toBe(12345);
    });

    it('stores an uploaded attachment to disk via the controller', function () {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $file = UploadedFile::fake()->create('medical-cert.pdf', 200, 'application/pdf');
        $request = \Illuminate\Http\Request::create('/api/leave-applications', 'POST', [], [], ['attachment' => $file]);

        $controller = new \App\Http\Controllers\Api\LeaveApplicationController(
            new \App\Services\LeaveApplicationService(new \App\Services\ApprovalChainResolver)
        );

        $reflection = new ReflectionMethod($controller, 'storeAttachment');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($controller, $request, $employee->id);

        expect($result['attachment_name'])->toBe('medical-cert.pdf');
        expect($result['attachment_mime'])->toBe('application/pdf');
        expect($result['attachment_path'])->toStartWith("leave-applications/{$employee->id}/");
        Storage::disk('local')->assertExists($result['attachment_path']);
    });
});

describe('Leave Approval Settings', function () {
    it('sets a single Admin Manager and clears any previous one', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $adminUser = createTenantUserForLeaveApp($tenant, TenantUserRole::Admin);

        $previous = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);
        $next = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        \Illuminate\Support\Facades\Gate::define('can-manage-organization', fn () => true);
        $this->actingAs($adminUser);

        $controller = new \App\Http\Controllers\Api\LeaveSettingsController;

        $request = \Illuminate\Http\Request::create('/api/organization/leave-settings/admin-manager', 'POST', [
            'employee_id' => $next->id,
        ]);
        $request->setUserResolver(fn () => $adminUser);

        $response = $controller->setAdminManager($request);
        $payload = $response->getData(true);

        expect($payload['admin_manager']['id'])->toBe($next->id);
        expect($previous->fresh()->is_leave_admin_manager)->toBeFalse();
        expect($next->fresh()->is_leave_admin_manager)->toBeTrue();
    });

    it('clears the Admin Manager when employee_id is null', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $adminUser = createTenantUserForLeaveApp($tenant, TenantUserRole::Admin);

        $existing = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);

        \Illuminate\Support\Facades\Gate::define('can-manage-organization', fn () => true);
        $this->actingAs($adminUser);

        $controller = new \App\Http\Controllers\Api\LeaveSettingsController;

        $request = \Illuminate\Http\Request::create('/api/organization/leave-settings/admin-manager', 'POST', [
            'employee_id' => null,
        ]);
        $request->setUserResolver(fn () => $adminUser);

        $response = $controller->setAdminManager($request);
        $payload = $response->getData(true);

        expect($payload['admin_manager'])->toBeNull();
        expect($existing->fresh()->is_leave_admin_manager)->toBeFalse();
    });

    it('rejects a designation for an inactive employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveApp($tenant);

        $adminUser = createTenantUserForLeaveApp($tenant, TenantUserRole::Admin);

        $resigned = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Resigned,
        ]);

        \Illuminate\Support\Facades\Gate::define('can-manage-organization', fn () => true);
        $this->actingAs($adminUser);

        $controller = new \App\Http\Controllers\Api\LeaveSettingsController;

        $request = \Illuminate\Http\Request::create('/api/organization/leave-settings/admin-manager', 'POST', [
            'employee_id' => $resigned->id,
        ]);
        $request->setUserResolver(fn () => $adminUser);

        expect(fn () => $controller->setAdminManager($request))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});
