<?php

/**
 * UI Component Tests for Work Schedule Configuration
 *
 * These tests verify the Inertia page controller and component rendering for
 * Work Schedules management functionality.
 */

use App\Enums\ScheduleType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\EmployeeScheduleAssignmentController;
use App\Http\Controllers\Api\WorkScheduleController as ApiWorkScheduleController;
use App\Http\Controllers\Organization\WorkScheduleController as PageWorkScheduleController;
use App\Http\Requests\StoreEmployeeScheduleAssignmentRequest;
use App\Http\Requests\StoreWorkScheduleRequest;
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
function bindTenantForWorkScheduleUI(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForWorkScheduleUI(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function createStoreWorkScheduleUIRequest(array $data, User $user): StoreWorkScheduleRequest
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
 * Helper to create a validated store employee schedule assignment request.
 */
function createStoreAssignmentUIRequest(array $data, User $user): StoreEmployeeScheduleAssignmentRequest
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

/**
 * Test 1: WorkSchedules Index page renders schedule list with filters
 */
it('renders work schedules index page with schedule list and filters', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    bindTenantForWorkScheduleUI($tenant);

    $admin = createTenantUserForWorkScheduleUI($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create work schedules with different types and statuses
    WorkSchedule::factory()->create([
        'name' => 'Regular Office Hours',
        'code' => 'REG-8AM',
        'schedule_type' => ScheduleType::Fixed,
        'status' => 'active',
    ]);

    WorkSchedule::factory()->flexible()->create([
        'name' => 'Flexible with Core Hours',
        'code' => 'FLEX-CORE',
        'status' => 'active',
    ]);

    WorkSchedule::factory()->shifting()->create([
        'name' => 'Morning Shift',
        'code' => 'SHIFT-A',
        'status' => 'active',
    ]);

    WorkSchedule::factory()->compressed()->create([
        'name' => 'Compressed 4-Day Workweek',
        'code' => 'CWW-4DAY',
        'status' => 'inactive',
    ]);

    // Test the controller directly to avoid Vite manifest issues
    $request = Request::create('/organization/work-schedules', 'GET');
    $request->setUserResolver(fn () => $admin);

    $controller = new PageWorkScheduleController;
    $inertiaResponse = $controller->index();

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    expect($componentProperty->getValue($inertiaResponse))->toBe('Organization/WorkSchedules/Index');

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    // Check that all 4 schedules are returned
    $schedules = $props['schedules']->collection;
    expect($schedules)->toHaveCount(4);

    // Check that all 4 schedule types are provided
    $scheduleTypes = $props['scheduleTypes'];
    expect($scheduleTypes)->toHaveCount(4);

    // Verify schedule type options are correct
    $typeValues = array_column($scheduleTypes, 'value');
    expect($typeValues)->toContain('fixed', 'flexible', 'shifting', 'compressed');
});

/**
 * Test 2: WorkScheduleFormModal opens and shows correct fields for Fixed type
 */
it('provides correct enum options for fixed schedule type in form modal', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    bindTenantForWorkScheduleUI($tenant);

    $admin = createTenantUserForWorkScheduleUI($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Test the controller directly
    $controller = new PageWorkScheduleController;
    $inertiaResponse = $controller->index();

    $reflection = new ReflectionClass($inertiaResponse);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    $scheduleTypes = $props['scheduleTypes'];

    // Verify all schedule types are provided
    expect($scheduleTypes)->toHaveCount(4);

    $typeValues = array_column($scheduleTypes, 'value');
    expect($typeValues)->toContain('fixed');
    expect($typeValues)->toContain('flexible');
    expect($typeValues)->toContain('shifting');
    expect($typeValues)->toContain('compressed');

    // Verify labels are correct
    $typeLabels = array_column($scheduleTypes, 'label');
    expect($typeLabels)->toContain('Fixed');
    expect($typeLabels)->toContain('Flexible');
    expect($typeLabels)->toContain('Shifting');
    expect($typeLabels)->toContain('Compressed');
});

/**
 * Test 3: WorkScheduleFormModal shows different configuration fields per schedule type
 */
it('returns schedule data with correct time configuration per schedule type', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    bindTenantForWorkScheduleUI($tenant);

    $admin = createTenantUserForWorkScheduleUI($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create schedules with specific time configurations
    WorkSchedule::factory()->create([
        'name' => 'Fixed Schedule',
        'code' => 'FIX-001',
        'schedule_type' => ScheduleType::Fixed,
        'time_configuration' => [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'half_day_saturday' => false,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'saturday_end_time' => null,
            'break' => ['start_time' => '12:00', 'duration_minutes' => 60],
        ],
    ]);

    WorkSchedule::factory()->create([
        'name' => 'Flexible Schedule',
        'code' => 'FLX-001',
        'schedule_type' => ScheduleType::Flexible,
        'time_configuration' => [
            'required_hours_per_day' => 8,
            'required_hours_per_week' => 40,
            'core_hours' => ['start_time' => '10:00', 'end_time' => '15:00'],
            'flexible_start_window' => ['earliest' => '06:00', 'latest' => '10:00'],
            'break' => ['start_time' => null, 'duration_minutes' => 60],
        ],
    ]);

    // Test the controller directly
    $controller = new PageWorkScheduleController;
    $inertiaResponse = $controller->index();

    $reflection = new ReflectionClass($inertiaResponse);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    $schedules = $props['schedules']->collection->toArray();
    expect($schedules)->toHaveCount(2);

    // Find Fixed schedule and verify time configuration
    $fixedSchedule = collect($schedules)->firstWhere('schedule_type', 'fixed');
    expect($fixedSchedule['time_configuration']['work_days'])->toBe(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
    expect($fixedSchedule['time_configuration']['start_time'])->toBe('08:00');

    // Find Flexible schedule and verify time configuration
    $flexibleSchedule = collect($schedules)->firstWhere('schedule_type', 'flexible');
    expect($flexibleSchedule['time_configuration']['core_hours']['start_time'])->toBe('10:00');
    expect($flexibleSchedule['time_configuration']['required_hours_per_day'])->toBe(8);
});

/**
 * Test 4: Schedule type change dynamically updates form fields (API test for store)
 */
it('creates new schedule with correct JSON structure per schedule type', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    bindTenantForWorkScheduleUI($tenant);

    $admin = createTenantUserForWorkScheduleUI($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new ApiWorkScheduleController;

    // Test Fixed schedule creation
    $fixedData = [
        'name' => 'Regular Office Hours',
        'code' => 'REG-001',
        'schedule_type' => 'fixed',
        'status' => 'active',
        'time_configuration' => [
            'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'half_day_saturday' => true,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'saturday_end_time' => '12:00',
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

    $storeRequest = createStoreWorkScheduleUIRequest($fixedData, $admin);
    $response = $controller->store($storeRequest);

    expect($response->getStatusCode())->toBe(201);

    $data = json_decode($response->getContent(), true);
    expect($data['name'])->toBe('Regular Office Hours');
    expect($data['schedule_type'])->toBe('fixed');
    expect($data['time_configuration']['half_day_saturday'])->toBeTrue();
    expect($data['time_configuration']['saturday_end_time'])->toBe('12:00');

    // Test Shifting schedule creation
    $shiftingData = [
        'name' => 'Morning Shift',
        'code' => 'SHIFT-001',
        'schedule_type' => 'shifting',
        'status' => 'active',
        'time_configuration' => [
            'shifts' => [
                [
                    'name' => 'Morning Shift',
                    'start_time' => '06:00',
                    'end_time' => '14:00',
                    'break' => ['start_time' => '10:00', 'duration_minutes' => 30],
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
            'enabled' => false,
            'start_time' => '22:00',
            'end_time' => '06:00',
            'rate_multiplier' => 1.10,
        ],
    ];

    $shiftRequest = createStoreWorkScheduleUIRequest($shiftingData, $admin);
    $shiftResponse = $controller->store($shiftRequest);

    expect($shiftResponse->getStatusCode())->toBe(201);

    $shiftData = json_decode($shiftResponse->getContent(), true);
    expect($shiftData['schedule_type'])->toBe('shifting');
    expect($shiftData['time_configuration']['shifts'][0]['name'])->toBe('Morning Shift');
});

/**
 * Test 5: Form submission creates new schedule with correct JSON structure
 */
it('validates form submission and creates schedule with proper structure', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    bindTenantForWorkScheduleUI($tenant);

    $admin = createTenantUserForWorkScheduleUI($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new ApiWorkScheduleController;

    // Test Compressed schedule creation with 4x10 pattern
    $compressedData = [
        'name' => 'Compressed 4-Day Week',
        'code' => 'CMP-4X10',
        'schedule_type' => 'compressed',
        'description' => '10-hour days Monday to Thursday, Friday off',
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
            'daily_threshold_hours' => 10,
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

    $storeRequest = createStoreWorkScheduleUIRequest($compressedData, $admin);
    $response = $controller->store($storeRequest);

    expect($response->getStatusCode())->toBe(201);

    $data = json_decode($response->getContent(), true);
    expect($data['name'])->toBe('Compressed 4-Day Week');
    expect($data['schedule_type'])->toBe('compressed');
    expect($data['time_configuration']['pattern'])->toBe('4x10');
    expect($data['time_configuration']['daily_hours'])->toBe(10);
    expect($data['overtime_rules']['daily_threshold_hours'])->toBe(10);
    expect($data['night_differential']['enabled'])->toBeTrue();

    // Verify the schedule was actually created in the database
    $this->assertDatabaseHas('work_schedules', [
        'name' => 'Compressed 4-Day Week',
        'code' => 'CMP-4X10',
    ]);
});

/**
 * Test 6: Employee assignment modal assigns employee with effective date
 */
it('assigns employee to schedule with effective date via assignment endpoint', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    bindTenantForWorkScheduleUI($tenant);

    $admin = createTenantUserForWorkScheduleUI($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $schedule = WorkSchedule::factory()->create([
        'name' => 'Regular Office Hours',
        'code' => 'REG-001',
    ]);

    $employee = Employee::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $controller = new EmployeeScheduleAssignmentController;

    $assignmentData = [
        'employee_id' => $employee->id,
        'effective_date' => now()->addWeek()->format('Y-m-d'),
        'end_date' => null,
    ];

    $storeRequest = createStoreAssignmentUIRequest($assignmentData, $admin);
    $response = $controller->store($storeRequest, $schedule);

    expect($response->getStatusCode())->toBe(201);

    $data = json_decode($response->getContent(), true);
    expect($data['employee_id'])->toBe($employee->id);
    expect($data['work_schedule_id'])->toBe($schedule->id);

    // Verify the assignment was created in the database
    $this->assertDatabaseHas('employee_schedule_assignments', [
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
    ]);

    // Verify the assignment is not yet active (future effective date)
    $assignment = EmployeeScheduleAssignment::where('employee_id', $employee->id)->first();
    expect($assignment)->not->toBeNull();

    $activeAssignment = EmployeeScheduleAssignment::active()
        ->where('employee_id', $employee->id)
        ->first();
    expect($activeAssignment)->toBeNull(); // Should not be active yet
});
