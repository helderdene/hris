<?php

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveApprovalDecision;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LeaveApplicationApproved;
use App\Notifications\LeaveApplicationRejected;
use App\Services\ApprovalChainResolver;
use App\Services\LeaveApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function bindTenantContextForApproval(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForApproval(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('LeaveApplicationService Approval', function () {
    it('approves a leave application at single level', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForApproval($tenant);

        $employeeUser = createTenantUserForApproval($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approverUser = createTenantUserForApproval($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $leaveType = LeaveType::factory()->create();
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 15,
            'used' => 0,
            'pending' => 3,
        ]);

        $application = LeaveApplication::factory()->pending()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => $balance->id,
            'total_days' => 3,
            'current_approval_level' => 1,
            'total_approval_levels' => 1,
        ]);

        LeaveApplicationApproval::factory()->pending()->create([
            'leave_application_id' => $application->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver->id,
            'approver_name' => $approver->full_name,
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);
        $approved = $service->approve($application, $approver, 'Enjoy your vacation!');

        expect($approved->status)->toBe(LeaveApplicationStatus::Approved);
        expect($approved->approved_at)->not->toBeNull();

        // Balance should be converted from pending to used
        $balance->refresh();
        expect((float) $balance->pending)->toBe(0.0);
        expect((float) $balance->used)->toBe(3.0);

        // Employee should be notified
        Notification::assertSentTo($employeeUser, LeaveApplicationApproved::class);
    });

    it('advances to next approval level on multi-level approval', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForApproval($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $approver1User = createTenantUserForApproval($tenant, TenantUserRole::Admin);
        $approver1 = Employee::factory()->create([
            'user_id' => $approver1User->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approver2User = createTenantUserForApproval($tenant, TenantUserRole::Admin);
        $approver2 = Employee::factory()->create([
            'user_id' => $approver2User->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

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
            'current_approval_level' => 1,
            'total_approval_levels' => 2,
        ]);

        LeaveApplicationApproval::factory()->pending()->create([
            'leave_application_id' => $application->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver1->id,
        ]);

        LeaveApplicationApproval::factory()->pending()->create([
            'leave_application_id' => $application->id,
            'approval_level' => 2,
            'approver_employee_id' => $approver2->id,
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);
        $afterFirstApproval = $service->approve($application, $approver1, 'First approval');

        // Should still be pending, waiting for second approval
        expect($afterFirstApproval->status)->toBe(LeaveApplicationStatus::Pending);
        expect($afterFirstApproval->current_approval_level)->toBe(2);

        // Balance should still be pending
        $balance->refresh();
        expect((float) $balance->pending)->toBe(3.0);
        expect((float) $balance->used)->toBe(0.0);

        // Second approver should be notified
        Notification::assertSentTo($approver2User, \App\Notifications\LeaveApplicationSubmitted::class);
    });

    it('rejects a leave application and releases balance', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForApproval($tenant);

        $employeeUser = createTenantUserForApproval($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $approverUser = createTenantUserForApproval($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

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
            'current_approval_level' => 1,
            'total_approval_levels' => 1,
        ]);

        LeaveApplicationApproval::factory()->pending()->create([
            'leave_application_id' => $application->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver->id,
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);
        $rejected = $service->reject($application, $approver, 'Insufficient staffing during this period');

        expect($rejected->status)->toBe(LeaveApplicationStatus::Rejected);
        expect($rejected->rejected_at)->not->toBeNull();

        // Balance should be released
        $balance->refresh();
        expect((float) $balance->pending)->toBe(0.0);
        expect((float) $balance->used)->toBe(0.0);

        // Employee should be notified
        Notification::assertSentTo($employeeUser, LeaveApplicationRejected::class);
    });

    it('prevents unauthorized approver from approving', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForApproval($tenant);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $authorizedApprover = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $unauthorizedApprover = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $leaveType = LeaveType::factory()->create();

        $application = LeaveApplication::factory()->pending()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'current_approval_level' => 1,
            'total_approval_levels' => 1,
        ]);

        LeaveApplicationApproval::factory()->pending()->create([
            'leave_application_id' => $application->id,
            'approval_level' => 1,
            'approver_employee_id' => $authorizedApprover->id,
        ]);

        $service = new LeaveApplicationService(new ApprovalChainResolver);

        expect(fn () => $service->approve($application, $unauthorizedApprover))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });
});

describe('LeaveApproval Queries', function () {
    it('retrieves pending approvals for an approver', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForApproval($tenant);

        $approverUser = createTenantUserForApproval($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $leaveType = LeaveType::factory()->create();

        $application = LeaveApplication::factory()->pending()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'current_approval_level' => 1,
            'reference_number' => 'LV-2026-PENDING1',
        ]);

        LeaveApplicationApproval::factory()->pending()->create([
            'leave_application_id' => $application->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver->id,
        ]);

        // Query pending approvals
        $pendingApprovals = LeaveApplicationApproval::forApprover($approver->id)
            ->pending()
            ->get();

        expect($pendingApprovals->count())->toBe(1);
    });

    it('queries leave applications for an approver', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForApproval($tenant);

        $approverUser = createTenantUserForApproval($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $leaveType = LeaveType::factory()->create();

        // Create applications at different levels
        $app1 = LeaveApplication::factory()->pending()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'current_approval_level' => 1,
            'reference_number' => 'LV-2026-APPR1',
        ]);
        LeaveApplicationApproval::factory()->pending()->create([
            'leave_application_id' => $app1->id,
            'approval_level' => 1,
            'approver_employee_id' => $approver->id,
        ]);

        $app2 = LeaveApplication::factory()->pending()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'current_approval_level' => 2,
            'reference_number' => 'LV-2026-APPR2',
        ]);
        LeaveApplicationApproval::factory()->pending()->create([
            'leave_application_id' => $app2->id,
            'approval_level' => 2,
            'approver_employee_id' => $approver->id,
        ]);

        // Query applications that need this approver's attention
        $applications = LeaveApplication::forApprover($approver->id)->get();

        expect($applications->count())->toBe(2);
    });

    it('validates reason required for rejection', function () {
        $rules = (new \App\Http\Requests\RejectLeaveApplicationRequest)->rules();
        $validator = \Illuminate\Support\Facades\Validator::make([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('reason'))->toBeTrue();
    });

    it('counts pending approvals for employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForApproval($tenant);

        $approverUser = createTenantUserForApproval($tenant, TenantUserRole::Admin);
        $approver = Employee::factory()->create([
            'user_id' => $approverUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $leaveType = LeaveType::factory()->create();

        // Create 3 pending applications
        for ($i = 1; $i <= 3; $i++) {
            $app = LeaveApplication::factory()->pending()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'current_approval_level' => 1,
                'reference_number' => "LV-2026-COUNT{$i}",
            ]);
            LeaveApplicationApproval::factory()->pending()->create([
                'leave_application_id' => $app->id,
                'approval_level' => 1,
                'approver_employee_id' => $approver->id,
            ]);
        }

        $pendingCount = $approver->pendingLeaveApprovals()->count();

        expect($pendingCount)->toBe(3);
    });
});

describe('LeaveApprovalDecision Enum', function () {
    it('has correct decision states', function () {
        expect(LeaveApprovalDecision::Pending->isDecided())->toBeFalse();
        expect(LeaveApprovalDecision::Approved->isDecided())->toBeTrue();
        expect(LeaveApprovalDecision::Rejected->isDecided())->toBeTrue();
        expect(LeaveApprovalDecision::Skipped->isDecided())->toBeTrue();
    });

    it('determines which decisions allow workflow continuation', function () {
        expect(LeaveApprovalDecision::Pending->allowsContinuation())->toBeFalse();
        expect(LeaveApprovalDecision::Approved->allowsContinuation())->toBeTrue();
        expect(LeaveApprovalDecision::Rejected->allowsContinuation())->toBeFalse();
        expect(LeaveApprovalDecision::Skipped->allowsContinuation())->toBeTrue();
    });
});
