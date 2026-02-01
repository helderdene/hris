<?php

use App\Enums\ScheduleType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\EmployeeScheduleAssignmentController;
use App\Http\Controllers\Api\WorkScheduleController;
use App\Http\Requests\StoreEmployeeScheduleAssignmentRequest;
use App\Http\Requests\StoreWorkScheduleRequest;
use App\Http\Requests\UpdateWorkScheduleRequest;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForWorkSchedule(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForWorkSchedule(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store work schedule request.
 */
function createStoreWorkScheduleRequest(array $data, User $user): StoreWorkScheduleRequest
{
    $request = StoreWorkScheduleRequest::create('/api/organization/work-schedules', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreWorkScheduleRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update work schedule request.
 */
function createUpdateWorkScheduleRequest(array $data, User $user, int $scheduleId): UpdateWorkScheduleRequest
{
    $request = UpdateWorkScheduleRequest::create("/api/organization/work-schedules/{$scheduleId}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRouteResolver(fn () => new class($scheduleId)
    {
        private int $id;

        public function __construct(int $id)
        {
            $this->id = $id;
        }

        public function parameter($name)
        {
            return $this->id;
        }
    });

    $rules = (new UpdateWorkScheduleRequest)->rules();
    // Override the unique rule for testing
    $rules['code'] = ['required', 'string', 'max:50'];

    $validator = Validator::make($data, $rules);
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated store employee schedule assignment request.
 */
function createStoreScheduleAssignmentRequest(array $data, User $user): StoreEmployeeScheduleAssignmentRequest
{
    $request = StoreEmployeeScheduleAssignmentRequest::create('/api/organization/work-schedules/1/assignments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    // Use simplified rules for testing - skip overlap validation
    $rules = [
        'employee_id' => ['required', 'integer'],
        'shift_name' => ['nullable', 'string', 'max:100'],
        'effective_date' => ['required', 'date', 'date_format:Y-m-d'],
        'end_date' => ['nullable', 'date', 'date_format:Y-m-d'],
    ];

    $validator = Validator::make($data, $rules);
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

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

describe('WorkSchedule API', function () {
    it('returns filtered work schedules list on index', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkSchedule($tenant);

        $admin = createTenantUserForWorkSchedule($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create work schedules with different types and statuses
        WorkSchedule::factory()->create([
            'name' => 'Regular Office Hours',
            'code' => 'ROH-001',
            'schedule_type' => ScheduleType::Fixed,
            'status' => 'active',
        ]);

        WorkSchedule::factory()->flexible()->create([
            'name' => 'Flexible Schedule',
            'code' => 'FLX-001',
            'status' => 'active',
        ]);

        WorkSchedule::factory()->create([
            'name' => 'Inactive Schedule',
            'code' => 'INA-001',
            'status' => 'inactive',
        ]);

        $controller = new WorkScheduleController;

        // Test without filters - returns all
        $request = Request::create('/api/organization/work-schedules', 'GET');
        $response = $controller->index($request);
        expect($response->count())->toBe(3);

        // Test filter by status
        $activeRequest = Request::create('/api/organization/work-schedules', 'GET', ['status' => 'active']);
        $activeResponse = $controller->index($activeRequest);
        expect($activeResponse->count())->toBe(2);

        // Test filter by schedule_type
        $flexibleRequest = Request::create('/api/organization/work-schedules', 'GET', ['schedule_type' => 'flexible']);
        $flexibleResponse = $controller->index($flexibleRequest);
        expect($flexibleResponse->count())->toBe(1);
        expect($flexibleResponse->first()->name)->toBe('Flexible Schedule');
    });

    it('creates Fixed schedule with time_configuration', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkSchedule($tenant);

        $admin = createTenantUserForWorkSchedule($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new WorkScheduleController;

        $timeConfiguration = [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'half_day_saturday' => false,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'saturday_end_time' => null,
            'break' => [
                'start_time' => '12:00',
                'duration_minutes' => 60,
            ],
        ];

        $overtimeRules = [
            'daily_threshold_hours' => 8,
            'weekly_threshold_hours' => 40,
            'regular_multiplier' => 1.25,
            'rest_day_multiplier' => 1.30,
            'holiday_multiplier' => 2.0,
        ];

        $scheduleData = [
            'name' => 'Regular Office Hours',
            'code' => 'ROH-001',
            'schedule_type' => 'fixed',
            'description' => 'Standard 9-5 office schedule',
            'status' => 'active',
            'time_configuration' => $timeConfiguration,
            'overtime_rules' => $overtimeRules,
            'night_differential' => [
                'enabled' => false,
                'start_time' => '22:00',
                'end_time' => '06:00',
                'rate_multiplier' => 1.10,
            ],
        ];

        $storeRequest = createStoreWorkScheduleRequest($scheduleData, $admin);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['name'])->toBe('Regular Office Hours');
        expect($data['code'])->toBe('ROH-001');
        expect($data['schedule_type'])->toBe('fixed');
        expect($data['time_configuration']['start_time'])->toBe('08:00');
        expect($data['overtime_rules']['regular_multiplier'])->toBe(1.25);

        $this->assertDatabaseHas('work_schedules', [
            'name' => 'Regular Office Hours',
            'code' => 'ROH-001',
        ]);
    });

    it('creates Flexible schedule with core hours', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkSchedule($tenant);

        $admin = createTenantUserForWorkSchedule($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new WorkScheduleController;

        $timeConfiguration = [
            'required_hours_per_day' => 8,
            'required_hours_per_week' => 40,
            'core_hours' => [
                'start_time' => '10:00',
                'end_time' => '15:00',
            ],
            'flexible_start_window' => [
                'earliest' => '06:00',
                'latest' => '10:00',
            ],
            'break' => [
                'start_time' => null,
                'duration_minutes' => 60,
            ],
        ];

        $scheduleData = [
            'name' => 'Flexible with Core Hours',
            'code' => 'FLX-001',
            'schedule_type' => 'flexible',
            'status' => 'active',
            'time_configuration' => $timeConfiguration,
            'overtime_rules' => [
                'daily_threshold_hours' => 8,
                'weekly_threshold_hours' => 40,
                'regular_multiplier' => 1.25,
                'rest_day_multiplier' => 1.30,
                'holiday_multiplier' => 2.0,
            ],
            'night_differential' => [
                'enabled' => false,
                'start_time' => '22:00',
                'end_time' => '06:00',
                'rate_multiplier' => 1.10,
            ],
        ];

        $storeRequest = createStoreWorkScheduleRequest($scheduleData, $admin);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['name'])->toBe('Flexible with Core Hours');
        expect($data['schedule_type'])->toBe('flexible');
        expect($data['time_configuration']['core_hours']['start_time'])->toBe('10:00');
        expect($data['time_configuration']['required_hours_per_day'])->toBe(8);
    });

    it('validates required fields and JSON structure', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkSchedule($tenant);

        $admin = createTenantUserForWorkSchedule($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Test missing required fields
        $rules = (new StoreWorkScheduleRequest)->rules();
        $validator = Validator::make([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('code'))->toBeTrue();
        expect($validator->errors()->has('schedule_type'))->toBeTrue();
        expect($validator->errors()->has('status'))->toBeTrue();

        // Test invalid schedule_type
        $invalidTypeValidator = Validator::make([
            'name' => 'Test Schedule',
            'code' => 'TEST-001',
            'schedule_type' => 'invalid_type',
            'status' => 'active',
        ], $rules);

        expect($invalidTypeValidator->fails())->toBeTrue();
        expect($invalidTypeValidator->errors()->has('schedule_type'))->toBeTrue();

        // Test duplicate code
        WorkSchedule::factory()->create(['code' => 'DUP-001']);

        $duplicateCodeValidator = Validator::make([
            'name' => 'Duplicate Code Schedule',
            'code' => 'DUP-001',
            'schedule_type' => 'fixed',
            'status' => 'active',
        ], $rules);

        expect($duplicateCodeValidator->fails())->toBeTrue();
        expect($duplicateCodeValidator->errors()->has('code'))->toBeTrue();
    });

    it('returns schedule with assignment count on show', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkSchedule($tenant);

        $admin = createTenantUserForWorkSchedule($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $schedule = WorkSchedule::factory()->create([
            'name' => 'Regular Office Hours',
            'code' => 'ROH-001',
        ]);

        // Create employees and assignments
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee1->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => now()->toDateString(),
        ]);

        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee2->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => now()->toDateString(),
        ]);

        $controller = new WorkScheduleController;
        $response = $controller->show($schedule);
        $data = $response->toArray(request());

        expect($data['name'])->toBe('Regular Office Hours');
        expect($data['assigned_employees_count'])->toBe(2);
    });

    it('updates schedule configuration', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkSchedule($tenant);

        $admin = createTenantUserForWorkSchedule($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new WorkScheduleController;

        $schedule = WorkSchedule::factory()->create([
            'name' => 'Original Name',
            'code' => 'ORI-001',
            'status' => 'active',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'code' => 'ORI-001',
            'schedule_type' => 'fixed',
            'status' => 'inactive',
            'time_configuration' => [
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday'],
                'half_day_saturday' => false,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'saturday_end_time' => null,
                'break' => [
                    'start_time' => '12:00',
                    'duration_minutes' => 60,
                ],
            ],
            'overtime_rules' => [
                'daily_threshold_hours' => 8,
                'weekly_threshold_hours' => 40,
                'regular_multiplier' => 1.25,
                'rest_day_multiplier' => 1.30,
                'holiday_multiplier' => 2.0,
            ],
            'night_differential' => [
                'enabled' => false,
                'start_time' => '22:00',
                'end_time' => '06:00',
                'rate_multiplier' => 1.10,
            ],
        ];

        $updateRequest = createUpdateWorkScheduleRequest($updateData, $admin, $schedule->id);
        $response = $controller->update($updateRequest, $schedule);

        $data = $response->toArray(request());
        expect($data['name'])->toBe('Updated Name');
        expect($data['status'])->toBe('inactive');
        expect($data['time_configuration']['start_time'])->toBe('09:00');

        $this->assertDatabaseHas('work_schedules', [
            'id' => $schedule->id,
            'name' => 'Updated Name',
            'status' => 'inactive',
        ]);
    });

    it('prevents deletion if employees are assigned', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkSchedule($tenant);

        $admin = createTenantUserForWorkSchedule($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new WorkScheduleController;

        $schedule = WorkSchedule::factory()->create([
            'name' => 'Schedule With Employees',
            'code' => 'SWE-001',
        ]);

        // Create an active employee assignment directly using the model
        $employee = Employee::factory()->create();
        EmployeeScheduleAssignment::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => now()->subDay()->toDateString(), // Yesterday
            'end_date' => null,
        ]);

        // Verify active scope works correctly
        $activeCount = $schedule->employeeScheduleAssignments()->active()->count();
        expect($activeCount)->toBe(1);

        $response = $controller->destroy($schedule);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('Cannot delete schedule with active employee assignments.');

        // Verify schedule still exists
        expect(WorkSchedule::find($schedule->id))->not->toBeNull();
    });

    it('assigns employee to schedule with effective date', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForWorkSchedule($tenant);

        $admin = createTenantUserForWorkSchedule($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new EmployeeScheduleAssignmentController;

        $schedule = WorkSchedule::factory()->create([
            'name' => 'Regular Office Hours',
            'code' => 'ROH-001',
        ]);

        $employee = Employee::factory()->create();

        $assignmentData = [
            'employee_id' => $employee->id,
            'effective_date' => now()->addWeek()->toDateString(),
            'end_date' => null,
        ];

        $storeRequest = createStoreScheduleAssignmentRequest($assignmentData, $admin);
        $response = $controller->store($storeRequest, $schedule);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['employee_id'])->toBe($employee->id);
        expect($data['work_schedule_id'])->toBe($schedule->id);
        expect($data['effective_date'])->toBe(now()->addWeek()->toDateString());

        // Verify assignment was created
        expect(EmployeeScheduleAssignment::where('employee_id', $employee->id)->count())->toBe(1);
    });
});
