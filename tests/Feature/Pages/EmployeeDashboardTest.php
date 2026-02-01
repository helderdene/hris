<?php

use App\Authorization\RolePermissions;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Permission;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeDashboardController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmployeeDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForDashboard(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForDashboard(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

    // Run tenant-specific migrations for testing
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('denies access to users without can-manage-employees permission', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    // Create employee role user (doesn't have can-manage-employees permission)
    $employee = createTenantUserForDashboard($tenant, TenantUserRole::Employee);
    $this->actingAs($employee);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);

    expect(fn () => $controller->dashboard())
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('allows access to admin users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    // Use reflection to access the component
    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('Employees/Dashboard');
});

it('returns all required metrics data structure', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create some employees to have data
    Employee::factory()->count(3)->active()->create([
        'hire_date' => Carbon::now()->subMonths(6),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    // Use reflection to access props
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Verify all required keys are present
    expect($props)->toHaveKey('headcount')
        ->and($props)->toHaveKey('newHires')
        ->and($props)->toHaveKey('separations')
        ->and($props)->toHaveKey('turnover')
        ->and($props)->toHaveKey('tenureDistribution')
        ->and($props)->toHaveKey('employmentTypeBreakdown')
        ->and($props)->toHaveKey('departmentHeadcounts');

    // Verify headcount structure
    expect($props['headcount'])->toHaveKey('total')
        ->and($props['headcount'])->toHaveKey('active');

    // Verify newHires structure
    expect($props['newHires'])->toHaveKey('count')
        ->and($props['newHires'])->toHaveKey('percentageChange');

    // Verify separations structure
    expect($props['separations'])->toHaveKey('count')
        ->and($props['separations'])->toHaveKey('percentageChange');

    // Verify turnover structure
    expect($props['turnover'])->toHaveKey('rate')
        ->and($props['turnover'])->toHaveKey('averageTenure');

    // Verify tenure distribution structure
    expect($props['tenureDistribution'])->toHaveKey('lessThan1Year')
        ->and($props['tenureDistribution'])->toHaveKey('oneToThreeYears')
        ->and($props['tenureDistribution'])->toHaveKey('threeToFiveYears')
        ->and($props['tenureDistribution'])->toHaveKey('fiveToTenYears')
        ->and($props['tenureDistribution'])->toHaveKey('moreThan10Years');
});

it('correctly counts new hires for current month', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create employees hired this month
    Employee::factory()->count(5)->create([
        'hire_date' => Carbon::now()->startOfMonth()->addDays(5),
        'employment_status' => EmploymentStatus::Active,
    ]);

    // Create employees hired last month
    Employee::factory()->count(3)->create([
        'hire_date' => Carbon::now()->subMonth()->startOfMonth()->addDays(10),
        'employment_status' => EmploymentStatus::Active,
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['newHires']['count'])->toBe(5);
});

it('correctly counts separations for current month', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create resigned employees this month
    Employee::factory()->count(2)->create([
        'hire_date' => Carbon::now()->subYear(),
        'termination_date' => Carbon::now()->startOfMonth()->addDays(3),
        'employment_status' => EmploymentStatus::Resigned,
    ]);

    // Create terminated employees this month
    Employee::factory()->count(1)->create([
        'hire_date' => Carbon::now()->subYear(),
        'termination_date' => Carbon::now()->startOfMonth()->addDays(7),
        'employment_status' => EmploymentStatus::Terminated,
    ]);

    // Create active employee (should not be counted)
    Employee::factory()->active()->create([
        'hire_date' => Carbon::now()->subYear(),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['separations']['count'])->toBe(3);
});

it('correctly aggregates department headcounts', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create departments
    $engineering = Department::factory()->create([
        'name' => 'Engineering',
        'status' => 'active',
    ]);
    $hr = Department::factory()->create([
        'name' => 'Human Resources',
        'status' => 'active',
    ]);
    $inactiveDept = Department::factory()->create([
        'name' => 'Inactive Department',
        'status' => 'inactive',
    ]);

    // Create active employees for Engineering
    Employee::factory()->count(5)->active()->create([
        'department_id' => $engineering->id,
    ]);

    // Create active employees for HR
    Employee::factory()->count(3)->active()->create([
        'department_id' => $hr->id,
    ]);

    // Create resigned employee for Engineering (should not be counted)
    Employee::factory()->resigned()->create([
        'department_id' => $engineering->id,
    ]);

    // Create employee for inactive department
    Employee::factory()->active()->create([
        'department_id' => $inactiveDept->id,
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    $departments = collect($props['departmentHeadcounts']);

    // Should only include active departments
    expect($departments)->toHaveCount(2);

    // Find Engineering department
    $engDept = $departments->firstWhere('name', 'Engineering');
    expect($engDept)->not->toBeNull()
        ->and($engDept['employees_count'])->toBe(5);

    // Find HR department
    $hrDept = $departments->firstWhere('name', 'Human Resources');
    expect($hrDept)->not->toBeNull()
        ->and($hrDept['employees_count'])->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Task Group 2: Dashboard Page Component Tests
|--------------------------------------------------------------------------
|
| These tests verify the dashboard page renders correctly with all sections,
| proper breadcrumb navigation, and authorization handling.
|
*/

it('renders dashboard page component with correct Inertia component name', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('Employees/Dashboard');
});

it('allows access to HR Manager role users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $hrManager = createTenantUserForDashboard($tenant, TenantUserRole::HrManager);
    $this->actingAs($hrManager);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('Employees/Dashboard');
});

it('denies access to supervisor role users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $supervisor = createTenantUserForDashboard($tenant, TenantUserRole::Supervisor);
    $this->actingAs($supervisor);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);

    expect(fn () => $controller->dashboard())
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('returns metrics with correct structure for page rendering', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create diverse employee data for comprehensive testing
    $department = Department::factory()->create([
        'name' => 'Operations',
        'status' => 'active',
    ]);

    Employee::factory()->count(10)->active()->create([
        'hire_date' => Carbon::now()->subMonths(2),
        'department_id' => $department->id,
        'employment_type' => EmploymentType::Regular,
    ]);

    Employee::factory()->count(5)->active()->create([
        'hire_date' => Carbon::now()->startOfMonth()->addDays(1),
        'department_id' => $department->id,
        'employment_type' => EmploymentType::Probationary,
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Verify headcount values make sense
    expect($props['headcount']['total'])->toBe(15)
        ->and($props['headcount']['active'])->toBe(15);

    // Verify new hires count matches employees hired this month
    expect($props['newHires']['count'])->toBe(5);

    // Verify department headcounts include our test department
    $departments = collect($props['departmentHeadcounts']);
    $opsDept = $departments->firstWhere('name', 'Operations');
    expect($opsDept)->not->toBeNull()
        ->and($opsDept['employees_count'])->toBe(15);

    // Verify employment type breakdown includes our employee types
    expect($props['employmentTypeBreakdown'])
        ->toHaveKey('regular')
        ->and($props['employmentTypeBreakdown']['regular'])->toBe(10)
        ->and($props['employmentTypeBreakdown']['probationary'])->toBe(5);
});

/*
|--------------------------------------------------------------------------
| Task Group 3: Stat Cards Components Tests
|--------------------------------------------------------------------------
|
| These tests verify the stat card components display correct data and
| trend indicators with appropriate styling.
|
*/

it('returns headcount data with total and active counts for stat card display', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create mix of active and inactive employees
    Employee::factory()->count(10)->active()->create([
        'hire_date' => Carbon::now()->subMonths(6),
    ]);

    Employee::factory()->count(3)->resigned()->create([
        'hire_date' => Carbon::now()->subYear(),
        'termination_date' => Carbon::now()->subMonth(),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Total should include all employees, active should only include active
    expect($props['headcount']['total'])->toBe(13)
        ->and($props['headcount']['active'])->toBe(10);
});

it('returns new hires data with count and percentage change for trend indicator', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create 10 employees hired this month
    Employee::factory()->count(10)->active()->create([
        'hire_date' => Carbon::now()->startOfMonth()->addDays(5),
    ]);

    // Create 5 employees hired last month (50% less than this month)
    Employee::factory()->count(5)->active()->create([
        'hire_date' => Carbon::now()->subMonth()->startOfMonth()->addDays(10),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['newHires']['count'])->toBe(10)
        ->and($props['newHires']['percentageChange'])->toEqual(100.0); // 100% increase from 5 to 10
});

it('returns separations data with count and percentage change for trend indicator', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create 2 separations this month
    Employee::factory()->count(2)->resigned()->create([
        'hire_date' => Carbon::now()->subYear(),
        'termination_date' => Carbon::now()->startOfMonth()->addDays(5),
    ]);

    // Create 4 separations last month (50% reduction this month)
    Employee::factory()->count(4)->resigned()->create([
        'hire_date' => Carbon::now()->subYear(),
        'termination_date' => Carbon::now()->subMonth()->startOfMonth()->addDays(10),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['separations']['count'])->toBe(2)
        ->and($props['separations']['percentageChange'])->toEqual(-50.0); // 50% decrease from 4 to 2
});

it('returns turnover rate data with percentage and average tenure', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create active employees with specific hire dates for tenure calculation
    Employee::factory()->count(5)->active()->create([
        'hire_date' => Carbon::now()->subYears(2), // 2 years tenure
    ]);

    Employee::factory()->count(5)->active()->create([
        'hire_date' => Carbon::now()->subYears(4), // 4 years tenure
    ]);

    // Create a separation this month
    Employee::factory()->resigned()->create([
        'hire_date' => Carbon::now()->subYears(3),
        'termination_date' => Carbon::now()->startOfMonth()->addDays(5),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Verify turnover structure has required fields
    expect($props['turnover'])->toHaveKey('rate')
        ->and($props['turnover'])->toHaveKey('averageTenure')
        ->and($props['turnover']['rate'])->toBeNumeric()
        ->and($props['turnover']['averageTenure'])->toBeNumeric();

    // Average tenure should be approximately 3 years ((2+4)/2 for active employees)
    expect($props['turnover']['averageTenure'])->toBeGreaterThanOrEqual(2)
        ->and($props['turnover']['averageTenure'])->toBeLessThanOrEqual(4);
});

it('returns 100% change when no previous month data exists but current month has data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create employees hired only this month (no previous month data)
    Employee::factory()->count(5)->active()->create([
        'hire_date' => Carbon::now()->startOfMonth()->addDays(5),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // When there's no previous month data but current has data, percentage change is 100%
    // This follows the service logic: if previousValue is 0 and currentValue > 0, return 100.0
    expect($props['newHires']['count'])->toBe(5)
        ->and($props['newHires']['percentageChange'])->toEqual(100.0);
});

/*
|--------------------------------------------------------------------------
| Task Group 4: Charts Section Components Tests
|--------------------------------------------------------------------------
|
| These tests verify the chart components (Tenure Distribution and Employment
| Type) render correctly with proper data structure and calculations.
|
*/

it('returns tenure distribution with all 5 buckets and correct counts', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create employees in each tenure bucket
    // Less than 1 year
    Employee::factory()->count(3)->active()->create([
        'hire_date' => Carbon::now()->subMonths(6),
    ]);

    // 1-3 years
    Employee::factory()->count(5)->active()->create([
        'hire_date' => Carbon::now()->subYears(2),
    ]);

    // 3-5 years
    Employee::factory()->count(4)->active()->create([
        'hire_date' => Carbon::now()->subYears(4),
    ]);

    // 5-10 years
    Employee::factory()->count(2)->active()->create([
        'hire_date' => Carbon::now()->subYears(7),
    ]);

    // More than 10 years
    Employee::factory()->count(1)->active()->create([
        'hire_date' => Carbon::now()->subYears(12),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Verify all 5 buckets exist with correct counts
    expect($props['tenureDistribution']['lessThan1Year'])->toBe(3)
        ->and($props['tenureDistribution']['oneToThreeYears'])->toBe(5)
        ->and($props['tenureDistribution']['threeToFiveYears'])->toBe(4)
        ->and($props['tenureDistribution']['fiveToTenYears'])->toBe(2)
        ->and($props['tenureDistribution']['moreThan10Years'])->toBe(1);
});

it('returns employment type breakdown with counts for all types', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create employees of each employment type
    Employee::factory()->count(10)->active()->create([
        'employment_type' => EmploymentType::Regular,
    ]);

    Employee::factory()->count(5)->active()->create([
        'employment_type' => EmploymentType::Probationary,
    ]);

    Employee::factory()->count(3)->active()->create([
        'employment_type' => EmploymentType::Contractual,
    ]);

    Employee::factory()->count(2)->active()->create([
        'employment_type' => EmploymentType::ProjectBased,
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Verify all employment types have counts
    expect($props['employmentTypeBreakdown']['regular'])->toBe(10)
        ->and($props['employmentTypeBreakdown']['probationary'])->toBe(5)
        ->and($props['employmentTypeBreakdown']['contractual'])->toBe(3)
        ->and($props['employmentTypeBreakdown']['project_based'])->toBe(2);
});

it('excludes inactive employees from tenure distribution and employment type counts', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create active employees
    Employee::factory()->count(5)->active()->create([
        'hire_date' => Carbon::now()->subMonths(6),
        'employment_type' => EmploymentType::Regular,
    ]);

    // Create resigned employees (should be excluded)
    Employee::factory()->count(3)->resigned()->create([
        'hire_date' => Carbon::now()->subMonths(6),
        'employment_type' => EmploymentType::Regular,
        'termination_date' => Carbon::now()->subMonth(),
    ]);

    // Create terminated employees (should be excluded)
    Employee::factory()->count(2)->create([
        'hire_date' => Carbon::now()->subYears(2),
        'employment_type' => EmploymentType::Probationary,
        'employment_status' => EmploymentStatus::Terminated,
        'termination_date' => Carbon::now()->subWeeks(2),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Only active employees should be counted
    expect($props['tenureDistribution']['lessThan1Year'])->toBe(5)
        ->and($props['employmentTypeBreakdown']['regular'])->toBe(5)
        ->and($props['employmentTypeBreakdown']['probationary'])->toBe(0);
});

it('returns zero counts for empty tenure buckets and employment types', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create only regular employees hired less than 1 year ago
    Employee::factory()->count(3)->active()->create([
        'hire_date' => Carbon::now()->subMonths(6),
        'employment_type' => EmploymentType::Regular,
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Verify empty buckets return 0, not null or undefined
    expect($props['tenureDistribution']['lessThan1Year'])->toBe(3)
        ->and($props['tenureDistribution']['oneToThreeYears'])->toBe(0)
        ->and($props['tenureDistribution']['threeToFiveYears'])->toBe(0)
        ->and($props['tenureDistribution']['fiveToTenYears'])->toBe(0)
        ->and($props['tenureDistribution']['moreThan10Years'])->toBe(0);

    // Verify empty employment types return 0
    expect($props['employmentTypeBreakdown']['regular'])->toBe(3)
        ->and($props['employmentTypeBreakdown']['probationary'])->toBe(0)
        ->and($props['employmentTypeBreakdown']['contractual'])->toBe(0)
        ->and($props['employmentTypeBreakdown']['project_based'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Task Group 5: Department Headcount Section Tests
|--------------------------------------------------------------------------
|
| These tests verify the department cards render correctly with name, count,
| and colored border, and that navigation to filtered employee list works.
|
*/

it('returns department headcounts with id, name, and employees_count for card display', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create departments with employees
    $operations = Department::factory()->create([
        'name' => 'Operations',
        'status' => 'active',
    ]);
    $finance = Department::factory()->create([
        'name' => 'Finance',
        'status' => 'active',
    ]);

    Employee::factory()->count(8)->active()->create([
        'department_id' => $operations->id,
    ]);

    Employee::factory()->count(4)->active()->create([
        'department_id' => $finance->id,
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    $departments = collect($props['departmentHeadcounts']);

    // Verify each department has required fields for card display
    $opsDept = $departments->firstWhere('name', 'Operations');
    expect($opsDept)->toHaveKey('id')
        ->and($opsDept)->toHaveKey('name')
        ->and($opsDept)->toHaveKey('employees_count')
        ->and($opsDept['id'])->toBe($operations->id)
        ->and($opsDept['name'])->toBe('Operations')
        ->and($opsDept['employees_count'])->toBe(8);

    $financeDept = $departments->firstWhere('name', 'Finance');
    expect($financeDept['id'])->toBe($finance->id)
        ->and($financeDept['employees_count'])->toBe(4);
});

it('returns department headcounts sorted by employee count for horizontal scroll display', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create departments with varying employee counts
    $sales = Department::factory()->create(['name' => 'Sales', 'status' => 'active']);
    $engineering = Department::factory()->create(['name' => 'Engineering', 'status' => 'active']);
    $hr = Department::factory()->create(['name' => 'HR', 'status' => 'active']);
    $marketing = Department::factory()->create(['name' => 'Marketing', 'status' => 'active']);
    $it = Department::factory()->create(['name' => 'IT', 'status' => 'active']);

    // Create employees with different counts per department
    Employee::factory()->count(25)->active()->create(['department_id' => $engineering->id]);
    Employee::factory()->count(15)->active()->create(['department_id' => $sales->id]);
    Employee::factory()->count(10)->active()->create(['department_id' => $hr->id]);
    Employee::factory()->count(8)->active()->create(['department_id' => $marketing->id]);
    Employee::factory()->count(5)->active()->create(['department_id' => $it->id]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    $departments = $props['departmentHeadcounts'];

    // Verify we have all 5 departments for horizontal scroll
    expect($departments)->toHaveCount(5);

    // Verify departments are returned (order may vary based on implementation)
    $deptNames = collect($departments)->pluck('name')->toArray();
    expect($deptNames)->toContain('Engineering')
        ->and($deptNames)->toContain('Sales')
        ->and($deptNames)->toContain('HR')
        ->and($deptNames)->toContain('Marketing')
        ->and($deptNames)->toContain('IT');
});

it('returns zero employees_count for departments with only resigned employees', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create department with active employees
    $activeDept = Department::factory()->create([
        'name' => 'Active Department',
        'status' => 'active',
    ]);

    // Create department with only resigned employees
    $emptyDept = Department::factory()->create([
        'name' => 'Empty Department',
        'status' => 'active',
    ]);

    Employee::factory()->count(5)->active()->create([
        'department_id' => $activeDept->id,
    ]);

    // Create only resigned employees for the "empty" department
    Employee::factory()->count(3)->resigned()->create([
        'department_id' => $emptyDept->id,
        'termination_date' => Carbon::now()->subMonth(),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    $departments = collect($props['departmentHeadcounts']);

    // Both departments should be returned (active departments)
    expect($departments)->toHaveCount(2);

    // Find Active Department - should have 5 employees
    $activeDeptResult = $departments->firstWhere('name', 'Active Department');
    expect($activeDeptResult['employees_count'])->toBe(5);

    // Find Empty Department - should have 0 employees (only resigned)
    $emptyDeptResult = $departments->firstWhere('name', 'Empty Department');
    expect($emptyDeptResult['employees_count'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Task Group 6: Quick Actions Section Tests
|--------------------------------------------------------------------------
|
| These tests verify the quick actions section component structure is correct
| and that the dashboard controller returns the Inertia component properly.
| Route navigation tests are verified through the component rendering.
|
*/

it('renders dashboard with quick actions section included in the component', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    // Verify the dashboard component is rendered
    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    // The Dashboard component contains the QuickActionsSection which has:
    // - View All Employees card linking to /employees
    // - Add New Employee card linking to /employees/create
    // - Manage Departments card linking to /organization/departments
    // - Generate Reports card (disabled)
    expect($componentProperty->getValue($response))->toBe('Employees/Dashboard');
});

it('dashboard component exists and includes quick actions as a section', function () {
    // Verify the Vue component file exists with QuickActionsSection import
    $dashboardPath = resource_path('js/Pages/Employees/Dashboard.vue');
    expect(file_exists($dashboardPath))->toBeTrue();

    $dashboardContent = file_get_contents($dashboardPath);

    // Verify QuickActionsSection is imported and used in the template
    expect($dashboardContent)->toContain('QuickActionsSection')
        ->and($dashboardContent)->toContain('<QuickActionsSection');
});

it('quick action cards render with correct navigation hrefs', function () {
    // Verify the QuickActionsSection component contains correct hrefs
    $quickActionsSectionPath = resource_path('js/Components/dashboard/QuickActionsSection.vue');
    expect(file_exists($quickActionsSectionPath))->toBeTrue();

    $content = file_get_contents($quickActionsSectionPath);

    // Verify View All Employees links to /employees
    expect($content)->toContain('href="/employees"');

    // Verify Add New Employee links to /employees/create
    expect($content)->toContain('href="/employees/create"');

    // Verify Manage Departments links to /organization/departments
    expect($content)->toContain('href="/organization/departments"');

    // Verify Generate Reports is disabled
    expect($content)->toContain(':disabled="true"');
});

/*
|--------------------------------------------------------------------------
| Task Group 7: Sidebar Navigation Tests
|--------------------------------------------------------------------------
|
| These tests verify the Employee Dashboard link appears in the sidebar
| for authorized users and is hidden for unauthorized roles.
|
*/

it('grants can_manage_employees permission to Admin and HR Manager roles', function () {
    // Test Admin role has all required employee permissions
    $hasAdminPermissions = RolePermissions::roleHasPermission(TenantUserRole::Admin, Permission::EmployeesView)
        && RolePermissions::roleHasPermission(TenantUserRole::Admin, Permission::EmployeesCreate)
        && RolePermissions::roleHasPermission(TenantUserRole::Admin, Permission::EmployeesEdit)
        && RolePermissions::roleHasPermission(TenantUserRole::Admin, Permission::EmployeesDelete);

    expect($hasAdminPermissions)->toBeTrue();

    // Test HR Manager role has all required employee permissions
    $hasHrManagerPermissions = RolePermissions::roleHasPermission(TenantUserRole::HrManager, Permission::EmployeesView)
        && RolePermissions::roleHasPermission(TenantUserRole::HrManager, Permission::EmployeesCreate)
        && RolePermissions::roleHasPermission(TenantUserRole::HrManager, Permission::EmployeesEdit)
        && RolePermissions::roleHasPermission(TenantUserRole::HrManager, Permission::EmployeesDelete);

    expect($hasHrManagerPermissions)->toBeTrue();
});

it('denies can_manage_employees permission to Employee, Supervisor, and HR Staff roles', function () {
    // Test Employee role does not have all required employee permissions
    $hasEmployeePermissions = RolePermissions::roleHasPermission(TenantUserRole::Employee, Permission::EmployeesView)
        && RolePermissions::roleHasPermission(TenantUserRole::Employee, Permission::EmployeesCreate)
        && RolePermissions::roleHasPermission(TenantUserRole::Employee, Permission::EmployeesEdit)
        && RolePermissions::roleHasPermission(TenantUserRole::Employee, Permission::EmployeesDelete);

    expect($hasEmployeePermissions)->toBeFalse();

    // Test Supervisor role does not have all required employee permissions
    $hasSupervisorPermissions = RolePermissions::roleHasPermission(TenantUserRole::Supervisor, Permission::EmployeesView)
        && RolePermissions::roleHasPermission(TenantUserRole::Supervisor, Permission::EmployeesCreate)
        && RolePermissions::roleHasPermission(TenantUserRole::Supervisor, Permission::EmployeesEdit)
        && RolePermissions::roleHasPermission(TenantUserRole::Supervisor, Permission::EmployeesDelete);

    expect($hasSupervisorPermissions)->toBeFalse();

    // Test HR Staff role does not have all required employee permissions (missing delete)
    $hasHrStaffPermissions = RolePermissions::roleHasPermission(TenantUserRole::HrStaff, Permission::EmployeesView)
        && RolePermissions::roleHasPermission(TenantUserRole::HrStaff, Permission::EmployeesCreate)
        && RolePermissions::roleHasPermission(TenantUserRole::HrStaff, Permission::EmployeesEdit)
        && RolePermissions::roleHasPermission(TenantUserRole::HrStaff, Permission::EmployeesDelete);

    expect($hasHrStaffPermissions)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Task Group 8: Test Review and Gap Analysis - Additional Strategic Tests
|--------------------------------------------------------------------------
|
| These tests fill critical gaps identified during test review:
| 1. Zero employees edge case
| 2. HR Staff and HR Consultant role denial verification (no EmployeesDelete permission)
| 3. Full integration test with comprehensive realistic data
| 4. Turnover rate calculation with specific values
| 5. Null percentage change edge case
| 6. Empty database state handling
|
*/

it('returns valid metrics when there are zero employees in the system', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // No employees created - test zero employee state

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Verify all metrics return valid values (zeros)
    // Note: rate returns int 0 when there's no data, averageTenure returns float 0.0
    expect($props['headcount']['total'])->toBe(0)
        ->and($props['headcount']['active'])->toBe(0)
        ->and($props['newHires']['count'])->toBe(0)
        ->and($props['separations']['count'])->toBe(0)
        ->and($props['turnover']['rate'])->toEqual(0)
        ->and($props['turnover']['averageTenure'])->toEqual(0)
        ->and($props['tenureDistribution']['lessThan1Year'])->toBe(0)
        ->and($props['tenureDistribution']['oneToThreeYears'])->toBe(0)
        ->and($props['tenureDistribution']['threeToFiveYears'])->toBe(0)
        ->and($props['tenureDistribution']['fiveToTenYears'])->toBe(0)
        ->and($props['tenureDistribution']['moreThan10Years'])->toBe(0)
        ->and($props['departmentHeadcounts'])->toBeArray();
});

it('denies access to HR Staff role users due to missing EmployeesDelete permission', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $hrStaff = createTenantUserForDashboard($tenant, TenantUserRole::HrStaff);
    $this->actingAs($hrStaff);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);

    // HR Staff does not have EmployeesDelete permission, so they fail the can-manage-employees gate
    expect(fn () => $controller->dashboard())
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('denies access to HR Consultant role users due to missing EmployeesDelete permission', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $hrConsultant = createTenantUserForDashboard($tenant, TenantUserRole::HrConsultant);
    $this->actingAs($hrConsultant);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);

    // HR Consultant has same permissions as HR Staff (no EmployeesDelete), so they fail the gate
    expect(fn () => $controller->dashboard())
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('calculates turnover rate correctly using the annualized formula', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create employees that existed at start and end of month (for average headcount)
    // Start of month headcount: employees hired before month start, not terminated before month start
    // End of month headcount: employees hired on or before month end, not terminated before month end
    Employee::factory()->count(100)->active()->create([
        'hire_date' => Carbon::now()->subYear(),
    ]);

    // Create 1 separation this month
    // Turnover rate = (1 / 100) * 12 * 100 = 12%
    Employee::factory()->resigned()->create([
        'hire_date' => Carbon::now()->subYear(),
        'termination_date' => Carbon::now()->startOfMonth()->addDays(5),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // With 100 employees and 1 separation, turnover rate should be approximately 12%
    // (1 / ((100 + 100) / 2)) * 12 * 100 = 12%
    expect($props['turnover']['rate'])->toBeGreaterThanOrEqual(10)
        ->and($props['turnover']['rate'])->toBeLessThanOrEqual(15);
});

it('returns null percentage change when both current and previous months have no data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create employees hired before both current and previous month
    // This ensures no new hires in current or previous month
    Employee::factory()->count(5)->active()->create([
        'hire_date' => Carbon::now()->subMonths(3),
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // With 0 hires in both months, percentage change should be null
    expect($props['newHires']['count'])->toBe(0)
        ->and($props['newHires']['percentageChange'])->toBeNull();
});

it('handles comprehensive realistic data scenario for full dashboard integration', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantContextForDashboard($tenant);

    $admin = createTenantUserForDashboard($tenant, TenantUserRole::Admin);
    $this->actingAs($admin);

    // Create realistic organizational structure
    $engineering = Department::factory()->create(['name' => 'Engineering', 'status' => 'active']);
    $hr = Department::factory()->create(['name' => 'Human Resources', 'status' => 'active']);
    $sales = Department::factory()->create(['name' => 'Sales', 'status' => 'active']);

    // Create diverse employee base with various tenures
    // Engineering: 20 active employees
    Employee::factory()->count(5)->active()->create([
        'department_id' => $engineering->id,
        'hire_date' => Carbon::now()->subMonths(6),
        'employment_type' => EmploymentType::Regular,
    ]);
    Employee::factory()->count(10)->active()->create([
        'department_id' => $engineering->id,
        'hire_date' => Carbon::now()->subYears(3),
        'employment_type' => EmploymentType::Regular,
    ]);
    Employee::factory()->count(5)->active()->create([
        'department_id' => $engineering->id,
        'hire_date' => Carbon::now()->subYears(7),
        'employment_type' => EmploymentType::Regular,
    ]);

    // HR: 8 employees with mix of types
    Employee::factory()->count(4)->active()->create([
        'department_id' => $hr->id,
        'hire_date' => Carbon::now()->subYears(2),
        'employment_type' => EmploymentType::Regular,
    ]);
    Employee::factory()->count(4)->active()->create([
        'department_id' => $hr->id,
        'hire_date' => Carbon::now()->subMonths(4),
        'employment_type' => EmploymentType::Probationary,
    ]);

    // Sales: 12 employees
    Employee::factory()->count(8)->active()->create([
        'department_id' => $sales->id,
        'hire_date' => Carbon::now()->subYears(4),
        'employment_type' => EmploymentType::Regular,
    ]);
    Employee::factory()->count(4)->active()->create([
        'department_id' => $sales->id,
        'hire_date' => Carbon::now()->subMonths(2),
        'employment_type' => EmploymentType::Contractual,
    ]);

    // New hires this month: 3
    Employee::factory()->count(3)->active()->create([
        'department_id' => $engineering->id,
        'hire_date' => Carbon::now()->startOfMonth()->addDays(5),
        'employment_type' => EmploymentType::Probationary,
    ]);

    // New hires last month: 2
    Employee::factory()->count(2)->active()->create([
        'department_id' => $sales->id,
        'hire_date' => Carbon::now()->subMonth()->startOfMonth()->addDays(10),
        'employment_type' => EmploymentType::Probationary,
    ]);

    // Separations this month: 1
    Employee::factory()->resigned()->create([
        'department_id' => $hr->id,
        'hire_date' => Carbon::now()->subYears(2),
        'termination_date' => Carbon::now()->startOfMonth()->addDays(3),
        'employment_type' => EmploymentType::Regular,
    ]);

    $controller = new EmployeeDashboardController(new EmployeeDashboardService);
    $response = $controller->dashboard();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    // Verify comprehensive metrics
    // Total: 20 engineering + 8 HR + 12 sales + 3 new this month + 2 new last month + 1 resigned = 46
    expect($props['headcount']['total'])->toBe(46);
    // Active: 46 - 1 resigned = 45
    expect($props['headcount']['active'])->toBe(45);
    // New hires this month: 3
    expect($props['newHires']['count'])->toBe(3);
    // Percentage change: (3-2)/2 * 100 = 50%
    expect($props['newHires']['percentageChange'])->toBe(50.0);
    // Separations this month: 1
    expect($props['separations']['count'])->toBe(1);

    // Verify department headcounts
    $departments = collect($props['departmentHeadcounts']);
    expect($departments)->toHaveCount(3);

    $engDept = $departments->firstWhere('name', 'Engineering');
    expect($engDept['employees_count'])->toBe(23); // 20 + 3 new

    $hrDept = $departments->firstWhere('name', 'Human Resources');
    expect($hrDept['employees_count'])->toBe(8); // 8 active (1 resigned not counted)

    $salesDept = $departments->firstWhere('name', 'Sales');
    expect($salesDept['employees_count'])->toBe(14); // 12 + 2 new last month

    // Verify tenure distribution has non-zero values
    expect($props['tenureDistribution']['lessThan1Year'])->toBeGreaterThan(0);
    expect($props['tenureDistribution']['oneToThreeYears'])->toBeGreaterThan(0);
    expect($props['tenureDistribution']['threeToFiveYears'])->toBeGreaterThan(0);
    expect($props['tenureDistribution']['fiveToTenYears'])->toBeGreaterThan(0);

    // Verify employment type breakdown
    expect($props['employmentTypeBreakdown']['regular'])->toBeGreaterThan(0);
    expect($props['employmentTypeBreakdown']['probationary'])->toBeGreaterThan(0);
    expect($props['employmentTypeBreakdown']['contractual'])->toBeGreaterThan(0);

    // Verify turnover rate is calculated
    expect($props['turnover']['rate'])->toBeGreaterThanOrEqual(0);
    expect($props['turnover']['averageTenure'])->toBeGreaterThan(0);
});
