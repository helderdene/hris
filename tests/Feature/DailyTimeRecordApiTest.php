<?php

use App\Enums\DtrStatus;
use App\Enums\ScheduleType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DailyTimeRecordController;
use App\Http\Requests\Api\CalculateDtrRequest;
use App\Http\Requests\Api\ResolveDtrReviewRequest;
use App\Http\Requests\DtrFilterRequest;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TimeRecordPunch;
use App\Models\User;
use App\Models\WorkLocation;
use App\Models\WorkSchedule;
use App\Services\Dtr\DtrCalculationService;
use App\Services\Dtr\DtrPeriodAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForDtr(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForDtr(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a DtrFilterRequest for testing.
 */
function createDtrFilterRequest(array $params = []): DtrFilterRequest
{
    $request = DtrFilterRequest::create('/api/time-attendance/dtr', 'GET', $params);
    $request->setContainer(app());

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('DTR API Index', function () {
    it('returns DTR records list', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        // Create records with different dates to avoid unique constraint
        DailyTimeRecord::factory()
            ->sequence(
                ['date' => '2025-01-15'],
                ['date' => '2025-01-16'],
                ['date' => '2025-01-17'],
            )
            ->count(3)
            ->create([
                'employee_id' => $employee->id,
            ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest();
        $response = $controller->index($request);

        expect($response->count())->toBe(3);
    });

    it('filters DTR records by employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee1->id,
        ]);
        DailyTimeRecord::factory()->count(3)->create([
            'employee_id' => $employee2->id,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest(['employee_id' => $employee1->id]);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('filters DTR records by date range', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        // Create records for different dates using forDate() helper
        DailyTimeRecord::factory()
            ->forDate('2025-01-15')
            ->create(['employee_id' => $employee->id]);
        DailyTimeRecord::factory()
            ->forDate('2025-01-16')
            ->create(['employee_id' => $employee->id]);
        DailyTimeRecord::factory()
            ->forDate('2025-01-20')
            ->create(['employee_id' => $employee->id]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest([
            'date_from' => '2025-01-15',
            'date_to' => '2025-01-16',
        ]);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('filters DTR records by department', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $dept1 = Department::factory()->create(['name' => 'Engineering']);
        $dept2 = Department::factory()->create(['name' => 'Sales']);

        $employee1 = Employee::factory()->create(['department_id' => $dept1->id]);
        $employee2 = Employee::factory()->create(['department_id' => $dept2->id]);

        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee1->id,
        ]);
        DailyTimeRecord::factory()->count(3)->create([
            'employee_id' => $employee2->id,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest(['department_id' => $dept1->id]);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('filters DTR records by status', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'status' => DtrStatus::Present,
        ]);
        DailyTimeRecord::factory()->count(1)->create([
            'employee_id' => $employee->id,
            'status' => DtrStatus::Absent,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest(['status' => DtrStatus::Present->value]);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('filters DTR records needing review', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'needs_review' => true,
            'review_reason' => 'Missing time-out',
        ]);
        DailyTimeRecord::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'needs_review' => false,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest(['needs_review' => '1']);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('filters DTR records with pending overtime', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'overtime_minutes' => 120,
            'overtime_approved' => false,
        ]);
        DailyTimeRecord::factory()->count(1)->create([
            'employee_id' => $employee->id,
            'overtime_minutes' => 60,
            'overtime_approved' => true,
        ]);
        DailyTimeRecord::factory()->count(1)->create([
            'employee_id' => $employee->id,
            'overtime_minutes' => 0,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest(['overtime_pending' => '1']);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });
});

describe('DTR API Show', function () {
    it('returns a specific DTR record', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $schedule = WorkSchedule::factory()->create([
            'schedule_type' => ScheduleType::Fixed,
        ]);

        $dtr = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'date' => '2025-01-15',
            'status' => DtrStatus::Present,
            'total_work_minutes' => 480,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $response = $controller->show($dtr);

        $data = $response->toArray(request());
        expect($data['id'])->toBe($dtr->id);
        expect($data['date'])->toBe('2025-01-15');
        expect($data['status'])->toBe('present');
        expect($data['total_work_minutes'])->toBe(480);
    });
});

describe('DTR API Employee DTR', function () {
    it('returns DTR records for a specific employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        DailyTimeRecord::factory()->count(5)->create([
            'employee_id' => $employee1->id,
        ]);
        DailyTimeRecord::factory()->count(3)->create([
            'employee_id' => $employee2->id,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest();
        $response = $controller->employeeDtr($request, $employee1);

        expect($response->count())->toBe(5);
    });

    it('filters employee DTR by date range', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-10',
        ]);
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-15',
        ]);
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-20',
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest([
            'date_from' => '2025-01-12',
            'date_to' => '2025-01-18',
        ]);
        $response = $controller->employeeDtr($request, $employee);

        expect($response->count())->toBe(1);
    });
});

describe('DTR API Summary', function () {
    it('returns period summary for an employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $department = Department::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
        ]);

        // Create DTR records for January 2025
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-15',
            'status' => DtrStatus::Present,
            'total_work_minutes' => 480,
            'late_minutes' => 15,
            'undertime_minutes' => 0,
            'overtime_minutes' => 60,
            'overtime_approved' => true,
        ]);
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-16',
            'status' => DtrStatus::Present,
            'total_work_minutes' => 510,
            'late_minutes' => 0,
            'undertime_minutes' => 30,
            'overtime_minutes' => 30,
            'overtime_approved' => false,
        ]);
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-17',
            'status' => DtrStatus::Absent,
            'total_work_minutes' => 0,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = Request::create('/api/time-attendance/dtr/summary', 'GET', [
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31',
        ]);
        app()->instance('request', $request);

        $response = $controller->summary($request, $employee);

        $data = $response->toArray(request());

        expect($data['employee']['id'])->toBe($employee->id);
        expect($data['period']['start_date'])->toBe('2025-01-01');
        expect($data['period']['end_date'])->toBe('2025-01-31');
        expect($data['attendance']['present_days'])->toBe(2);
        expect($data['attendance']['absent_days'])->toBe(1);
    });
});

describe('DTR API Approve Overtime', function () {
    it('approves overtime for a DTR record', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $dtr = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'overtime_minutes' => 120,
            'overtime_approved' => false,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $response = $controller->approveOvertime($dtr);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('Overtime approved successfully.');
        expect($data['data']['overtime_approved'])->toBeTrue();

        $dtr->refresh();
        expect($dtr->overtime_approved)->toBeTrue();
    });

    it('returns error when no overtime to approve', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $dtr = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'overtime_minutes' => 0,
            'overtime_approved' => false,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $response = $controller->approveOvertime($dtr);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('No overtime to approve for this record.');
    });
});

describe('DTR API Resolve Review', function () {
    it('resolves review flag for a DTR record', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $dtr = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'needs_review' => true,
            'review_reason' => 'Missing time-out',
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = ResolveDtrReviewRequest::create('/api/time-attendance/dtr/resolve-review', 'POST', [
            'resolution_type' => 'no_change',
            'remarks' => 'Verified manually with supervisor',
        ]);
        $request->setContainer(app());
        $request->validateResolved();

        $response = $controller->resolveReview($request, $dtr);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('Review resolved.');
        expect($data['data']['needs_review'])->toBeFalse();
        expect($data['data']['remarks'])->toContain('Verified manually with supervisor');

        $dtr->refresh();
        expect($dtr->needs_review)->toBeFalse();
        expect($dtr->review_reason)->toBeNull();
    });

    it('returns error when record does not need review', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $dtr = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'needs_review' => false,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = ResolveDtrReviewRequest::create('/api/time-attendance/dtr/resolve-review', 'POST', [
            'resolution_type' => 'no_change',
            'remarks' => 'Test remarks',
        ]);
        $request->setContainer(app());
        $request->validateResolved();

        $response = $controller->resolveReview($request, $dtr);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('This record does not need review.');
    });
});

describe('DTR API Needs Review', function () {
    it('returns records needing review', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'needs_review' => true,
            'review_reason' => 'Missing time-out',
        ]);
        DailyTimeRecord::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'needs_review' => false,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest();
        $response = $controller->needsReview($request);

        expect($response->count())->toBe(2);
    });
});

describe('DTR API Pending Overtime', function () {
    it('returns records with pending overtime approval', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'overtime_minutes' => 120,
            'overtime_approved' => false,
        ]);
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'overtime_minutes' => 60,
            'overtime_approved' => true,
        ]);
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'overtime_minutes' => 0,
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = createDtrFilterRequest();
        $response = $controller->pendingOvertime($request);

        expect($response->count())->toBe(2);
    });
});

describe('DTR API Calculate', function () {
    it('calculates DTR for employee on a specific date', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $admin = createTenantUserForDtr($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $schedule = WorkSchedule::factory()->create([
            'schedule_type' => ScheduleType::Fixed,
            'time_configuration' => [
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'start_time' => '08:00',
                'end_time' => '17:00',
            ],
            'overtime_rules' => [
                'daily_threshold_hours' => 8,
            ],
            'night_differential' => ['enabled' => false],
        ]);

        $employee = Employee::factory()->create();

        // Assign schedule to employee
        $employee->scheduleAssignments()->create([
            'work_schedule_id' => $schedule->id,
            'effective_date' => '2025-01-01',
        ]);

        // Create attendance logs for 2025-01-15 (Wednesday)
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-15 08:00:00',
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-15 17:30:00',
        ]);

        $controller = new DailyTimeRecordController(
            app(DtrCalculationService::class),
            app(DtrPeriodAggregator::class)
        );

        $request = CalculateDtrRequest::create('/api/time-attendance/dtr/calculate', 'POST', [
            'date' => '2025-01-15',
        ]);
        $request->setContainer(app());
        $request->validateResolved();

        $response = $controller->calculate($request, $employee);

        $data = $response->toArray(request());

        expect($data['date'])->toBe('2025-01-15');
        expect($data['status'])->toBe('present');
        expect($data['employee_id'])->toBe($employee->id);
    });
});

describe('DTR Model Relationships', function () {
    it('has employee relationship', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $employee = Employee::factory()->create();
        $dtr = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
        ]);

        expect($dtr->employee)->toBeInstanceOf(Employee::class);
        expect($dtr->employee->id)->toBe($employee->id);
    });

    it('has work schedule relationship', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $schedule = WorkSchedule::factory()->create();
        $employee = Employee::factory()->create();
        $dtr = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
        ]);

        expect($dtr->workSchedule)->toBeInstanceOf(WorkSchedule::class);
        expect($dtr->workSchedule->id)->toBe($schedule->id);
    });

    it('has punches relationship', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $employee = Employee::factory()->create();
        $dtr = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
        ]);

        TimeRecordPunch::factory()->count(2)->create([
            'daily_time_record_id' => $dtr->id,
        ]);

        expect($dtr->punches)->toHaveCount(2);
        expect($dtr->punches->first())->toBeInstanceOf(TimeRecordPunch::class);
    });
});

describe('DTR Model Scopes', function () {
    it('filters by needs review scope', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $employee = Employee::factory()->create();

        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'needs_review' => true,
        ]);
        DailyTimeRecord::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'needs_review' => false,
        ]);

        $needsReview = DailyTimeRecord::needsReview()->count();

        expect($needsReview)->toBe(2);
    });

    it('filters by for employee scope', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        DailyTimeRecord::factory()->count(3)->create([
            'employee_id' => $employee1->id,
        ]);
        DailyTimeRecord::factory()->count(2)->create([
            'employee_id' => $employee2->id,
        ]);

        $employee1Records = DailyTimeRecord::forEmployee($employee1->id)->count();

        expect($employee1Records)->toBe(3);
    });

    it('filters by date range scope', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForDtr($tenant);

        $employee = Employee::factory()->create();

        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-10',
        ]);
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-15',
        ]);
        DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2025-01-20',
        ]);

        $inRange = DailyTimeRecord::forDateRange('2025-01-12', '2025-01-18')->count();

        expect($inRange)->toBe(1);
    });
});
