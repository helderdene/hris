<?php

use App\Enums\EmploymentStatus;
use App\Enums\OvertimeApprovalDecision;
use App\Enums\OvertimeRequestStatus;
use App\Enums\OvertimeType;
use App\Enums\TenantUserRole;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRequestApproval;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OvertimeRequestApproved;
use App\Notifications\OvertimeRequestRejected;
use App\Services\ApprovalChainResolver;
use App\Services\OvertimeRequestService;
use App\Services\Payroll\DtrAggregationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function bindTenantContextForOT(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForOT(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('OvertimeRequestService Approval', function () {
    it('approves an overtime request at single level', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $employeeUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approverUser = createTenantUserForOT($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $request = OvertimeRequest::factory()->pending()->create([
            'employee_id' => $employee->id,
            'current_approval_level' => 1,
            'total_approval_levels' => 1,
        ]);

        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver->id,
            'approver_name' => $approver->full_name,
        ]);

        $service = new OvertimeRequestService(new ApprovalChainResolver);
        $approved = $service->approve($request, $approver, 'Approved for project deadline');

        expect($approved->status)->toBe(OvertimeRequestStatus::Approved);
        expect($approved->approved_at)->not->toBeNull();

        Notification::assertSentTo($employeeUser, OvertimeRequestApproved::class);
    });

    it('advances to next approval level on multi-level approval', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $approver1User = createTenantUserForOT($tenant, TenantUserRole::Admin);
        $approver1 = Employee::factory()->create([
            'user_id' => $approver1User->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approver2User = createTenantUserForOT($tenant, TenantUserRole::Admin);
        $approver2 = Employee::factory()->create([
            'user_id' => $approver2User->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $request = OvertimeRequest::factory()->pending()->create([
            'employee_id' => $employee->id,
            'current_approval_level' => 1,
            'total_approval_levels' => 2,
        ]);

        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver1->id,
        ]);

        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 2,
            'approver_employee_id' => $approver2->id,
        ]);

        $service = new OvertimeRequestService(new ApprovalChainResolver);
        $afterFirstApproval = $service->approve($request, $approver1, 'First approval');

        expect($afterFirstApproval->status)->toBe(OvertimeRequestStatus::Pending);
        expect($afterFirstApproval->current_approval_level)->toBe(2);

        Notification::assertSentTo(
            $approver2User,
            \App\Notifications\OvertimeRequestSubmitted::class
        );
    });

    it('rejects an overtime request', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $employeeUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approverUser = createTenantUserForOT($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $request = OvertimeRequest::factory()->pending()->create([
            'employee_id' => $employee->id,
            'current_approval_level' => 1,
            'total_approval_levels' => 1,
        ]);

        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver->id,
        ]);

        $service = new OvertimeRequestService(new ApprovalChainResolver);
        $rejected = $service->reject($request, $approver, 'Not enough justification');

        expect($rejected->status)->toBe(OvertimeRequestStatus::Rejected);
        expect($rejected->rejected_at)->not->toBeNull();

        Notification::assertSentTo($employeeUser, OvertimeRequestRejected::class);
    });

    it('prevents unauthorized approver from approving', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $authorizedApprover = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $unauthorizedApprover = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $request = OvertimeRequest::factory()->pending()->create([
            'employee_id' => $employee->id,
            'current_approval_level' => 1,
            'total_approval_levels' => 1,
        ]);

        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 1,
            'approver_employee_id' => $authorizedApprover->id,
        ]);

        $service = new OvertimeRequestService(new ApprovalChainResolver);

        expect(fn () => $service->approve($request, $unauthorizedApprover))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });

    it('cancels a pending overtime request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $approver = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $request = OvertimeRequest::factory()->pending()->create([
            'employee_id' => $employee->id,
        ]);

        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver->id,
        ]);

        $service = new OvertimeRequestService(new ApprovalChainResolver);
        $cancelled = $service->cancel($request, 'Changed plans');

        expect($cancelled->status)->toBe(OvertimeRequestStatus::Cancelled);
        expect($cancelled->cancelled_at)->not->toBeNull();
        expect($cancelled->cancellation_reason)->toBe('Changed plans');
        expect($cancelled->approvals()->where('decision', OvertimeApprovalDecision::Pending)->count())->toBe(0);
        expect($cancelled->approvals()->where('decision', OvertimeApprovalDecision::Skipped)->count())->toBe(1);
    });
});

describe('OvertimeRequest Queries', function () {
    it('retrieves pending approvals for an approver', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $approver = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $request = OvertimeRequest::factory()->pending()->create([
            'employee_id' => $employee->id,
            'current_approval_level' => 1,
        ]);

        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver->id,
        ]);

        $pendingApprovals = OvertimeRequestApproval::forApprover($approver->id)
            ->pending()
            ->get();

        expect($pendingApprovals->count())->toBe(1);
    });

    it('generates unique reference numbers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $ref1 = OvertimeRequest::generateReferenceNumber();
        OvertimeRequest::factory()->create(['reference_number' => $ref1]);
        $ref2 = OvertimeRequest::generateReferenceNumber();

        expect($ref1)->toStartWith('OT-');
        expect($ref2)->toStartWith('OT-');
        expect($ref1)->not->toBe($ref2);
    });
});

describe('OvertimeRequestStatus Enum', function () {
    it('has correct transition rules', function () {
        expect(OvertimeRequestStatus::Draft->canTransitionTo(OvertimeRequestStatus::Pending))->toBeTrue();
        expect(OvertimeRequestStatus::Draft->canTransitionTo(OvertimeRequestStatus::Cancelled))->toBeTrue();
        expect(OvertimeRequestStatus::Draft->canTransitionTo(OvertimeRequestStatus::Approved))->toBeFalse();

        expect(OvertimeRequestStatus::Pending->canTransitionTo(OvertimeRequestStatus::Approved))->toBeTrue();
        expect(OvertimeRequestStatus::Pending->canTransitionTo(OvertimeRequestStatus::Rejected))->toBeTrue();
        expect(OvertimeRequestStatus::Pending->canTransitionTo(OvertimeRequestStatus::Cancelled))->toBeTrue();
        expect(OvertimeRequestStatus::Pending->canTransitionTo(OvertimeRequestStatus::Draft))->toBeFalse();

        expect(OvertimeRequestStatus::Approved->isFinal())->toBeTrue();
        expect(OvertimeRequestStatus::Rejected->isFinal())->toBeTrue();
        expect(OvertimeRequestStatus::Cancelled->isFinal())->toBeTrue();
    });

    it('determines editability correctly', function () {
        expect(OvertimeRequestStatus::Draft->canBeEdited())->toBeTrue();
        expect(OvertimeRequestStatus::Pending->canBeEdited())->toBeFalse();
        expect(OvertimeRequestStatus::Approved->canBeEdited())->toBeFalse();
    });
});

describe('OvertimeApprovalDecision Enum', function () {
    it('has correct decision states', function () {
        expect(OvertimeApprovalDecision::Pending->isDecided())->toBeFalse();
        expect(OvertimeApprovalDecision::Approved->isDecided())->toBeTrue();
        expect(OvertimeApprovalDecision::Rejected->isDecided())->toBeTrue();
        expect(OvertimeApprovalDecision::Skipped->isDecided())->toBeTrue();
    });

    it('determines which decisions allow workflow continuation', function () {
        expect(OvertimeApprovalDecision::Pending->allowsContinuation())->toBeFalse();
        expect(OvertimeApprovalDecision::Approved->allowsContinuation())->toBeTrue();
        expect(OvertimeApprovalDecision::Rejected->allowsContinuation())->toBeFalse();
        expect(OvertimeApprovalDecision::Skipped->allowsContinuation())->toBeTrue();
    });
});

describe('OvertimeType Enum', function () {
    it('has correct multiplier values', function () {
        expect(OvertimeType::Regular->multiplier())->toBe(1.25);
        expect(OvertimeType::RestDay->multiplier())->toBe(1.30);
        expect(OvertimeType::Holiday->multiplier())->toBe(2.00);
    });

    it('provides options for forms', function () {
        $options = OvertimeType::options();

        expect($options)->toBeArray();
        expect(count($options))->toBe(3);
        expect($options[0])->toHaveKeys(['value', 'label', 'color']);
    });
});

describe('Payroll OT Gating & Capping', function () {
    it('only counts overtime with a linked approved OT request in payroll aggregation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $date = Carbon::parse('2026-01-15');

        $approvedRequest = OvertimeRequest::factory()->approved()->create([
            'employee_id' => $employee->id,
            'overtime_date' => $date->toDateString(),
            'expected_minutes' => 120,
        ]);

        // DTR with linked approved request — should be counted
        DailyTimeRecord::factory()->forDate($date)->withOvertime(90, true)->create([
            'employee_id' => $employee->id,
            'overtime_request_id' => $approvedRequest->id,
        ]);

        // DTR with overtime_approved but NO linked request — should NOT be counted
        DailyTimeRecord::factory()->forDate($date->copy()->addDay())->withOvertime(60, true)->create([
            'employee_id' => $employee->id,
            'overtime_request_id' => null,
        ]);

        $service = new DtrAggregationService;
        $result = $service->aggregate($employee, $date->copy(), $date->copy()->addDay());

        expect($result['total_overtime_minutes'])->toBe(90);
    });

    it('caps payable overtime to approved request expected_minutes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');
        $otDate = Carbon::parse('2026-01-15');

        $approvedRequest = OvertimeRequest::factory()->approved()->create([
            'employee_id' => $employee->id,
            'overtime_date' => $otDate->toDateString(),
            'expected_minutes' => 90,
        ]);

        // Employee actually worked 120 OT minutes, but request only approved 90
        DailyTimeRecord::factory()->forDate($otDate)->withOvertime(120, true)->create([
            'employee_id' => $employee->id,
            'overtime_request_id' => $approvedRequest->id,
        ]);

        $service = new DtrAggregationService;
        $result = $service->aggregate($employee, $periodStart, $periodEnd);

        expect($result['total_overtime_minutes'])->toBe(90);
    });

    it('counts full actual overtime when it is less than approved amount', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');
        $otDate = Carbon::parse('2026-01-15');

        $approvedRequest = OvertimeRequest::factory()->approved()->create([
            'employee_id' => $employee->id,
            'overtime_date' => $otDate->toDateString(),
            'expected_minutes' => 120,
        ]);

        // Employee worked only 60 OT minutes, less than approved 120
        DailyTimeRecord::factory()->forDate($otDate)->withOvertime(60, true)->create([
            'employee_id' => $employee->id,
            'overtime_request_id' => $approvedRequest->id,
        ]);

        $service = new DtrAggregationService;
        $result = $service->aggregate($employee, $periodStart, $periodEnd);

        expect($result['total_overtime_minutes'])->toBe(60);
    });
});

describe('Level 2 Overtime Approvers', function () {
    /**
     * @return array{employee: Employee, departmentHead: Employee, headUser: User}
     */
    function createEmployeeWithDepartmentHeadForOT(Tenant $tenant): array
    {
        $headUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        $departmentHead = Employee::factory()->create([
            'user_id' => $headUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);
        $department = \App\Models\Department::factory()->create(['department_head_id' => $departmentHead->id]);

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        return ['employee' => $employee, 'departmentHead' => $departmentHead, 'headUser' => $headUser];
    }

    it('lets any one of several overtime approvers give the level 2 approval', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        ['employee' => $employee, 'departmentHead' => $departmentHead, 'headUser' => $headUser] = createEmployeeWithDepartmentHeadForOT($tenant);

        $firstApproverUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        $firstApprover = Employee::factory()->create([
            'user_id' => $firstApproverUser->id,
            'employment_status' => EmploymentStatus::Active,
            'is_overtime_approver' => true,
        ]);
        $secondApproverUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        $secondApprover = Employee::factory()->create([
            'user_id' => $secondApproverUser->id,
            'employment_status' => EmploymentStatus::Active,
            'is_overtime_approver' => true,
        ]);

        $request = OvertimeRequest::factory()->create(['employee_id' => $employee->id]);

        $service = app(OvertimeRequestService::class);
        $submitted = $service->submit($request);

        expect($submitted->total_approval_levels)->toBe(2);
        expect($submitted->approvals->where('approval_level', 2)->pluck('approver_employee_id')->all())
            ->toEqualCanonicalizing([$firstApprover->id, $secondApprover->id]);
        Notification::assertSentTo($headUser, \App\Notifications\OvertimeRequestSubmitted::class);

        $service->approve($submitted, $departmentHead);

        Notification::assertSentTo($firstApproverUser, \App\Notifications\OvertimeRequestSubmitted::class);
        Notification::assertSentTo($secondApproverUser, \App\Notifications\OvertimeRequestSubmitted::class);

        $approved = $service->approve($submitted->fresh(), $secondApprover);

        expect($approved->status)->toBe(OvertimeRequestStatus::Approved);
        expect($approved->approvals->firstWhere('approver_employee_id', $firstApprover->id)->decision)
            ->toBe(OvertimeApprovalDecision::Skipped);

        expect(fn () => $service->approve($approved, $firstApprover))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('falls back to the leave admin manager when no overtime approver is flagged', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        ['employee' => $employee] = createEmployeeWithDepartmentHeadForOT($tenant);
        $adminManager = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_leave_admin_manager' => true,
        ]);

        $levelTwo = app(OvertimeRequestService::class)->resolveApprovers($employee)->where('level', 2);

        expect($levelTwo->pluck('employee.id')->all())->toBe([$adminManager->id]);
        expect($levelTwo->first()['type'])->toBe('admin_manager');
    });

    it('excludes the applicant and the department head from level 2', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        ['employee' => $employee, 'departmentHead' => $departmentHead] = createEmployeeWithDepartmentHeadForOT($tenant);
        $employee->update(['is_overtime_approver' => true]);
        $departmentHead->update(['is_overtime_approver' => true]);
        $otherApprover = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_overtime_approver' => true,
        ]);

        $approvers = app(OvertimeRequestService::class)->resolveApprovers($employee);

        expect($approvers->where('level', 1)->pluck('employee.id')->all())->toBe([$departmentHead->id]);
        expect($approvers->where('level', 2)->pluck('employee.id')->values()->all())->toBe([$otherApprover->id]);
    });

    it('lets an admin replace the overtime approver list', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $previous = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'is_overtime_approver' => true,
        ]);
        [$first, $second] = Employee::factory()->count(2)->create(['employment_status' => EmploymentStatus::Active]);

        $this->actingAs(createTenantUserForOT($tenant, TenantUserRole::Admin));

        $this->postJson("http://{$tenant->slug}.kasamahr.test/api/organization/overtime-settings/approvers", [
            'employee_ids' => [$first->id, $second->id],
        ])->assertSuccessful();

        expect($previous->fresh()->is_overtime_approver)->toBeFalse();
        expect($first->fresh()->is_overtime_approver)->toBeTrue();
        expect($second->fresh()->is_overtime_approver)->toBeTrue();
    });

    it('rejects inactive employees as overtime approvers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $inactive = Employee::factory()->create(['employment_status' => EmploymentStatus::Resigned]);

        $this->actingAs(createTenantUserForOT($tenant, TenantUserRole::Admin));

        $this->postJson("http://{$tenant->slug}.kasamahr.test/api/organization/overtime-settings/approvers", [
            'employee_ids' => [$inactive->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('employee_ids.0');
    });

    it('forbids non-admins from changing overtime approvers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $this->actingAs(createTenantUserForOT($tenant, TenantUserRole::Employee));

        $this->postJson("http://{$tenant->slug}.kasamahr.test/api/organization/overtime-settings/approvers", [
            'employee_ids' => [],
        ])->assertForbidden();
    });
});

describe('OT Approvals Navigation', function () {
    it('flags pending overtime approvals only for the approver at the current level', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        $levelOneUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        $levelOneApprover = Employee::factory()->create(['user_id' => $levelOneUser->id]);
        $levelTwoUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        $levelTwoApprover = Employee::factory()->create(['user_id' => $levelTwoUser->id]);

        $request = OvertimeRequest::factory()->pending()->create([
            'current_approval_level' => 1,
            'total_approval_levels' => 2,
        ]);
        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 1,
            'approver_employee_id' => $levelOneApprover->id,
        ]);
        OvertimeRequestApproval::factory()->pending()->create([
            'overtime_request_id' => $request->id,
            'approval_level' => 2,
            'approver_employee_id' => $levelTwoApprover->id,
        ]);

        $url = "http://{$tenant->slug}.kasamahr.test/overtime/approvals";

        $this->actingAs($levelOneUser)->get($url)
            ->assertInertia(fn ($page) => $page->where('tenant.has_pending_overtime_approvals', true));

        $this->actingAs($levelTwoUser)->get($url)
            ->assertInertia(fn ($page) => $page->where('tenant.has_pending_overtime_approvals', false));
    });
});

describe('Level 2 Skip Option', function () {
    /**
     * @return array{0: OvertimeRequest, 1: Employee, 2: Employee, 3: User}
     */
    function createTwoLevelOvertimeRequest(Tenant $tenant): array
    {
        $levelOneApprover = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $levelTwoUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        $levelTwoApprover = Employee::factory()->create([
            'user_id' => $levelTwoUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $request = OvertimeRequest::factory()->pending()->create([
            'current_approval_level' => 1,
            'total_approval_levels' => 2,
        ]);

        foreach ([1 => $levelOneApprover, 2 => $levelTwoApprover] as $level => $approver) {
            OvertimeRequestApproval::factory()->pending()->create([
                'overtime_request_id' => $request->id,
                'approval_level' => $level,
                'approver_employee_id' => $approver->id,
            ]);
        }

        return [$request, $levelOneApprover, $levelTwoApprover, $levelTwoUser];
    }

    it('keeps level 2 waiting for level 1 when the option is off', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForOT($tenant);

        [$request, , , $levelTwoUser] = createTwoLevelOvertimeRequest($tenant);

        $this->actingAs($levelTwoUser)
            ->postJson("http://{$tenant->slug}.kasamahr.test/api/overtime-approvals/{$request->id}/approve")
            ->assertUnprocessable();

        expect($request->fresh()->status)->toBe(OvertimeRequestStatus::Pending);
    });

    it('lets level 2 give final approval before level 1 when the option is on', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create(['payroll_settings' => ['overtime_level_two_can_skip' => true]]);
        bindTenantContextForOT($tenant);

        [$request, $levelOneApprover, $levelTwoApprover, $levelTwoUser] = createTwoLevelOvertimeRequest($tenant);

        $this->actingAs($levelTwoUser)
            ->get("http://{$tenant->slug}.kasamahr.test/overtime/approvals")
            ->assertInertia(fn ($page) => $page
                ->where('tenant.has_pending_overtime_approvals', true)
                ->where('summary.pending_count', 1));

        $this->actingAs($levelTwoUser)
            ->postJson("http://{$tenant->slug}.kasamahr.test/api/overtime-approvals/{$request->id}/approve", [
                'remarks' => 'Approved early',
            ])
            ->assertSuccessful();

        $result = $request->fresh(['approvals']);

        expect($result->status)->toBe(OvertimeRequestStatus::Approved);
        expect($result->current_approval_level)->toBe(2);
        expect($result->approvals->firstWhere('approver_employee_id', $levelOneApprover->id)->decision)
            ->toBe(OvertimeApprovalDecision::Skipped);
        expect($result->approvals->firstWhere('approver_employee_id', $levelTwoApprover->id)->decision)
            ->toBe(OvertimeApprovalDecision::Approved);
    });

    it('notifies level 2 approvers at submission when the option is on', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create(['payroll_settings' => ['overtime_level_two_can_skip' => true]]);
        bindTenantContextForOT($tenant);

        ['employee' => $employee, 'headUser' => $headUser] = createEmployeeWithDepartmentHeadForOT($tenant);
        $levelTwoUser = createTenantUserForOT($tenant, TenantUserRole::Employee);
        Employee::factory()->create([
            'user_id' => $levelTwoUser->id,
            'employment_status' => EmploymentStatus::Active,
            'is_overtime_approver' => true,
        ]);

        app(OvertimeRequestService::class)->submit(OvertimeRequest::factory()->create(['employee_id' => $employee->id]));

        Notification::assertSentTo($headUser, \App\Notifications\OvertimeRequestSubmitted::class);
        Notification::assertSentTo($levelTwoUser, \App\Notifications\OvertimeRequestSubmitted::class);
    });

    it('still sends level 1 approvals on to level 2 when the option is on', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create(['payroll_settings' => ['overtime_level_two_can_skip' => true]]);
        bindTenantContextForOT($tenant);

        [$request, $levelOneApprover, $levelTwoApprover] = createTwoLevelOvertimeRequest($tenant);

        $result = app(OvertimeRequestService::class)->approve($request, $levelOneApprover);

        expect($result->status)->toBe(OvertimeRequestStatus::Pending);
        expect($result->current_approval_level)->toBe(2);
        expect($result->approvals->firstWhere('approver_employee_id', $levelTwoApprover->id)->decision)
            ->toBe(OvertimeApprovalDecision::Pending);
    });

    it('lets an admin turn the option on and keeps it when omitted', function () {
        $tenant = Tenant::factory()->create(['payroll_settings' => ['double_holiday_rate' => 260]]);
        bindTenantContextForOT($tenant);

        $this->actingAs(createTenantUserForOT($tenant, TenantUserRole::Admin));
        $url = "http://{$tenant->slug}.kasamahr.test/api/organization/overtime-settings/approvers";

        $this->postJson($url, ['employee_ids' => [], 'level_two_can_skip' => true])
            ->assertSuccessful()
            ->assertJsonPath('level_two_can_skip', true);

        $this->postJson($url, ['employee_ids' => []])
            ->assertSuccessful()
            ->assertJsonPath('level_two_can_skip', true);

        expect($tenant->fresh()->payroll_settings)->toBe([
            'double_holiday_rate' => 260,
            'overtime_level_two_can_skip' => true,
        ]);
    });
});
