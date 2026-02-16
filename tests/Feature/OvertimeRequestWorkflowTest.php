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

        $request = OvertimeRequest::factory()->pending()->create([
            'employee_id' => $employee->id,
        ]);

        $service = new OvertimeRequestService(new ApprovalChainResolver);
        $cancelled = $service->cancel($request, 'Changed plans');

        expect($cancelled->status)->toBe(OvertimeRequestStatus::Cancelled);
        expect($cancelled->cancelled_at)->not->toBeNull();
        expect($cancelled->cancellation_reason)->toBe('Changed plans');
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
