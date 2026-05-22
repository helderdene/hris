<?php

use App\Enums\AttendanceSource;
use App\Enums\EmploymentStatus;
use App\Enums\ManualAttendanceRequestStatus;
use App\Enums\TenantUserRole;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ManualAttendanceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ManualAttendanceRequestApproved;
use App\Notifications\ManualAttendanceRequestRejected;
use App\Notifications\ManualAttendanceRequestSubmitted;
use App\Services\ManualAttendanceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function bindTenantContextForMAR(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForMAR(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Build an employee plus a department whose head is another employee.
 *
 * @return array{requester: Employee, requesterUser: User, head: Employee, headUser: User, department: Department}
 */
function buildDepartmentWithHead(Tenant $tenant): array
{
    $headUser = createTenantUserForMAR($tenant, TenantUserRole::Admin);
    $head = Employee::factory()->create([
        'user_id' => $headUser->id,
        'employment_status' => EmploymentStatus::Active,
    ]);

    $department = Department::factory()->create([
        'department_head_id' => $head->id,
    ]);

    $head->update(['department_id' => $department->id]);

    $requesterUser = createTenantUserForMAR($tenant, TenantUserRole::Employee);
    $requester = Employee::factory()->create([
        'user_id' => $requesterUser->id,
        'department_id' => $department->id,
        'employment_status' => EmploymentStatus::Active,
    ]);

    return [
        'requester' => $requester,
        'requesterUser' => $requesterUser,
        'head' => $head,
        'headUser' => $headUser,
        'department' => $department,
    ];
}

function makeAdminManager(Tenant $tenant): array
{
    $adminUser = createTenantUserForMAR($tenant, TenantUserRole::Admin);
    $admin = Employee::factory()->create([
        'user_id' => $adminUser->id,
        'employment_status' => EmploymentStatus::Active,
        'is_leave_admin_manager' => true,
    ]);

    return ['user' => $adminUser, 'employee' => $admin];
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('ManualAttendanceRequestService submit', function () {
    it('moves a draft to pending and notifies eligible approvers', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester, 'headUser' => $headUser] = buildDepartmentWithHead($tenant);
        ['user' => $adminUser] = makeAdminManager($tenant);

        $request = ManualAttendanceRequest::factory()->draft()->create([
            'employee_id' => $requester->id,
        ]);

        $service = app(ManualAttendanceRequestService::class);
        $result = $service->submit($request);

        expect($result->status)->toBe(ManualAttendanceRequestStatus::Pending);
        expect($result->submitted_at)->not->toBeNull();

        Notification::assertSentTo($headUser, ManualAttendanceRequestSubmitted::class);
        Notification::assertSentTo($adminUser, ManualAttendanceRequestSubmitted::class);
    });

    it('rejects submission of non-draft requests', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester] = buildDepartmentWithHead($tenant);
        $request = ManualAttendanceRequest::factory()->pending()->create([
            'employee_id' => $requester->id,
        ]);

        $service = app(ManualAttendanceRequestService::class);

        expect(fn () => $service->submit($request))
            ->toThrow(ValidationException::class);
    });
});

describe('ManualAttendanceRequestService approve', function () {
    it('allows the department head to approve and materialises attendance logs', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester, 'requesterUser' => $requesterUser, 'headUser' => $headUser] =
            buildDepartmentWithHead($tenant);

        $request = ManualAttendanceRequest::factory()->pending()->create([
            'employee_id' => $requester->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'time_in' => '08:00',
            'time_out' => '17:00',
        ]);

        $service = app(ManualAttendanceRequestService::class);
        $service->approve($request, $headUser, 'Approved by dept head');

        $request->refresh();

        expect($request->status)->toBe(ManualAttendanceRequestStatus::Approved);
        expect($request->decided_by_user_id)->toBe($headUser->id);
        expect($request->decided_by_role)->toBe('department_head');
        expect($request->decision_remarks)->toBe('Approved by dept head');

        $logs = AttendanceLog::query()->where('employee_id', $requester->id)->get();
        expect($logs)->toHaveCount(2);
        expect($logs->pluck('source')->all())->each->toBe(AttendanceSource::Manual);
        expect($logs->pluck('direction')->sort()->values()->all())->toBe(['in', 'out']);

        Notification::assertSentTo($requesterUser, ManualAttendanceRequestApproved::class);
    });

    it('allows an HR admin manager to approve a request from any department', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester, 'requesterUser' => $requesterUser] = buildDepartmentWithHead($tenant);
        ['user' => $adminUser] = makeAdminManager($tenant);

        $request = ManualAttendanceRequest::factory()->pending()->create([
            'employee_id' => $requester->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'time_in' => '09:00',
            'time_out' => null,
        ]);

        $service = app(ManualAttendanceRequestService::class);
        $service->approve($request, $adminUser, null);

        $request->refresh();

        expect($request->status)->toBe(ManualAttendanceRequestStatus::Approved);
        expect($request->decided_by_role)->toBe('admin_manager');

        $logs = AttendanceLog::query()->where('employee_id', $requester->id)->get();
        expect($logs)->toHaveCount(1);
        expect($logs->first()->direction)->toBe('in');
        expect($logs->first()->source)->toBe(AttendanceSource::Manual);

        Notification::assertSentTo($requesterUser, ManualAttendanceRequestApproved::class);
    });

    it('blocks approval by an unrelated employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester] = buildDepartmentWithHead($tenant);

        $outsiderUser = createTenantUserForMAR($tenant, TenantUserRole::Employee);
        Employee::factory()->create([
            'user_id' => $outsiderUser->id,
            'employment_status' => EmploymentStatus::Active,
        ]);

        $request = ManualAttendanceRequest::factory()->pending()->create([
            'employee_id' => $requester->id,
        ]);

        $service = app(ManualAttendanceRequestService::class);

        expect(fn () => $service->approve($request, $outsiderUser, null))
            ->toThrow(ValidationException::class);
    });

    it('blocks an employee from approving their own request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester, 'requesterUser' => $requesterUser] = buildDepartmentWithHead($tenant);

        // make the requester an admin manager so eligibility check exists but
        // self-approval guard kicks in
        $requester->update(['is_leave_admin_manager' => true]);

        $request = ManualAttendanceRequest::factory()->pending()->create([
            'employee_id' => $requester->id,
        ]);

        $service = app(ManualAttendanceRequestService::class);

        expect(fn () => $service->approve($request, $requesterUser, null))
            ->toThrow(ValidationException::class);
    });

    it('refuses a second decision after the first one lands', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester, 'headUser' => $headUser] = buildDepartmentWithHead($tenant);
        ['user' => $adminUser] = makeAdminManager($tenant);

        $request = ManualAttendanceRequest::factory()->pending()->create([
            'employee_id' => $requester->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'time_in' => '08:00',
        ]);

        $service = app(ManualAttendanceRequestService::class);
        $service->approve($request, $headUser, 'First');

        expect(fn () => $service->approve($request, $adminUser, 'Second'))
            ->toThrow(ValidationException::class);
    });
});

describe('ManualAttendanceRequestService reject', function () {
    it('marks the request rejected and notifies the employee', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester, 'requesterUser' => $requesterUser, 'headUser' => $headUser] =
            buildDepartmentWithHead($tenant);

        $request = ManualAttendanceRequest::factory()->pending()->create([
            'employee_id' => $requester->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'time_in' => '08:00',
            'time_out' => '17:00',
        ]);

        $service = app(ManualAttendanceRequestService::class);
        $service->reject($request, $headUser, 'No supporting evidence.');

        $request->refresh();

        expect($request->status)->toBe(ManualAttendanceRequestStatus::Rejected);
        expect($request->decided_by_user_id)->toBe($headUser->id);
        expect($request->decision_remarks)->toBe('No supporting evidence.');

        expect(AttendanceLog::query()->where('employee_id', $requester->id)->count())->toBe(0);

        Notification::assertSentTo($requesterUser, ManualAttendanceRequestRejected::class);
    });
});

describe('ManualAttendanceRequestService cancel', function () {
    it('cancels a pending request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester] = buildDepartmentWithHead($tenant);

        $request = ManualAttendanceRequest::factory()->pending()->create([
            'employee_id' => $requester->id,
        ]);

        $service = app(ManualAttendanceRequestService::class);
        $service->cancel($request, 'No longer needed');

        $request->refresh();

        expect($request->status)->toBe(ManualAttendanceRequestStatus::Cancelled);
        expect($request->cancellation_reason)->toBe('No longer needed');
    });

    it('refuses to cancel a decided request', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester] = buildDepartmentWithHead($tenant);

        $request = ManualAttendanceRequest::factory()->approved()->create([
            'employee_id' => $requester->id,
        ]);

        $service = app(ManualAttendanceRequestService::class);

        expect(fn () => $service->cancel($request))
            ->toThrow(ValidationException::class);
    });
});

describe('ManualAttendanceRequest::scopeForApprover', function () {
    it('matches the department head only for their own department', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $requester, 'head' => $head] = buildDepartmentWithHead($tenant);
        ['requester' => $otherRequester] = buildDepartmentWithHead($tenant);

        ManualAttendanceRequest::factory()->pending()->create(['employee_id' => $requester->id]);
        ManualAttendanceRequest::factory()->pending()->create(['employee_id' => $otherRequester->id]);

        $queue = ManualAttendanceRequest::query()->forApprover($head)->get();

        expect($queue)->toHaveCount(1);
        expect($queue->first()->employee_id)->toBe($requester->id);
    });

    it('matches all pending requests for an admin manager', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMAR($tenant);

        ['requester' => $a] = buildDepartmentWithHead($tenant);
        ['requester' => $b] = buildDepartmentWithHead($tenant);
        ['employee' => $admin] = makeAdminManager($tenant);

        ManualAttendanceRequest::factory()->pending()->create(['employee_id' => $a->id]);
        ManualAttendanceRequest::factory()->pending()->create(['employee_id' => $b->id]);

        expect(ManualAttendanceRequest::query()->forApprover($admin)->count())->toBe(2);
    });
});
