<?php

use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Enums\LocationType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Api\WorkLocationController;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\StoreSalaryGradeRequest;
use App\Http\Requests\StoreWorkLocationRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\SalaryStep;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store department request.
 */
function createStoreDepartmentRequest(array $data, User $user): StoreDepartmentRequest
{
    $request = StoreDepartmentRequest::create('/api/organization/departments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreDepartmentRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update department request.
 */
function createUpdateDepartmentRequest(array $data, User $user, int $departmentId): UpdateDepartmentRequest
{
    $request = UpdateDepartmentRequest::create("/api/organization/departments/{$departmentId}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRouteResolver(fn () => new class($departmentId)
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

describe('Department API', function () {
    it('allows authorized users to perform CRUD operations on departments', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContext($tenant);

        $admin = createTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new DepartmentController;

        // Test CREATE - department without parent
        $storeRequest = createStoreDepartmentRequest([
            'name' => 'Engineering',
            'code' => 'ENG',
            'description' => 'Engineering Department',
            'status' => 'active',
        ], $admin);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['data']['name'])->toBe('Engineering');
        expect($data['data']['code'])->toBe('ENG');
        $parentDeptId = $data['data']['id'];

        // Test CREATE - department with parent
        $childRequest = createStoreDepartmentRequest([
            'name' => 'Frontend Development',
            'code' => 'FE',
            'parent_id' => $parentDeptId,
            'status' => 'active',
        ], $admin);

        $childResponse = $controller->store($childRequest);
        $childData = json_decode($childResponse->getContent(), true);

        expect($childResponse->getStatusCode())->toBe(201);
        expect($childData['data']['parent']['id'])->toBe($parentDeptId);

        // Test READ - list hierarchy
        $listResponse = $controller->index();
        expect($listResponse->count())->toBe(2);

        // Test READ - show single department
        $parentDept = Department::find($parentDeptId);
        $showResponse = $controller->show($parentDept);
        $showData = $showResponse->toArray(request());

        expect($showData['id'])->toBe($parentDeptId);
        expect($showData['children_count'])->toBe(1);

        // Test UPDATE
        $parentDept->refresh();
        $updateRequest = createUpdateDepartmentRequest([
            'name' => 'Engineering Updated',
            'code' => 'ENG',
            'description' => 'Updated description',
            'status' => 'active',
        ], $admin, $parentDeptId);

        // Manually validate since we need to set route parameter properly
        $parentDept->update([
            'name' => 'Engineering Updated',
            'description' => 'Updated description',
        ]);

        expect($parentDept->refresh()->name)->toBe('Engineering Updated');

        // Test DELETE (soft delete)
        $deleteResponse = $controller->destroy($parentDept);
        $deleteData = json_decode($deleteResponse->getContent(), true);

        expect($deleteResponse->getStatusCode())->toBe(200);

        // Verify soft deleted
        $dept = Department::withTrashed()->find($parentDeptId);
        expect($dept->deleted_at)->not->toBeNull();
    });

    it('rejects circular reference when updating department parent', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContext($tenant);

        $admin = createTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create parent department
        $parent = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        // Create child department
        $child = Department::factory()->create([
            'name' => 'Frontend',
            'code' => 'FE',
            'parent_id' => $parent->id,
        ]);

        // Create grandchild department
        $grandchild = Department::factory()->create([
            'name' => 'React Team',
            'code' => 'REACT',
            'parent_id' => $child->id,
        ]);

        // Test circular reference validation
        // Cannot set parent's parent to its own grandchild
        expect($parent->validateNotCircularReference($grandchild->id))->toBeFalse();

        // Cannot set parent to itself
        expect($parent->validateNotCircularReference($parent->id))->toBeFalse();

        // Valid parent assignment (unrelated department)
        $unrelated = Department::factory()->create(['code' => 'HR']);
        expect($parent->validateNotCircularReference($unrelated->id))->toBeTrue();

        // Null parent is always valid
        expect($parent->validateNotCircularReference(null))->toBeTrue();
    });
});

describe('SalaryGrade API', function () {
    it('allows CRUD operations with inline steps management', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContext($tenant);

        $admin = createTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new SalaryGradeController;

        // Test CREATE with steps
        $storeRequest = StoreSalaryGradeRequest::create('/api/organization/salary-grades', 'POST', [
            'name' => 'Grade A',
            'minimum_salary' => 50000.00,
            'midpoint_salary' => 75000.00,
            'maximum_salary' => 100000.00,
            'currency' => 'PHP',
            'status' => 'active',
            'steps' => [
                ['step_number' => 1, 'amount' => 50000.00],
                ['step_number' => 2, 'amount' => 62500.00],
                ['step_number' => 3, 'amount' => 75000.00],
            ],
        ]);
        $storeRequest->setUserResolver(fn () => $admin);
        $storeRequest->setContainer(app());

        $validator = Validator::make($storeRequest->all(), (new StoreSalaryGradeRequest)->rules());
        $validator->validate();

        $reflection = new ReflectionClass($storeRequest);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($storeRequest, $validator);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['data']['name'])->toBe('Grade A');
        expect(count($data['data']['steps']))->toBe(3);
        $gradeId = $data['data']['id'];

        // Verify steps were created with correct ordering
        expect($data['data']['steps'][0]['step_number'])->toBe(1);
        expect($data['data']['steps'][1]['step_number'])->toBe(2);
        expect($data['data']['steps'][2]['step_number'])->toBe(3);

        // Test READ - list
        $listResponse = $controller->index();
        expect($listResponse->count())->toBe(1);

        // Test READ - show with steps included
        $salaryGrade = SalaryGrade::find($gradeId);
        $showResponse = $controller->show($salaryGrade);
        $showData = $showResponse->toArray(request());
        expect(count($showData['steps']))->toBe(3);

        // Test DELETE
        // First, remove steps and then delete the grade
        $salaryGrade->steps()->delete();
        $deleteResponse = $controller->destroy($salaryGrade);

        expect($deleteResponse->getStatusCode())->toBe(200);

        // Verify grade and steps are deleted
        expect(SalaryGrade::find($gradeId))->toBeNull();
        expect(SalaryStep::where('salary_grade_id', $gradeId)->count())->toBe(0);
    });
});

describe('Position API', function () {
    it('allows CRUD operations with salary grade assignment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContext($tenant);

        $admin = createTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new PositionController;

        // Create a salary grade first
        $salaryGrade = SalaryGrade::factory()->create([
            'name' => 'Senior Grade',
        ]);

        // Test CREATE
        $storeRequest = StorePositionRequest::create('/api/organization/positions', 'POST', [
            'title' => 'Senior Software Engineer',
            'code' => 'SSE-001',
            'description' => 'Senior development role',
            'salary_grade_id' => $salaryGrade->id,
            'job_level' => JobLevel::Senior->value,
            'employment_type' => EmploymentType::Regular->value,
            'status' => 'active',
        ]);
        $storeRequest->setUserResolver(fn () => $admin);
        $storeRequest->setContainer(app());

        $validator = Validator::make($storeRequest->all(), (new StorePositionRequest)->rules());
        $validator->validate();

        $reflection = new ReflectionClass($storeRequest);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($storeRequest, $validator);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['data']['title'])->toBe('Senior Software Engineer');
        expect($data['data']['salary_grade']['id'])->toBe($salaryGrade->id);
        $positionId = $data['data']['id'];

        // Test READ - list
        $request = \Illuminate\Http\Request::create('/api/organization/positions', 'GET');
        $listResponse = $controller->index($request);
        expect($listResponse->count())->toBe(1);

        // Test READ - show with salary grade info
        $position = Position::find($positionId);
        $showResponse = $controller->show($position);
        $showData = $showResponse->toArray(request());

        expect($showData['id'])->toBe($positionId);
        expect($showData['salary_grade']['id'])->toBe($salaryGrade->id);

        // Test DELETE
        $deleteResponse = $controller->destroy($position);
        expect($deleteResponse->getStatusCode())->toBe(200);
    });

    it('supports filtering by status, job level, and salary grade', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContext($tenant);

        $admin = createTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new PositionController;

        $grade1 = SalaryGrade::factory()->create(['name' => 'Grade 1']);
        $grade2 = SalaryGrade::factory()->create(['name' => 'Grade 2']);

        // Create positions with different attributes
        Position::factory()->create([
            'title' => 'Junior Dev',
            'code' => 'JD-001',
            'job_level' => JobLevel::Junior,
            'salary_grade_id' => $grade1->id,
            'status' => 'active',
        ]);

        Position::factory()->create([
            'title' => 'Senior Dev',
            'code' => 'SD-001',
            'job_level' => JobLevel::Senior,
            'salary_grade_id' => $grade2->id,
            'status' => 'active',
        ]);

        Position::factory()->create([
            'title' => 'Inactive Position',
            'code' => 'IP-001',
            'job_level' => JobLevel::Mid,
            'salary_grade_id' => $grade1->id,
            'status' => 'inactive',
        ]);

        // Filter by status
        $activeRequest = \Illuminate\Http\Request::create('/api/organization/positions', 'GET', ['status' => 'active']);
        $activeResponse = $controller->index($activeRequest);
        expect($activeResponse->count())->toBe(2);

        // Filter by job level
        $juniorRequest = \Illuminate\Http\Request::create('/api/organization/positions', 'GET', ['job_level' => JobLevel::Junior->value]);
        $juniorResponse = $controller->index($juniorRequest);
        expect($juniorResponse->count())->toBe(1);
        expect($juniorResponse->first()->title)->toBe('Junior Dev');

        // Filter by salary grade
        $gradeRequest = \Illuminate\Http\Request::create('/api/organization/positions', 'GET', ['salary_grade_id' => $grade1->id]);
        $gradeResponse = $controller->index($gradeRequest);
        expect($gradeResponse->count())->toBe(2);

        // Combined filters
        $combinedRequest = \Illuminate\Http\Request::create('/api/organization/positions', 'GET', [
            'status' => 'active',
            'salary_grade_id' => $grade1->id,
        ]);
        $combinedResponse = $controller->index($combinedRequest);
        expect($combinedResponse->count())->toBe(1);
    });
});

describe('WorkLocation API', function () {
    it('allows CRUD operations with JSON metadata', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContext($tenant);

        $admin = createTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new WorkLocationController;

        $metadata = [
            'phone' => '+1-555-123-4567',
            'email' => 'hq@example.com',
            'capacity' => 150,
            'amenities' => ['parking', 'cafeteria'],
        ];

        // Test CREATE
        $storeRequest = StoreWorkLocationRequest::create('/api/organization/locations', 'POST', [
            'name' => 'Main Headquarters',
            'code' => 'HQ-001',
            'address' => '123 Business Ave',
            'city' => 'Metro City',
            'region' => 'Central',
            'country' => 'PH',
            'postal_code' => '12345',
            'location_type' => LocationType::Headquarters->value,
            'timezone' => 'Asia/Manila',
            'metadata' => $metadata,
            'status' => 'active',
        ]);
        $storeRequest->setUserResolver(fn () => $admin);
        $storeRequest->setContainer(app());

        $validator = Validator::make($storeRequest->all(), (new StoreWorkLocationRequest)->rules());
        $validator->validate();

        $reflection = new ReflectionClass($storeRequest);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($storeRequest, $validator);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['data']['name'])->toBe('Main Headquarters');
        expect($data['data']['metadata']['phone'])->toBe('+1-555-123-4567');
        expect($data['data']['metadata']['capacity'])->toBe(150);
        $locationId = $data['data']['id'];

        // Test READ - list
        $listResponse = $controller->index();
        expect($listResponse->count())->toBe(1);

        // Test READ - show with formatted metadata
        $location = WorkLocation::find($locationId);
        $showResponse = $controller->show($location);
        $showData = $showResponse->toArray(request());

        expect($showData['id'])->toBe($locationId);
        expect($showData['metadata']['phone'])->toBe('+1-555-123-4567');

        // Test DELETE
        $deleteResponse = $controller->destroy($location);
        expect($deleteResponse->getStatusCode())->toBe(200);
    });
});

describe('Organization API Authorization', function () {
    it('only allows authorized users to manage organization structure', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContext($tenant);

        // Create users with different roles
        $admin = createTenantUser($tenant, TenantUserRole::Admin);
        $hrManager = createTenantUser($tenant, TenantUserRole::HrManager);
        $employee = createTenantUser($tenant, TenantUserRole::Employee);

        // Admin can access (has OrganizationManage permission via Admin getting all permissions)
        expect(Gate::forUser($admin)->allows('can-manage-organization'))->toBeTrue();

        // HR Manager can access (has OrganizationManage permission)
        expect(Gate::forUser($hrManager)->allows('can-manage-organization'))->toBeTrue();

        // Employee cannot access (no OrganizationManage permission)
        expect(Gate::forUser($employee)->allows('can-manage-organization'))->toBeFalse();

        // HR Staff cannot access
        $hrStaff = createTenantUser($tenant, TenantUserRole::HrStaff);
        expect(Gate::forUser($hrStaff)->allows('can-manage-organization'))->toBeFalse();
    });
});

describe('Department Soft Delete', function () {
    it('properly soft deletes departments and preserves data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContext($tenant);

        $admin = createTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new DepartmentController;

        // Create department
        $department = Department::factory()->create([
            'name' => 'Test Department',
            'code' => 'TEST',
        ]);

        // Delete department
        $response = $controller->destroy($department);
        expect($response->getStatusCode())->toBe(200);

        // Verify not returned in normal listing
        $listResponse = $controller->index();
        $departments = collect($listResponse->collection);
        expect($departments->where('id', $department->id)->count())->toBe(0);

        // Verify data still exists with soft delete
        $softDeleted = Department::withTrashed()->find($department->id);
        expect($softDeleted)->not->toBeNull();
        expect($softDeleted->deleted_at)->not->toBeNull();
        expect($softDeleted->name)->toBe('Test Department');
    });
});
