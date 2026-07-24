<?php

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\JobRequisitionStatus;
use App\Enums\JobRequisitionUrgency;
use App\Enums\LeaveApprovalDecision;
use App\Enums\TenantUserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\JobRequisitionApproved;
use App\Services\JobPostingService;
use App\Services\JobRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function bindTenantContextForJobReq(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForJobReq(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('JobRequisition Model', function () {
    it('generates a unique reference number', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $refNumber = JobRequisition::generateReferenceNumber();

        expect($refNumber)->toStartWith('JR-'.now()->year.'-');
    });

    it('has correct status transitions', function () {
        expect(JobRequisitionStatus::Draft->canTransitionTo(JobRequisitionStatus::Pending))->toBeTrue();
        expect(JobRequisitionStatus::Draft->canTransitionTo(JobRequisitionStatus::Approved))->toBeFalse();
        expect(JobRequisitionStatus::Pending->canTransitionTo(JobRequisitionStatus::Approved))->toBeTrue();
        expect(JobRequisitionStatus::Pending->canTransitionTo(JobRequisitionStatus::Rejected))->toBeTrue();
        expect(JobRequisitionStatus::Approved->isFinal())->toBeTrue();
        expect(JobRequisitionStatus::Rejected->isFinal())->toBeTrue();
        expect(JobRequisitionStatus::Cancelled->isFinal())->toBeTrue();
    });

    it('identifies editable statuses', function () {
        expect(JobRequisitionStatus::Draft->canBeEdited())->toBeTrue();
        expect(JobRequisitionStatus::Pending->canBeEdited())->toBeFalse();
        expect(JobRequisitionStatus::Approved->canBeEdited())->toBeFalse();
    });

    it('identifies cancellable statuses', function () {
        expect(JobRequisitionStatus::Draft->canBeCancelled())->toBeTrue();
        expect(JobRequisitionStatus::Pending->canBeCancelled())->toBeTrue();
        expect(JobRequisitionStatus::Approved->canBeCancelled())->toBeFalse();
        expect(JobRequisitionStatus::Rejected->canBeCancelled())->toBeFalse();
    });
});

describe('JobRequisition Creation', function () {
    it('creates a job requisition as draft', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $position = Position::factory()->create();
        $department = Department::factory()->create();

        $requisition = JobRequisition::factory()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'headcount' => 2,
            'employment_type' => EmploymentType::Regular,
            'justification' => 'Team expansion needed',
        ]);

        expect($requisition)->toBeInstanceOf(JobRequisition::class);
        expect($requisition->status)->toBe(JobRequisitionStatus::Draft);
        expect($requisition->reference_number)->toStartWith('JR-');
        expect($requisition->headcount)->toBe(2);
        expect($requisition->requested_by_employee_id)->toBe($employee->id);
    });

    it('scopes requisitions for a specific employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();
        $position = Position::factory()->create();
        $department = Department::factory()->create();

        JobRequisition::factory()->create([
            'requested_by_employee_id' => $employee->id,
            'position_id' => $position->id,
            'department_id' => $department->id,
            'reference_number' => 'JR-2026-TEST1',
        ]);
        JobRequisition::factory()->create([
            'requested_by_employee_id' => $employee->id,
            'position_id' => $position->id,
            'department_id' => $department->id,
            'reference_number' => 'JR-2026-TEST2',
        ]);
        JobRequisition::factory()->create([
            'requested_by_employee_id' => $otherEmployee->id,
            'position_id' => $position->id,
            'department_id' => $department->id,
            'reference_number' => 'JR-2026-TEST3',
        ]);

        $applications = JobRequisition::forEmployee($employee->id)->get();

        expect($applications->count())->toBe(2);
    });
});

describe('JobRequisitionService', function () {
    it('submits a job requisition for approval', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $supervisor = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);
        $supervisorUser = createTenantUserForJobReq($tenant, TenantUserRole::Admin);
        $supervisor->update(['user_id' => $supervisorUser->id]);

        $employee = Employee::factory()->create([
            'supervisor_id' => $supervisor->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $position = Position::factory()->create();
        $department = Department::factory()->create();

        $requisition = JobRequisition::factory()->draft()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-SUBMIT1',
        ]);

        $service = app(JobRequisitionService::class);
        $submitted = $service->submit($requisition);

        expect($submitted->status)->toBe(JobRequisitionStatus::Pending);
        expect($submitted->submitted_at)->not->toBeNull();
        expect($submitted->approvals)->toHaveCount(1);
        expect($submitted->current_approval_level)->toBe(1);
    });

    it('rejects submission of non-draft requisition', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $position = Position::factory()->create();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create();

        $requisition = JobRequisition::factory()->pending()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-SUBMIT2',
        ]);

        $service = app(JobRequisitionService::class);

        expect(fn () => $service->submit($requisition))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });

    it('approves a job requisition through the chain', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $supervisor = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);
        $supervisorUser = createTenantUserForJobReq($tenant, TenantUserRole::Admin);
        $supervisor->update(['user_id' => $supervisorUser->id]);

        $employee = Employee::factory()->create([
            'supervisor_id' => $supervisor->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $position = Position::factory()->create();
        $department = Department::factory()->create();

        $requisition = JobRequisition::factory()->draft()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-APPROVE1',
        ]);

        $service = app(JobRequisitionService::class);
        $submitted = $service->submit($requisition);
        $approved = $service->approve($submitted, $supervisor, 'Looks good');

        expect($approved->status)->toBe(JobRequisitionStatus::Approved);
        expect($approved->approved_at)->not->toBeNull();
    });

    it('creates a job posting and notifies the requester on final approval', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $supervisor = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);
        $supervisorUser = createTenantUserForJobReq($tenant, TenantUserRole::Admin);
        $supervisor->update(['user_id' => $supervisorUser->id]);

        $requesterUser = createTenantUserForJobReq($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'supervisor_id' => $supervisor->id,
            'employment_status' => EmploymentStatus::Active,
            'user_id' => $requesterUser->id,
        ]);

        $position = Position::factory()->create();
        $department = Department::factory()->create();

        $requisition = JobRequisition::factory()->draft()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-APPROVE2',
        ]);

        $service = app(JobRequisitionService::class);
        $submitted = $service->submit($requisition);
        $approved = $service->approve($submitted, $supervisor, 'Looks good');

        expect($approved->status)->toBe(JobRequisitionStatus::Approved);

        $posting = JobPosting::where('job_requisition_id', $approved->id)->first();
        expect($posting)->not->toBeNull();
        expect($posting->created_by_employee_id)->toBe($employee->id);

        Notification::assertSentTo($requesterUser, JobRequisitionApproved::class);
    });

    it('rolls back the approval and sends no notification when job posting creation fails', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $supervisor = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);
        $supervisorUser = createTenantUserForJobReq($tenant, TenantUserRole::Admin);
        $supervisor->update(['user_id' => $supervisorUser->id]);

        $requesterUser = createTenantUserForJobReq($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'supervisor_id' => $supervisor->id,
            'employment_status' => EmploymentStatus::Active,
            'user_id' => $requesterUser->id,
        ]);

        $position = Position::factory()->create();
        $department = Department::factory()->create();

        $requisition = JobRequisition::factory()->draft()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-APPROVE3',
        ]);

        $submitted = app(JobRequisitionService::class)->submit($requisition);

        $this->mock(JobPostingService::class, function ($mock) {
            $mock->shouldReceive('createFromRequisition')
                ->once()
                ->andThrow(new RuntimeException('Posting creation failed'));
        });

        $service = app(JobRequisitionService::class);

        expect(fn () => $service->approve($submitted, $supervisor, 'Looks good'))
            ->toThrow(RuntimeException::class);

        $fresh = $submitted->fresh();
        expect($fresh->status)->toBe(JobRequisitionStatus::Pending);
        expect($fresh->approvals()->where('decision', LeaveApprovalDecision::Pending)->count())->toBe(1);
        expect(JobPosting::where('job_requisition_id', $submitted->id)->exists())->toBeFalse();

        Notification::assertNotSentTo($requesterUser, JobRequisitionApproved::class);
    });

    it('rejects a job requisition', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $supervisor = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);
        $supervisorUser = createTenantUserForJobReq($tenant, TenantUserRole::Admin);
        $supervisor->update(['user_id' => $supervisorUser->id]);

        $employee = Employee::factory()->create([
            'supervisor_id' => $supervisor->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $position = Position::factory()->create();
        $department = Department::factory()->create();

        $requisition = JobRequisition::factory()->draft()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-REJECT1',
        ]);

        $service = app(JobRequisitionService::class);
        $submitted = $service->submit($requisition);
        $rejected = $service->reject($submitted, $supervisor, 'Budget constraints');

        expect($rejected->status)->toBe(JobRequisitionStatus::Rejected);
        expect($rejected->rejected_at)->not->toBeNull();
    });

    it('cancels a pending job requisition', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $supervisor = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);
        $supervisorUser = createTenantUserForJobReq($tenant, TenantUserRole::Admin);
        $supervisor->update(['user_id' => $supervisorUser->id]);

        $employee = Employee::factory()->create([
            'supervisor_id' => $supervisor->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $position = Position::factory()->create();
        $department = Department::factory()->create();

        $requisition = JobRequisition::factory()->draft()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-CANCEL1',
        ]);

        $service = app(JobRequisitionService::class);
        $submitted = $service->submit($requisition);
        $cancelled = $service->cancel($submitted, 'Plans changed');

        expect($cancelled->status)->toBe(JobRequisitionStatus::Cancelled);
        expect($cancelled->cancellation_reason)->toBe('Plans changed');
        expect($cancelled->approvals()->where('decision', LeaveApprovalDecision::Pending)->count())->toBe(0);
        expect($cancelled->approvals()->where('decision', LeaveApprovalDecision::Skipped)->count())->toBe(1);
    });

    it('skips remaining pending approvals when rejected mid-chain', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $departmentHead = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);
        $adminManager = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);

        $department = Department::factory()->create([
            'department_head_id' => $departmentHead->id,
        ]);

        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'department_id' => $department->id,
        ]);

        $position = Position::factory()->create();

        $requisition = JobRequisition::factory()->draft()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-REJECT2',
        ]);

        $service = app(JobRequisitionService::class);
        $submitted = $service->submit($requisition);

        expect($submitted->total_approval_levels)->toBe(2);

        $rejected = $service->reject($submitted, $departmentHead, 'Budget constraints');

        expect($rejected->status)->toBe(JobRequisitionStatus::Rejected);
        expect($rejected->approvals()->where('decision', LeaveApprovalDecision::Pending)->count())->toBe(0);
        expect($rejected->approvals()->where('decision', LeaveApprovalDecision::Rejected)->count())->toBe(1);
        expect($rejected->approvals()->where('decision', LeaveApprovalDecision::Skipped)->count())->toBe(1);
    });

    it('prevents cancelling an approved requisition', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $position = Position::factory()->create();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create();

        $requisition = JobRequisition::factory()->approved()->create([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'reference_number' => 'JR-2026-CANCEL2',
        ]);

        $service = app(JobRequisitionService::class);

        expect(fn () => $service->cancel($requisition, 'Too late'))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });
});

describe('JobRequisition Store Validation', function () {
    it('validates required fields when creating requisition', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $rules = (new \App\Http\Requests\StoreJobRequisitionRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('position_id'))->toBeTrue();
        expect($validator->errors()->has('department_id'))->toBeTrue();
        expect($validator->errors()->has('requested_by_employee_id'))->toBeTrue();
        expect($validator->errors()->has('headcount'))->toBeTrue();
        expect($validator->errors()->has('employment_type'))->toBeTrue();
        expect($validator->errors()->has('justification'))->toBeTrue();
        expect($validator->errors()->has('urgency'))->toBeTrue();
    });

    it('validates salary range is consistent', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $employee = Employee::factory()->create();
        $position = Position::factory()->create();
        $department = Department::factory()->create();

        $rules = (new \App\Http\Requests\StoreJobRequisitionRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([
            'position_id' => $position->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $employee->id,
            'headcount' => 1,
            'employment_type' => EmploymentType::Regular->value,
            'salary_range_min' => 50000,
            'salary_range_max' => 30000, // less than min
            'justification' => 'Need more staff',
            'urgency' => JobRequisitionUrgency::Normal->value,
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('salary_range_max'))->toBeTrue();
    });
});

describe('JobRequisition Create Page', function () {
    it('renders the create job requisition page', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $user = createTenantUserForJobReq($tenant, TenantUserRole::HrManager);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        Department::factory()->create();
        Position::factory()->create();

        $this->withoutVite();
        $this->actingAs($user);

        $response = $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/create");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Recruitment/Requisitions/Create')
            ->where('employee.id', $employee->id)
            ->has('employees')
            ->has('departments')
            ->has('positions')
            ->has('urgencies')
            ->has('employmentTypes')
        );
    });

    it('renders the create page with a requester list for an admin without an employee record', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        // Admin user with NO linked employee record (mirrors the prod admin@zimo case)
        $user = createTenantUserForJobReq($tenant, TenantUserRole::Admin);
        Employee::factory()->count(3)->create();

        $this->withoutVite();
        $this->actingAs($user);

        $response = $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/create");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Recruitment/Requisitions/Create')
            ->where('employee', null)
            ->has('employees', 3)
        );
    });

    it('does not capture the create path as a requisition id on the show route', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $user = createTenantUserForJobReq($tenant, TenantUserRole::HrManager);
        Employee::factory()->create(['user_id' => $user->id]);

        $this->withoutVite();
        $this->actingAs($user);

        $response = $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/create");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page->component('Recruitment/Requisitions/Create'));
    });
});

describe('JobRequisition Create Authorization', function () {
    it('allows a supervisor to open the create page', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $user = createTenantUserForJobReq($tenant, TenantUserRole::Supervisor);
        Employee::factory()->count(2)->create();

        $this->withoutVite();
        $this->actingAs($user);

        $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/create")
            ->assertSuccessful();
    });

    it('forbids the create page for users without a permitted role', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $user = createTenantUserForJobReq($tenant, TenantUserRole::Employee);

        $this->withoutVite();
        $this->actingAs($user);

        $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/create")
            ->assertForbidden();
    });

    it('forbids storing a requisition for users without a permitted role', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $user = createTenantUserForJobReq($tenant, TenantUserRole::Employee);

        $this->actingAs($user);

        $this->postJson("http://{$tenant->slug}.kasamahr.test/api/job-requisitions", [])
            ->assertForbidden();
    });

    it('forbids update, submit, cancel, and delete for users without a permitted role', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $requisition = JobRequisition::factory()->create([
            'position_id' => Position::factory()->create()->id,
            'department_id' => Department::factory()->create()->id,
            'requested_by_employee_id' => Employee::factory()->create()->id,
        ]);

        $user = createTenantUserForJobReq($tenant, TenantUserRole::Employee);
        $this->actingAs($user);

        $base = "http://{$tenant->slug}.kasamahr.test/api/job-requisitions/{$requisition->id}";

        $this->putJson($base, [])->assertForbidden();
        $this->postJson("{$base}/submit")->assertForbidden();
        $this->postJson("{$base}/cancel")->assertForbidden();
        $this->deleteJson($base)->assertForbidden();
    });

    it('exposes canManage on the index for permitted vs non-permitted roles', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);

        $this->withoutVite();

        $hr = createTenantUserForJobReq($tenant, TenantUserRole::HrManager);
        $this->actingAs($hr);
        $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions")
            ->assertInertia(fn ($page) => $page->where('canManage', true));

        $employee = createTenantUserForJobReq($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);
        $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions")
            ->assertInertia(fn ($page) => $page->where('canManage', false));
    });
});

describe('JobRequisition Show Page Approval Actions', function () {
    function setUpPendingRequisitionForShow(Tenant $tenant): array
    {
        $approverUser = createTenantUserForJobReq($tenant, TenantUserRole::HrManager);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $department = Department::factory()->create(['department_head_id' => $approver->id]);

        $requesterUser = createTenantUserForJobReq($tenant, TenantUserRole::Supervisor);
        $requester = Employee::factory()->create([
            'user_id' => $requesterUser->id,
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $requisition = JobRequisition::factory()->draft()->create([
            'position_id' => Position::factory()->create()->id,
            'department_id' => $department->id,
            'requested_by_employee_id' => $requester->id,
        ]);

        $submitted = app(JobRequisitionService::class)->submit($requisition);

        return [$submitted, $approver, $approverUser, $requesterUser];
    }

    it('exposes can_approve to the current-level pending approver', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);
        $this->withoutVite();

        [$submitted, , $approverUser] = setUpPendingRequisitionForShow($tenant);

        $this->actingAs($approverUser);
        $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/{$submitted->id}")
            ->assertInertia(fn ($page) => $page->where('requisition.can_approve', true));
    });

    it('does not expose can_approve to users who are not the pending approver', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);
        $this->withoutVite();

        [$submitted, , , $requesterUser] = setUpPendingRequisitionForShow($tenant);

        $this->actingAs($requesterUser);
        $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/{$submitted->id}")
            ->assertInertia(fn ($page) => $page->where('requisition.can_approve', false));

        $userWithoutEmployee = createTenantUserForJobReq($tenant, TenantUserRole::Admin);
        $this->actingAs($userWithoutEmployee);
        $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/{$submitted->id}")
            ->assertInertia(fn ($page) => $page->where('requisition.can_approve', false));
    });

    it('does not expose can_approve once the requisition is decided', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobReq($tenant);
        $this->withoutVite();

        [$submitted, $approver, $approverUser] = setUpPendingRequisitionForShow($tenant);

        app(JobRequisitionService::class)->approve($submitted, $approver, 'Approved');

        $this->actingAs($approverUser);
        $this->get("http://{$tenant->slug}.kasamahr.test/recruitment/requisitions/{$submitted->id}")
            ->assertInertia(fn ($page) => $page->where('requisition.can_approve', false));
    });
});
