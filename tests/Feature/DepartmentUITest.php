<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\OrganizationController;
use App\Http\Requests\StoreDepartmentRequest;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantForUI(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForUI(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to validate department data.
 */
function validateDepartmentData(array $data): \Illuminate\Validation\Validator
{
    return Validator::make($data, (new StoreDepartmentRequest)->rules());
}

/**
 * Helper to create a validated StoreDepartmentRequest.
 */
function createValidStoreRequest(array $data, User $user): StoreDepartmentRequest
{
    $request = StoreDepartmentRequest::create('/api/organization/departments', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreDepartmentRequest)->rules());

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

describe('Department Tree View UI', function () {
    it('renders department tree view correctly with hierarchy', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForUI($tenant);

        $admin = createTenantUserForUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create parent department
        $parentDept = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
            'status' => 'active',
            'parent_id' => null,
        ]);

        // Create child departments
        $childDept1 = Department::factory()->create([
            'name' => 'Frontend Development',
            'code' => 'FE',
            'status' => 'active',
            'parent_id' => $parentDept->id,
        ]);

        $childDept2 = Department::factory()->create([
            'name' => 'Backend Development',
            'code' => 'BE',
            'status' => 'inactive',
            'parent_id' => $parentDept->id,
        ]);

        // Create grandchild department
        $grandchildDept = Department::factory()->create([
            'name' => 'React Team',
            'code' => 'REACT',
            'status' => 'active',
            'parent_id' => $childDept1->id,
        ]);

        // Test the controller directly to avoid Vite manifest issues
        $request = Request::create('/organization/departments', 'GET');
        $request->setUserResolver(fn () => $admin);

        $controller = new OrganizationController;
        $inertiaResponse = $controller->departmentsIndex();

        // Use reflection to access protected properties
        $reflection = new ReflectionClass($inertiaResponse);

        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);
        expect($componentProperty->getValue($inertiaResponse))->toBe('Organization/Departments/Index');

        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Check that all 4 departments are returned
        $departments = $props['departments']->collection;
        expect($departments)->toHaveCount(4);

        // Check that only 1 root department in tree
        $departmentTree = $props['departmentTree']->collection;
        expect($departmentTree)->toHaveCount(1);

        // Verify root department is Engineering
        $rootDept = $departmentTree->first()->toArray(request());
        expect($rootDept['name'])->toBe('Engineering');

        // Verify it has 2 children
        expect($rootDept['children'])->toHaveCount(2);
    });

    it('shows empty state when no departments exist', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForUI($tenant);

        $admin = createTenantUserForUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Test the controller directly
        $request = Request::create('/organization/departments', 'GET');
        $request->setUserResolver(fn () => $admin);

        $controller = new OrganizationController;
        $inertiaResponse = $controller->departmentsIndex();

        // Use reflection to access protected properties
        $reflection = new ReflectionClass($inertiaResponse);

        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Check that no departments are returned
        $departments = $props['departments']->collection;
        expect($departments)->toHaveCount(0);

        // Check that tree is empty
        $departmentTree = $props['departmentTree']->collection;
        expect($departmentTree)->toHaveCount(0);
    });
});

describe('Department Form UI', function () {
    it('validates and submits create department form correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForUI($tenant);

        $admin = createTenantUserForUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new DepartmentController;

        // Test validation error - missing required fields
        $invalidValidator = validateDepartmentData([]);
        expect($invalidValidator->fails())->toBeTrue();
        expect($invalidValidator->errors()->has('name'))->toBeTrue();
        expect($invalidValidator->errors()->has('code'))->toBeTrue();
        expect($invalidValidator->errors()->has('status'))->toBeTrue();

        // Test validation error - duplicate code
        Department::factory()->create(['code' => 'EXISTING']);

        $duplicateValidator = validateDepartmentData([
            'name' => 'Test Department',
            'code' => 'EXISTING',
            'status' => 'active',
        ]);
        expect($duplicateValidator->fails())->toBeTrue();
        expect($duplicateValidator->errors()->has('code'))->toBeTrue();

        // Test successful creation
        $validValidator = validateDepartmentData([
            'name' => 'New Department',
            'code' => 'NEW',
            'description' => 'A new department',
            'status' => 'active',
        ]);
        expect($validValidator->passes())->toBeTrue();

        $validRequest = createValidStoreRequest([
            'name' => 'New Department',
            'code' => 'NEW',
            'description' => 'A new department',
            'status' => 'active',
        ], $admin);

        $response = $controller->store($validRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['name'])->toBe('New Department');
        expect($data['code'])->toBe('NEW');

        // Verify in database
        $this->assertDatabaseHas('departments', [
            'name' => 'New Department',
            'code' => 'NEW',
        ]);
    });

    it('validates and submits edit department with parent change', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForUI($tenant);

        $admin = createTenantUserForUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create departments
        $dept1 = Department::factory()->create([
            'name' => 'Department 1',
            'code' => 'D1',
            'parent_id' => null,
        ]);

        $dept2 = Department::factory()->create([
            'name' => 'Department 2',
            'code' => 'D2',
            'parent_id' => null,
        ]);

        $childDept = Department::factory()->create([
            'name' => 'Child Dept',
            'code' => 'CD',
            'parent_id' => $dept1->id,
        ]);

        // Test updating department with new parent
        $childDept->update([
            'name' => 'Child Dept Updated',
            'parent_id' => $dept2->id,
        ]);
        $childDept->load(['parent', 'children']);

        expect($childDept->name)->toBe('Child Dept Updated');
        expect($childDept->parent_id)->toBe($dept2->id);

        // Test circular reference prevention - cannot set parent to itself
        expect($dept1->validateNotCircularReference($dept1->id))->toBeFalse();

        // Test circular reference prevention - cannot set parent to own descendant
        // Move childDept back to dept1
        $childDept->update(['parent_id' => $dept1->id]);

        $grandchild = Department::factory()->create([
            'name' => 'Grandchild',
            'code' => 'GC',
            'parent_id' => $childDept->id,
        ]);

        expect($dept1->validateNotCircularReference($grandchild->id))->toBeFalse();
        expect($dept1->validateNotCircularReference($childDept->id))->toBeFalse();

        // Valid parent change - unrelated department
        expect($dept1->validateNotCircularReference($dept2->id))->toBeTrue();
        expect($dept1->validateNotCircularReference(null))->toBeTrue();
    });

    it('handles delete department with confirmation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForUI($tenant);

        $admin = createTenantUserForUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new DepartmentController;

        // Create department
        $dept = Department::factory()->create([
            'name' => 'Department to Delete',
            'code' => 'DEL',
        ]);

        // Test delete
        $response = $controller->destroy($dept);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['message'])->toBe('Department deleted successfully.');

        // Verify soft deleted
        $this->assertSoftDeleted('departments', ['id' => $dept->id]);

        // Department should not appear in normal listing
        $listResponse = $controller->index();
        $departments = $listResponse->collection;
        expect($departments->where('id', $dept->id)->count())->toBe(0);
    });
});
