<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\LeaveCalendarController;
use App\Http\Controllers\Leave\LeaveCalendarPageController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForCalendar(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForCalendar(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a request with a user.
 */
function createRequestForCalendar(array $params, User $user): Request
{
    $request = Request::create('/leave/calendar', 'GET', $params);
    $request->setUserResolver(fn () => $user);

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

describe('Leave Calendar Page Controller', function () {
    it('returns inertia response with correct component', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        Employee::factory()->create(['user_id' => $user->id]);
        Department::factory()->create();
        LeaveType::factory()->create();

        $request = createRequestForCalendar([], $user);
        $controller = new LeaveCalendarPageController;
        $response = $controller->index($request);

        // Use reflection to access protected properties
        $reflection = new ReflectionClass($response);

        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);
        expect($componentProperty->getValue($response))->toBe('Leave/Calendar/Index');
    });

    it('includes departments and leave types in page props', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        Employee::factory()->create(['user_id' => $user->id]);

        Department::factory()->create(['name' => 'Engineering']);
        LeaveType::factory()->create(['name' => 'Vacation Leave']);

        $request = createRequestForCalendar([], $user);
        $controller = new LeaveCalendarPageController;
        $response = $controller->index($request);

        $reflection = new ReflectionClass($response);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($response);

        expect($props['departments'])->toHaveCount(1);
        expect($props['departments'][0]['name'])->toBe('Engineering');
        expect($props['leaveTypes'])->toHaveCount(1);
        expect($props['leaveTypes'][0]['name'])->toBe('Vacation Leave');
    });

    it('respects query parameters for year and month', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        Employee::factory()->create(['user_id' => $user->id]);

        $request = createRequestForCalendar(['year' => 2025, 'month' => 6], $user);
        $controller = new LeaveCalendarPageController;
        $response = $controller->index($request);

        $reflection = new ReflectionClass($response);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($response);

        expect($props['filters']['year'])->toBe(2025);
        expect($props['filters']['month'])->toBe(6);
    });

    it('includes current employee info in props', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $request = createRequestForCalendar([], $user);
        $controller = new LeaveCalendarPageController;
        $response = $controller->index($request);

        $reflection = new ReflectionClass($response);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($response);

        expect($props['employee'])->not->toBeNull();
        expect($props['employee']['id'])->toBe($employee->id);
    });
});

describe('Leave Calendar API Controller', function () {
    it('returns leave applications for the requested month', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        // Create approved leave for January 2026
        LeaveApplication::factory()
            ->approved()
            ->forDates('2026-01-15', '2026-01-17')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        // Create approved leave for February 2026 (should not appear)
        LeaveApplication::factory()
            ->approved()
            ->forDates('2026-02-10', '2026-02-12')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        $request = createRequestForCalendar(['year' => 2026, 'month' => 1], $user);
        $controller = new LeaveCalendarController;
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
        expect($response->first()->start_date->format('Y-m-d'))->toBe('2026-01-15');
    });

    it('includes both approved and pending leave when show_pending is true', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        // Create approved leave
        LeaveApplication::factory()
            ->approved()
            ->forDates('2026-01-10', '2026-01-10')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        // Create pending leave
        LeaveApplication::factory()
            ->pending()
            ->forDates('2026-01-20', '2026-01-20')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        $request = createRequestForCalendar(['year' => 2026, 'month' => 1, 'show_pending' => 'true'], $user);
        $controller = new LeaveCalendarController;
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('excludes pending leave when show_pending is false', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        // Create approved leave
        LeaveApplication::factory()
            ->approved()
            ->forDates('2026-01-10', '2026-01-10')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        // Create pending leave
        LeaveApplication::factory()
            ->pending()
            ->forDates('2026-01-20', '2026-01-20')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        $request = createRequestForCalendar(['year' => 2026, 'month' => 1, 'show_pending' => 'false'], $user);
        $controller = new LeaveCalendarController;
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
        expect($response->first()->status->value)->toBe('approved');
    });

    it('filters by department when department_id is provided', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        $leaveType = LeaveType::factory()->create();

        $department1 = Department::factory()->create(['name' => 'Engineering']);
        $department2 = Department::factory()->create(['name' => 'Marketing']);

        $employee1 = Employee::factory()->create(['department_id' => $department1->id]);
        $employee2 = Employee::factory()->create(['department_id' => $department2->id]);

        // Create leave for both departments
        LeaveApplication::factory()
            ->approved()
            ->forDates('2026-01-15', '2026-01-15')
            ->create([
                'employee_id' => $employee1->id,
                'leave_type_id' => $leaveType->id,
            ]);

        LeaveApplication::factory()
            ->approved()
            ->forDates('2026-01-20', '2026-01-20')
            ->create([
                'employee_id' => $employee2->id,
                'leave_type_id' => $leaveType->id,
            ]);

        $request = createRequestForCalendar([
            'year' => 2026,
            'month' => 1,
            'department_id' => $department1->id,
        ], $user);
        $controller = new LeaveCalendarController;
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
        expect($response->first()->employee->department_id)->toBe($department1->id);
    });

    it('includes leave that spans across month boundaries', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        // Create leave that starts in December and ends in January
        LeaveApplication::factory()
            ->approved()
            ->forDates('2025-12-28', '2026-01-05')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        // Query for January 2026 - should include the spanning leave
        $request = createRequestForCalendar(['year' => 2026, 'month' => 1], $user);
        $controller = new LeaveCalendarController;
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });

    it('excludes draft and rejected leave from calendar', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::factory()->create();

        // Create approved leave
        LeaveApplication::factory()
            ->approved()
            ->forDates('2026-01-10', '2026-01-10')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        // Create draft leave (should not appear)
        LeaveApplication::factory()
            ->draft()
            ->forDates('2026-01-15', '2026-01-15')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        // Create rejected leave (should not appear)
        LeaveApplication::factory()
            ->rejected()
            ->forDates('2026-01-20', '2026-01-20')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
            ]);

        $request = createRequestForCalendar(['year' => 2026, 'month' => 1, 'show_pending' => 'true'], $user);
        $controller = new LeaveCalendarController;
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
        expect($response->first()->status->value)->toBe('approved');
    });
});

describe('Leave Calendar Entry Resource', function () {
    it('returns correct resource structure with initials', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantContextForCalendar($tenant);

        $user = createTenantUserForCalendar($tenant, TenantUserRole::HrStaff);
        $department = Department::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $leaveType = LeaveType::factory()->create();

        LeaveApplication::factory()
            ->approved()
            ->forDates('2026-01-15', '2026-01-17')
            ->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'reason' => 'Family vacation',
            ]);

        $request = createRequestForCalendar(['year' => 2026, 'month' => 1], $user);
        $controller = new LeaveCalendarController;
        $response = $controller->index($request);

        // Get the first resource
        $entry = $response->first();
        $resourceData = $entry->toArray(request());

        expect($resourceData)->toHaveKeys([
            'id',
            'employee',
            'leave_type',
            'start_date',
            'end_date',
            'total_days',
            'is_half_day_start',
            'is_half_day_end',
            'status',
            'status_label',
            'reason',
            'reference_number',
        ]);

        expect($resourceData['employee'])->toHaveKeys([
            'id',
            'full_name',
            'initials',
            'department_id',
            'department',
        ]);

        expect($resourceData['leave_type'])->toHaveKeys([
            'id',
            'name',
            'code',
            'category',
        ]);

        // Verify initials
        expect($resourceData['employee']['initials'])->toBe('JD');
    });
});
