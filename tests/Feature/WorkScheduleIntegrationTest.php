<?php

/**
 * Work Schedule Integration Tests
 *
 * These tests fill critical gaps in the Work Schedule Configuration feature coverage,
 * focusing on end-to-end workflows and edge cases.
 */

use App\Enums\ScheduleType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\EmployeeScheduleAssignmentController;
use App\Http\Controllers\Api\WorkScheduleController;
use App\Http\Controllers\Organization\WorkScheduleController as PageWorkScheduleController;
use App\Http\Requests\StoreEmployeeScheduleAssignmentRequest;
use App\Http\Requests\StoreWorkScheduleRequest;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant context for integration tests.
 */
function bindTenantForIntegration(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create admin user for integration tests.
 */
function createAdminUserForIntegration(Tenant $tenant): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to create validated store work schedule request for integration tests.
 */
function createIntegrationStoreRequest(array $data, User $user): StoreWorkScheduleRequest
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
 * Helper to create validated assignment request for integration tests.
 */
function createIntegrationAssignmentRequest(array $data, User $user): StoreEmployeeScheduleAssignmentRequest
{
    $request = StoreEmployeeScheduleAssignmentRequest::create('/api/organization/work-schedules/1/assignments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

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

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

/**
 * Test 1: End-to-end - Create Fixed schedule, assign employee, verify assignment displays correctly
 */
it('completes end-to-end workflow: create Fixed schedule, assign employee, verify display', function () {
    $tenant = Tenant::factory()->create();
    bindTenantForIntegration($tenant);

    $admin = createAdminUserForIntegration($tenant);
    $this->actingAs($admin);

    // Step 1: Create a Fixed schedule
    $scheduleController = new WorkScheduleController;
    $scheduleData = [
        'name' => 'Regular Office Hours',
        'code' => 'REG-001',
        'schedule_type' => 'fixed',
        'description' => 'Standard office schedule',
        'status' => 'active',
        'time_configuration' => [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'half_day_saturday' => false,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'saturday_end_time' => null,
            'break' => ['start_time' => '12:00', 'duration_minutes' => 60],
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

    $storeRequest = createIntegrationStoreRequest($scheduleData, $admin);
    $scheduleResponse = $scheduleController->store($storeRequest);

    expect($scheduleResponse->getStatusCode())->toBe(201);
    $createdSchedule = WorkSchedule::where('code', 'REG-001')->first();
    expect($createdSchedule)->not->toBeNull();

    // Step 2: Create an employee
    $employee = Employee::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    // Step 3: Assign employee to schedule with yesterday's effective date (ensures it's active)
    $assignmentController = new EmployeeScheduleAssignmentController;
    $assignmentData = [
        'employee_id' => $employee->id,
        'effective_date' => now()->subDay()->toDateString(),
        'end_date' => null,
    ];

    $assignmentRequest = createIntegrationAssignmentRequest($assignmentData, $admin);
    $assignmentResponse = $assignmentController->store($assignmentRequest, $createdSchedule);

    expect($assignmentResponse->getStatusCode())->toBe(201);

    // Step 4: Verify the assignment displays correctly on the schedule
    $createdSchedule->refresh();
    $createdSchedule->load('employeeScheduleAssignments');

    expect($createdSchedule->employeeScheduleAssignments)->toHaveCount(1);

    // Step 5: Verify the assignment is active (effective_date <= today)
    $activeAssignments = EmployeeScheduleAssignment::active()
        ->where('employee_id', $employee->id)
        ->get();

    expect($activeAssignments)->toHaveCount(1);
    expect($activeAssignments->first()->workSchedule->name)->toBe('Regular Office Hours');
});

/**
 * Test 2: End-to-end - Create Shifting schedule with multiple shifts, verify configuration saved
 */
it('creates Shifting schedule with multiple shifts and verifies configuration', function () {
    $tenant = Tenant::factory()->create();
    bindTenantForIntegration($tenant);

    $admin = createAdminUserForIntegration($tenant);
    $this->actingAs($admin);

    $scheduleController = new WorkScheduleController;
    $scheduleData = [
        'name' => 'Production Shifts',
        'code' => 'SHIFT-PROD',
        'schedule_type' => 'shifting',
        'description' => 'Three-shift rotation for production',
        'status' => 'active',
        'time_configuration' => [
            'shifts' => [
                [
                    'name' => 'Morning Shift',
                    'start_time' => '06:00',
                    'end_time' => '14:00',
                    'break' => ['start_time' => '10:00', 'duration_minutes' => 30],
                ],
                [
                    'name' => 'Afternoon Shift',
                    'start_time' => '14:00',
                    'end_time' => '22:00',
                    'break' => ['start_time' => '18:00', 'duration_minutes' => 30],
                ],
                [
                    'name' => 'Night Shift',
                    'start_time' => '22:00',
                    'end_time' => '06:00',
                    'break' => ['start_time' => '02:00', 'duration_minutes' => 30],
                ],
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
            'enabled' => true,
            'start_time' => '22:00',
            'end_time' => '06:00',
            'rate_multiplier' => 1.10,
        ],
    ];

    $storeRequest = createIntegrationStoreRequest($scheduleData, $admin);
    $response = $scheduleController->store($storeRequest);

    expect($response->getStatusCode())->toBe(201);

    // Verify the schedule was created with correct configuration
    $schedule = WorkSchedule::where('code', 'SHIFT-PROD')->first();
    expect($schedule)->not->toBeNull();
    expect($schedule->schedule_type)->toBe(ScheduleType::Shifting);
    expect($schedule->time_configuration['shifts'])->toHaveCount(3);
    expect($schedule->time_configuration['shifts'][0]['name'])->toBe('Morning Shift');
    expect($schedule->time_configuration['shifts'][1]['name'])->toBe('Afternoon Shift');
    expect($schedule->time_configuration['shifts'][2]['name'])->toBe('Night Shift');
    expect($schedule->night_differential['enabled'])->toBeTrue();
});

/**
 * Test 3: End-to-end - Create Compressed 4x10 schedule, verify daily hours calculation
 */
it('creates Compressed 4x10 schedule with correct configuration', function () {
    $tenant = Tenant::factory()->create();
    bindTenantForIntegration($tenant);

    $admin = createAdminUserForIntegration($tenant);
    $this->actingAs($admin);

    $scheduleController = new WorkScheduleController;
    $scheduleData = [
        'name' => 'Compressed 4-Day Week',
        'code' => 'CMP-4X10',
        'schedule_type' => 'compressed',
        'description' => 'Four 10-hour days',
        'status' => 'active',
        'time_configuration' => [
            'pattern' => '4x10',
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday'],
            'daily_hours' => 10,
            'half_day' => [
                'enabled' => false,
                'day' => null,
                'hours' => null,
            ],
        ],
        'overtime_rules' => [
            'daily_threshold_hours' => 10, // Higher threshold for compressed schedule
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

    $storeRequest = createIntegrationStoreRequest($scheduleData, $admin);
    $response = $scheduleController->store($storeRequest);

    expect($response->getStatusCode())->toBe(201);

    $schedule = WorkSchedule::where('code', 'CMP-4X10')->first();
    expect($schedule)->not->toBeNull();
    expect($schedule->schedule_type)->toBe(ScheduleType::Compressed);
    expect($schedule->time_configuration['pattern'])->toBe('4x10');
    expect($schedule->time_configuration['work_days'])->toHaveCount(4);
    expect($schedule->time_configuration['daily_hours'])->toBe(10);
    expect($schedule->overtime_rules['daily_threshold_hours'])->toBe(10);
});

/**
 * Test 4: Integration - Assign employee with future effective_date, verify not active yet
 */
it('assigns employee with future effective date and verifies not active yet', function () {
    $tenant = Tenant::factory()->create();
    bindTenantForIntegration($tenant);

    $admin = createAdminUserForIntegration($tenant);
    $this->actingAs($admin);

    $schedule = WorkSchedule::factory()->create([
        'name' => 'Future Schedule',
        'code' => 'FUT-001',
    ]);

    $employee = Employee::factory()->create();

    // Assign with a future effective date (next month)
    $futureDate = now()->addMonth()->toDateString();

    $assignmentController = new EmployeeScheduleAssignmentController;
    $assignmentData = [
        'employee_id' => $employee->id,
        'effective_date' => $futureDate,
        'end_date' => null,
    ];

    $assignmentRequest = createIntegrationAssignmentRequest($assignmentData, $admin);
    $response = $assignmentController->store($assignmentRequest, $schedule);

    expect($response->getStatusCode())->toBe(201);

    // Verify assignment exists
    $assignment = EmployeeScheduleAssignment::where('employee_id', $employee->id)->first();
    expect($assignment)->not->toBeNull();
    expect($assignment->effective_date->toDateString())->toBe($futureDate);

    // Verify the assignment is NOT active (future effective date)
    $activeAssignment = EmployeeScheduleAssignment::active()
        ->where('employee_id', $employee->id)
        ->first();

    expect($activeAssignment)->toBeNull();

    // Verify total assignments exists
    expect(EmployeeScheduleAssignment::where('employee_id', $employee->id)->count())->toBe(1);
});

/**
 * Test 5: Edge case - Validate overlapping assignments are rejected
 */
it('rejects overlapping schedule assignments for the same employee', function () {
    $tenant = Tenant::factory()->create();
    bindTenantForIntegration($tenant);

    $admin = createAdminUserForIntegration($tenant);
    $this->actingAs($admin);

    $schedule1 = WorkSchedule::factory()->create(['name' => 'Schedule 1', 'code' => 'SCH-001']);
    $schedule2 = WorkSchedule::factory()->create(['name' => 'Schedule 2', 'code' => 'SCH-002']);
    $employee = Employee::factory()->create();

    // Create first assignment starting today with no end date
    EmployeeScheduleAssignment::create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule1->id,
        'effective_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    // Try to create an overlapping assignment on another schedule
    $rules = (new StoreEmployeeScheduleAssignmentRequest)->rules();
    $overlappingData = [
        'employee_id' => $employee->id,
        'effective_date' => now()->addWeek()->toDateString(),
        'end_date' => null,
    ];

    $request = new StoreEmployeeScheduleAssignmentRequest($overlappingData);
    $request->setContainer(app());

    $validator = Validator::make($overlappingData, $rules);

    // Run after validation hook
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('employee_id'))->toBeTrue();
    expect($validator->errors()->first('employee_id'))->toContain('overlapping');
});

/**
 * Test 6: Edge case - Allow schedule deletion when no active employees assigned
 */
it('allows schedule deletion when no active employees are assigned', function () {
    $tenant = Tenant::factory()->create();
    bindTenantForIntegration($tenant);

    $admin = createAdminUserForIntegration($tenant);
    $this->actingAs($admin);

    $schedule = WorkSchedule::factory()->create([
        'name' => 'Deletable Schedule',
        'code' => 'DEL-001',
    ]);

    $employee = Employee::factory()->create();

    // Create an assignment that has already ended (past end date)
    EmployeeScheduleAssignment::create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
        'effective_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->subDay()->toDateString(), // Ended yesterday
    ]);

    // Verify no active assignments
    $activeCount = $schedule->employeeScheduleAssignments()->active()->count();
    expect($activeCount)->toBe(0);

    // Delete should succeed
    $controller = new WorkScheduleController;
    $response = $controller->destroy($schedule);

    expect($response->getStatusCode())->toBe(200);
    expect(WorkSchedule::find($schedule->id))->toBeNull();
});

/**
 * Test 7: Integration - Verify all schedule types appear in page controller
 */
it('provides all four schedule types to the frontend index page', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    bindTenantForIntegration($tenant);

    $admin = createAdminUserForIntegration($tenant);
    $this->actingAs($admin);

    // Create one schedule of each type
    WorkSchedule::factory()->create(['schedule_type' => ScheduleType::Fixed, 'code' => 'FIX-001']);
    WorkSchedule::factory()->flexible()->create(['code' => 'FLX-001']);
    WorkSchedule::factory()->shifting()->create(['code' => 'SHF-001']);
    WorkSchedule::factory()->compressed()->create(['code' => 'CMP-001']);

    $controller = new PageWorkScheduleController;
    $inertiaResponse = $controller->index();

    $reflection = new ReflectionClass($inertiaResponse);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Verify all 4 schedules are returned
    $schedules = $props['schedules']->collection;
    expect($schedules)->toHaveCount(4);

    // Verify all schedule types are represented
    // Query the database directly to verify all types exist
    $dbTypes = WorkSchedule::pluck('schedule_type')->map(fn ($type) => $type->value)->unique()->sort()->values()->toArray();

    expect($dbTypes)->toContain('compressed');
    expect($dbTypes)->toContain('fixed');
    expect($dbTypes)->toContain('flexible');
    expect($dbTypes)->toContain('shifting');
    expect(count($dbTypes))->toBe(4);

    // Verify all 4 schedule type options are provided for the filter
    $scheduleTypes = $props['scheduleTypes'];
    expect($scheduleTypes)->toHaveCount(4);
});

/**
 * Test 8: Integration - Verify night differential configuration is properly saved and retrieved
 */
it('saves and retrieves night differential configuration correctly', function () {
    $tenant = Tenant::factory()->create();
    bindTenantForIntegration($tenant);

    $admin = createAdminUserForIntegration($tenant);
    $this->actingAs($admin);

    $scheduleController = new WorkScheduleController;
    $scheduleData = [
        'name' => 'Night Shift Schedule',
        'code' => 'NIGHT-001',
        'schedule_type' => 'fixed',
        'status' => 'active',
        'time_configuration' => [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'half_day_saturday' => false,
            'start_time' => '22:00',
            'end_time' => '06:00',
            'saturday_end_time' => null,
            'break' => ['start_time' => '02:00', 'duration_minutes' => 30],
        ],
        'overtime_rules' => [
            'daily_threshold_hours' => 8,
            'weekly_threshold_hours' => 40,
            'regular_multiplier' => 1.25,
            'rest_day_multiplier' => 1.30,
            'holiday_multiplier' => 2.0,
        ],
        'night_differential' => [
            'enabled' => true,
            'start_time' => '22:00',
            'end_time' => '06:00',
            'rate_multiplier' => 1.15,
        ],
    ];

    $storeRequest = createIntegrationStoreRequest($scheduleData, $admin);
    $response = $scheduleController->store($storeRequest);

    expect($response->getStatusCode())->toBe(201);

    // Retrieve and verify
    $schedule = WorkSchedule::where('code', 'NIGHT-001')->first();
    expect($schedule)->not->toBeNull();
    expect($schedule->night_differential['enabled'])->toBeTrue();
    expect($schedule->night_differential['start_time'])->toBe('22:00');
    expect($schedule->night_differential['end_time'])->toBe('06:00');
    expect($schedule->night_differential['rate_multiplier'])->toBe(1.15);
});
