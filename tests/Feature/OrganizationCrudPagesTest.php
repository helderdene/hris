<?php

use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Enums\LocationType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Api\WorkLocationController;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\StoreSalaryGradeRequest;
use App\Http\Requests\StoreWorkLocationRequest;
use App\Http\Requests\UpdateSalaryGradeRequest;
use App\Http\Requests\UpdateWorkLocationRequest;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindCrudTestTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createCrudTestTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store position request.
 */
function createStorePositionRequest(array $data, User $user): StorePositionRequest
{
    $request = StorePositionRequest::create('/api/organization/positions', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StorePositionRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated store salary grade request.
 */
function createStoreSalaryGradeRequest(array $data, User $user): StoreSalaryGradeRequest
{
    $request = StoreSalaryGradeRequest::create('/api/organization/salary-grades', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreSalaryGradeRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update salary grade request.
 */
function createUpdateSalaryGradeRequest(array $data, User $user): UpdateSalaryGradeRequest
{
    $request = UpdateSalaryGradeRequest::create('/api/organization/salary-grades/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdateSalaryGradeRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated store work location request.
 */
function createStoreWorkLocationRequest(array $data, User $user): StoreWorkLocationRequest
{
    $request = StoreWorkLocationRequest::create('/api/organization/locations', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreWorkLocationRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update work location request with route binding.
 */
function createUpdateWorkLocationRequestWithRoute(array $data, User $user, WorkLocation $location): UpdateWorkLocationRequest
{
    $request = UpdateWorkLocationRequest::create("/api/organization/locations/{$location->id}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRouteResolver(fn () => new class($location)
    {
        private WorkLocation $location;

        public function __construct(WorkLocation $location)
        {
            $this->location = $location;
        }

        public function parameter($name)
        {
            return $this->location;
        }
    });

    $rules = (new UpdateWorkLocationRequest)->setRouteResolver($request->getRouteResolver())->rules();
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

describe('Position List with Filtering', function () {
    it('displays positions with filtering by status and job level', function () {
        $tenant = Tenant::factory()->create();
        bindCrudTestTenantContext($tenant);

        $admin = createCrudTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $grade1 = SalaryGrade::factory()->create(['name' => 'Grade 1']);
        $grade2 = SalaryGrade::factory()->create(['name' => 'Grade 2']);

        // Create positions with different attributes
        Position::factory()->create([
            'title' => 'Junior Developer',
            'code' => 'JD-001',
            'job_level' => JobLevel::Junior,
            'salary_grade_id' => $grade1->id,
            'status' => 'active',
        ]);

        Position::factory()->create([
            'title' => 'Senior Developer',
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

        $controller = new PositionController;

        // Test that all positions are returned
        $allRequest = Request::create('/api/organization/positions', 'GET');
        $allResponse = $controller->index($allRequest);
        expect($allResponse->count())->toBe(3);

        // Test filtering by status
        $activeRequest = Request::create('/api/organization/positions', 'GET', ['status' => 'active']);
        $activeResponse = $controller->index($activeRequest);
        expect($activeResponse->count())->toBe(2);

        // Test filtering by job level
        $juniorRequest = Request::create('/api/organization/positions', 'GET', ['job_level' => JobLevel::Junior->value]);
        $juniorResponse = $controller->index($juniorRequest);
        expect($juniorResponse->count())->toBe(1);
        expect($juniorResponse->first()->title)->toBe('Junior Developer');

        // Test filtering by salary grade
        $gradeRequest = Request::create('/api/organization/positions', 'GET', ['salary_grade_id' => $grade1->id]);
        $gradeResponse = $controller->index($gradeRequest);
        expect($gradeResponse->count())->toBe(2);

        // Test combined filters
        $combinedRequest = Request::create('/api/organization/positions', 'GET', [
            'status' => 'active',
            'salary_grade_id' => $grade1->id,
        ]);
        $combinedResponse = $controller->index($combinedRequest);
        expect($combinedResponse->count())->toBe(1);
    });
});

describe('Position Form with Salary Grade Dropdown', function () {
    it('creates position with salary grade assignment and enum labels', function () {
        $tenant = Tenant::factory()->create();
        bindCrudTestTenantContext($tenant);

        $admin = createCrudTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $salaryGrade = SalaryGrade::factory()->create([
            'name' => 'Grade A',
            'minimum_salary' => 50000,
            'midpoint_salary' => 75000,
            'maximum_salary' => 100000,
        ]);

        $controller = new PositionController;

        // Create a position with salary grade
        $storeRequest = createStorePositionRequest([
            'title' => 'Software Engineer',
            'code' => 'SE-001',
            'description' => 'Full-stack development role',
            'salary_grade_id' => $salaryGrade->id,
            'job_level' => JobLevel::Mid->value,
            'employment_type' => EmploymentType::Regular->value,
            'status' => 'active',
        ], $admin);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['data']['title'])->toBe('Software Engineer');
        expect($data['data']['salary_grade']['id'])->toBe($salaryGrade->id);
        expect($data['data']['salary_grade']['name'])->toBe('Grade A');
        expect($data['data']['job_level'])->toBe('mid');
        expect($data['data']['job_level_label'])->toBe('Mid');
        expect($data['data']['employment_type'])->toBe('regular');
        expect($data['data']['employment_type_label'])->toBe('Regular');

        // Verify position was created in database
        $this->assertDatabaseHas('positions', [
            'title' => 'Software Engineer',
            'code' => 'SE-001',
            'salary_grade_id' => $salaryGrade->id,
        ]);
    });
});

describe('SalaryGrade Form with Inline Steps', function () {
    it('creates and updates salary grade with inline steps editing', function () {
        $tenant = Tenant::factory()->create();
        bindCrudTestTenantContext($tenant);

        $admin = createCrudTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new SalaryGradeController;

        // Create salary grade with steps
        $storeRequest = createStoreSalaryGradeRequest([
            'name' => 'Professional Grade',
            'minimum_salary' => 60000.00,
            'midpoint_salary' => 80000.00,
            'maximum_salary' => 100000.00,
            'currency' => 'PHP',
            'status' => 'active',
            'steps' => [
                ['step_number' => 1, 'amount' => 60000.00, 'effective_date' => '2024-01-01'],
                ['step_number' => 2, 'amount' => 70000.00, 'effective_date' => '2024-07-01'],
                ['step_number' => 3, 'amount' => 80000.00, 'effective_date' => '2025-01-01'],
            ],
        ], $admin);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['data']['name'])->toBe('Professional Grade');
        expect($data['data']['minimum_salary'])->toBe('60000.00');
        expect($data['data']['midpoint_salary'])->toBe('80000.00');
        expect($data['data']['maximum_salary'])->toBe('100000.00');
        expect(count($data['data']['steps']))->toBe(3);

        // Verify steps are ordered correctly
        expect($data['data']['steps'][0]['step_number'])->toBe(1);
        expect($data['data']['steps'][1]['step_number'])->toBe(2);
        expect($data['data']['steps'][2]['step_number'])->toBe(3);

        // Update salary grade with different steps (replacing all)
        $gradeId = $data['data']['id'];
        $salaryGrade = SalaryGrade::find($gradeId);

        $updateRequest = createUpdateSalaryGradeRequest([
            'name' => 'Professional Grade Updated',
            'minimum_salary' => 65000.00,
            'midpoint_salary' => 85000.00,
            'maximum_salary' => 105000.00,
            'currency' => 'PHP',
            'status' => 'active',
            'steps' => [
                ['step_number' => 1, 'amount' => 65000.00],
                ['step_number' => 2, 'amount' => 85000.00],
            ],
        ], $admin);

        $updateResponse = $controller->update($updateRequest, $salaryGrade);
        $updateData = $updateResponse->toArray(request());

        expect($updateData['name'])->toBe('Professional Grade Updated');
        expect(count($updateData['steps']))->toBe(2);
    });
});

describe('SalaryGrade Validation', function () {
    it('validates salary range ordering (min <= mid <= max)', function () {
        $tenant = Tenant::factory()->create();
        bindCrudTestTenantContext($tenant);

        $admin = createCrudTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $rules = (new StoreSalaryGradeRequest)->rules();

        // Test invalid: minimum > midpoint (midpoint fails gte:minimum_salary)
        $validator1 = Validator::make([
            'name' => 'Invalid Grade 1',
            'minimum_salary' => 80000.00,
            'midpoint_salary' => 70000.00,
            'maximum_salary' => 100000.00,
            'currency' => 'PHP',
            'status' => 'active',
        ], $rules);

        expect($validator1->fails())->toBeTrue();
        expect($validator1->errors()->has('midpoint_salary'))->toBeTrue();

        // Test invalid: midpoint > maximum (maximum fails gte:midpoint_salary)
        $validator2 = Validator::make([
            'name' => 'Invalid Grade 2',
            'minimum_salary' => 50000.00,
            'midpoint_salary' => 110000.00,
            'maximum_salary' => 100000.00,
            'currency' => 'PHP',
            'status' => 'active',
        ], $rules);

        expect($validator2->fails())->toBeTrue();
        expect($validator2->errors()->has('maximum_salary'))->toBeTrue();

        // Test valid salary range
        $validator3 = Validator::make([
            'name' => 'Valid Grade',
            'minimum_salary' => 50000.00,
            'midpoint_salary' => 75000.00,
            'maximum_salary' => 100000.00,
            'currency' => 'PHP',
            'status' => 'active',
        ], $rules);

        expect($validator3->fails())->toBeFalse();
    });
});

describe('WorkLocation Form with Metadata', function () {
    it('creates and updates location with metadata JSON handling', function () {
        $tenant = Tenant::factory()->create();
        bindCrudTestTenantContext($tenant);

        $admin = createCrudTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new WorkLocationController;

        $metadata = [
            'phone' => '+63-2-8888-1234',
            'email' => 'main@company.com',
            'capacity' => 200,
            'parking_spaces' => 50,
        ];

        // Create location with metadata
        $storeRequest = createStoreWorkLocationRequest([
            'name' => 'Main Office',
            'code' => 'MAIN-001',
            'address' => '123 Business Park',
            'city' => 'Manila',
            'region' => 'NCR',
            'country' => 'Philippines',
            'postal_code' => '1234',
            'location_type' => LocationType::Headquarters->value,
            'timezone' => 'Asia/Manila',
            'metadata' => $metadata,
            'status' => 'active',
        ], $admin);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['data']['name'])->toBe('Main Office');
        expect($data['data']['code'])->toBe('MAIN-001');
        expect($data['data']['location_type'])->toBe('headquarters');
        expect($data['data']['location_type_label'])->toBe('Headquarters');
        expect($data['data']['metadata']['phone'])->toBe('+63-2-8888-1234');
        expect($data['data']['metadata']['capacity'])->toBe(200);

        // Verify metadata is stored correctly in database
        $location = WorkLocation::where('code', 'MAIN-001')->first();
        expect($location->metadata)->toBeArray();
        expect($location->metadata['phone'])->toBe('+63-2-8888-1234');
        expect($location->metadata['capacity'])->toBe(200);

        // Update location with new metadata
        $updatedMetadata = [
            'phone' => '+63-2-8888-5678',
            'email' => 'updated@company.com',
            'capacity' => 250,
            'floor_count' => 5,
        ];

        $updateRequest = createUpdateWorkLocationRequestWithRoute([
            'name' => 'Main Office Updated',
            'code' => 'MAIN-001',
            'address' => '123 Business Park',
            'city' => 'Manila',
            'region' => 'NCR',
            'country' => 'Philippines',
            'postal_code' => '1234',
            'location_type' => LocationType::Headquarters->value,
            'timezone' => 'Asia/Manila',
            'metadata' => $updatedMetadata,
            'status' => 'active',
        ], $admin, $location);

        $updateResponse = $controller->update($updateRequest, $location);
        $updateData = $updateResponse->toArray(request());

        expect($updateData['metadata']['capacity'])->toBe(250);
        expect($updateData['metadata']['floor_count'])->toBe(5);
    });
});

describe('LocationType Dropdown Selection', function () {
    it('supports all location type values with correct labels', function () {
        $tenant = Tenant::factory()->create();
        bindCrudTestTenantContext($tenant);

        $admin = createCrudTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new WorkLocationController;

        // Test each location type
        $locationTypes = [
            ['value' => 'headquarters', 'label' => 'Headquarters'],
            ['value' => 'branch', 'label' => 'Branch'],
            ['value' => 'satellite_office', 'label' => 'Satellite Office'],
            ['value' => 'remote_hub', 'label' => 'Remote Hub'],
            ['value' => 'warehouse', 'label' => 'Warehouse'],
            ['value' => 'factory', 'label' => 'Factory'],
        ];

        foreach ($locationTypes as $index => $type) {
            $storeRequest = createStoreWorkLocationRequest([
                'name' => "Location {$index}",
                'code' => "LOC-{$index}",
                'location_type' => $type['value'],
                'status' => 'active',
            ], $admin);

            $response = $controller->store($storeRequest);
            $data = json_decode($response->getContent(), true);

            expect($response->getStatusCode())->toBe(201);
            expect($data['data']['location_type'])->toBe($type['value']);
            expect($data['data']['location_type_label'])->toBe($type['label']);
        }

        // Verify all locations were created
        expect(WorkLocation::count())->toBe(count($locationTypes));
    });
});
