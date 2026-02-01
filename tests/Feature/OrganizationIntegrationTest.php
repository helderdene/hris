<?php

/**
 * Organization Structure Integration Tests (Task Group 8)
 *
 * Strategic tests to fill critical coverage gaps identified during test review:
 * - Department hierarchy manipulation (move department to new parent)
 * - Salary grade with steps full CRUD workflow
 * - Position assignment workflow with salary grade changes
 * - Cross-model integration scenarios
 * - Soft delete cascade behavior
 */

use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Enums\LocationType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\DepartmentHeadHistory;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\SalaryStep;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container.
 */
function bindIntegrationTestTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createOrgIntegrationTestUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
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
function createIntegrationStoreDeptRequest(array $data, User $user): StoreDepartmentRequest
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

/**
 * Helper to create a validated update department request.
 */
function createIntegrationUpdateDeptRequest(array $data, User $user, int $departmentId): UpdateDepartmentRequest
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

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Department Hierarchy Manipulation', function () {
    it('moves a department with children to a new parent correctly', function () {
        $tenant = Tenant::factory()->create();
        bindIntegrationTestTenant($tenant);

        $admin = createOrgIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create initial hierarchy: CEO -> Engineering -> Frontend -> React Team
        $ceo = Department::factory()->create(['name' => 'CEO Office', 'code' => 'CEO']);
        $engineering = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
            'parent_id' => $ceo->id,
        ]);
        $frontend = Department::factory()->create([
            'name' => 'Frontend',
            'code' => 'FE',
            'parent_id' => $engineering->id,
        ]);
        $reactTeam = Department::factory()->create([
            'name' => 'React Team',
            'code' => 'REACT',
            'parent_id' => $frontend->id,
        ]);

        // Create a separate branch: CEO -> Product
        $product = Department::factory()->create([
            'name' => 'Product',
            'code' => 'PROD',
            'parent_id' => $ceo->id,
        ]);

        // Move Frontend (with its child React Team) from Engineering to Product
        expect($frontend->validateNotCircularReference($product->id))->toBeTrue();

        $frontend->update(['parent_id' => $product->id]);
        $frontend->refresh();

        expect($frontend->parent_id)->toBe($product->id);
        expect($frontend->parent->id)->toBe($product->id);

        // Verify React Team is still a child of Frontend
        $reactTeam->refresh();
        expect($reactTeam->parent_id)->toBe($frontend->id);

        // Verify hierarchy: CEO -> Product -> Frontend -> React Team
        expect($product->children)->toHaveCount(1);
        expect($product->children->first()->id)->toBe($frontend->id);
        expect($engineering->children()->count())->toBe(0);

        // Verify tree structure from root
        $ceo->refresh();
        $allDescendants = Department::whereIn('parent_id', [$ceo->id])->get();
        expect($allDescendants)->toHaveCount(2); // Engineering and Product
    });

    it('prevents circular reference when moving a parent to its own descendant', function () {
        $tenant = Tenant::factory()->create();
        bindIntegrationTestTenant($tenant);

        $admin = createOrgIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create hierarchy: Engineering -> Frontend -> React -> Components
        $engineering = Department::factory()->create(['name' => 'Engineering', 'code' => 'ENG']);
        $frontend = Department::factory()->create([
            'name' => 'Frontend',
            'code' => 'FE',
            'parent_id' => $engineering->id,
        ]);
        $react = Department::factory()->create([
            'name' => 'React',
            'code' => 'REACT',
            'parent_id' => $frontend->id,
        ]);
        $components = Department::factory()->create([
            'name' => 'Components',
            'code' => 'COMP',
            'parent_id' => $react->id,
        ]);

        // Try to move Engineering under its descendants - all should fail
        expect($engineering->validateNotCircularReference($frontend->id))->toBeFalse();
        expect($engineering->validateNotCircularReference($react->id))->toBeFalse();
        expect($engineering->validateNotCircularReference($components->id))->toBeFalse();

        // But Frontend can be moved under Engineering's sibling
        $backend = Department::factory()->create(['name' => 'Backend', 'code' => 'BE']);
        expect($frontend->validateNotCircularReference($backend->id))->toBeTrue();
    });
});

describe('Salary Grade with Steps Full Workflow', function () {
    it('manages full lifecycle of salary grade with steps including updates and deletion', function () {
        $tenant = Tenant::factory()->create();
        bindIntegrationTestTenant($tenant);

        $admin = createOrgIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create salary grade
        $salaryGrade = SalaryGrade::factory()->create([
            'name' => 'Grade A',
            'minimum_salary' => 50000.00,
            'midpoint_salary' => 75000.00,
            'maximum_salary' => 100000.00,
            'status' => 'active',
        ]);

        // Add initial steps
        $step1 = SalaryStep::factory()->create([
            'salary_grade_id' => $salaryGrade->id,
            'step_number' => 1,
            'amount' => 50000.00,
        ]);
        $step2 = SalaryStep::factory()->create([
            'salary_grade_id' => $salaryGrade->id,
            'step_number' => 2,
            'amount' => 62500.00,
        ]);
        $step3 = SalaryStep::factory()->create([
            'salary_grade_id' => $salaryGrade->id,
            'step_number' => 3,
            'amount' => 75000.00,
        ]);

        expect($salaryGrade->steps)->toHaveCount(3);

        // Update steps - remove one, modify another
        $step2->update(['amount' => 65000.00]);
        $step3->delete();

        $salaryGrade->refresh();
        expect($salaryGrade->steps)->toHaveCount(2);

        // Add a new step
        SalaryStep::factory()->create([
            'salary_grade_id' => $salaryGrade->id,
            'step_number' => 3,
            'amount' => 80000.00,
            'effective_date' => now()->addMonth(),
        ]);

        $salaryGrade->refresh();
        expect($salaryGrade->steps)->toHaveCount(3);

        // Verify ordering is maintained
        $steps = $salaryGrade->steps;
        expect($steps[0]->step_number)->toBe(1);
        expect($steps[1]->step_number)->toBe(2);
        expect($steps[2]->step_number)->toBe(3);

        // Delete grade - steps should also be deleted (via cascade or manual)
        $gradeId = $salaryGrade->id;
        $salaryGrade->steps()->delete();
        $salaryGrade->delete();

        expect(SalaryGrade::find($gradeId))->toBeNull();
        expect(SalaryStep::where('salary_grade_id', $gradeId)->count())->toBe(0);
    });
});

describe('Position Assignment Workflow', function () {
    it('handles position salary grade assignment and reassignment', function () {
        $tenant = Tenant::factory()->create();
        bindIntegrationTestTenant($tenant);

        $admin = createOrgIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create two salary grades
        $gradeA = SalaryGrade::factory()->create([
            'name' => 'Grade A - Junior',
            'minimum_salary' => 40000.00,
            'midpoint_salary' => 50000.00,
            'maximum_salary' => 60000.00,
        ]);

        $gradeB = SalaryGrade::factory()->create([
            'name' => 'Grade B - Senior',
            'minimum_salary' => 70000.00,
            'midpoint_salary' => 90000.00,
            'maximum_salary' => 110000.00,
        ]);

        // Create position with Grade A
        $position = Position::factory()->create([
            'title' => 'Software Developer',
            'code' => 'SWD-001',
            'salary_grade_id' => $gradeA->id,
            'job_level' => JobLevel::Junior,
            'employment_type' => EmploymentType::Regular,
            'status' => 'active',
        ]);

        expect($position->salaryGrade->id)->toBe($gradeA->id);
        expect($position->job_level)->toBe(JobLevel::Junior);

        // Promote position to Grade B
        $position->update([
            'salary_grade_id' => $gradeB->id,
            'job_level' => JobLevel::Senior,
        ]);

        $position->refresh();
        expect($position->salaryGrade->id)->toBe($gradeB->id);
        expect($position->job_level)->toBe(JobLevel::Senior);

        // Verify relationships from grade side
        expect($gradeB->positions)->toHaveCount(1);
        expect($gradeA->positions()->count())->toBe(0);

        // Remove salary grade assignment
        $position->update(['salary_grade_id' => null]);
        $position->refresh();

        expect($position->salaryGrade)->toBeNull();
    });
});

describe('Work Location Metadata Integration', function () {
    it('updates and retrieves complex metadata structures correctly', function () {
        $tenant = Tenant::factory()->create();
        bindIntegrationTestTenant($tenant);

        $admin = createOrgIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create location with complex metadata
        $initialMetadata = [
            'contact' => [
                'phone' => '+63-2-8888-1234',
                'email' => 'office@company.com',
                'fax' => '+63-2-8888-5678',
            ],
            'facilities' => ['parking', 'cafeteria', 'gym', 'daycare'],
            'capacity' => [
                'total' => 500,
                'floors' => 10,
                'meeting_rooms' => 25,
            ],
            'operating_hours' => [
                'weekday' => '08:00-18:00',
                'saturday' => '09:00-13:00',
                'sunday' => 'closed',
            ],
        ];

        $location = WorkLocation::factory()->create([
            'name' => 'Main Headquarters',
            'code' => 'HQ-001',
            'location_type' => LocationType::Headquarters,
            'metadata' => $initialMetadata,
            'status' => 'active',
        ]);

        // Verify nested metadata is preserved
        $location->refresh();
        expect($location->metadata['contact']['phone'])->toBe('+63-2-8888-1234');
        expect($location->metadata['facilities'])->toContain('gym');
        expect($location->metadata['capacity']['total'])->toBe(500);

        // Update metadata - add new fields, modify existing
        $updatedMetadata = $location->metadata;
        $updatedMetadata['contact']['mobile'] = '+63-917-123-4567';
        $updatedMetadata['capacity']['total'] = 600;
        $updatedMetadata['security'] = ['access_card' => true, 'biometric' => true];
        unset($updatedMetadata['operating_hours']['sunday']);

        $location->update(['metadata' => $updatedMetadata]);
        $location->refresh();

        expect($location->metadata['contact']['mobile'])->toBe('+63-917-123-4567');
        expect($location->metadata['capacity']['total'])->toBe(600);
        expect($location->metadata['security']['biometric'])->toBeTrue();
        expect(isset($location->metadata['operating_hours']['sunday']))->toBeFalse();
    });
});

describe('Department Soft Delete Cascade Behavior', function () {
    it('soft deletes department while preserving child departments', function () {
        $tenant = Tenant::factory()->create();
        bindIntegrationTestTenant($tenant);

        $admin = createOrgIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create hierarchy
        $parent = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);
        $child1 = Department::factory()->create([
            'name' => 'Frontend',
            'code' => 'FE',
            'parent_id' => $parent->id,
        ]);
        $child2 = Department::factory()->create([
            'name' => 'Backend',
            'code' => 'BE',
            'parent_id' => $parent->id,
        ]);

        // Soft delete parent
        $controller = new DepartmentController;
        $response = $controller->destroy($parent);

        expect($response->getStatusCode())->toBe(200);

        // Parent should be soft deleted
        expect(Department::find($parent->id))->toBeNull();
        expect(Department::withTrashed()->find($parent->id))->not->toBeNull();

        // Children should still exist and be accessible
        expect(Department::find($child1->id))->not->toBeNull();
        expect(Department::find($child2->id))->not->toBeNull();

        // Children still reference deleted parent
        $child1->refresh();
        expect($child1->parent_id)->toBe($parent->id);
        expect($child1->parent)->toBeNull(); // Parent not found without withTrashed
    });
});

describe('Department Head History Tracking Workflow', function () {
    it('tracks department head changes over time correctly', function () {
        $tenant = Tenant::factory()->create();
        bindIntegrationTestTenant($tenant);

        $admin = createOrgIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create department
        $department = Department::factory()->create([
            'name' => 'Marketing',
            'code' => 'MKT',
            'department_head_id' => null,
        ]);

        // Assign first head
        $firstHead = DepartmentHeadHistory::factory()->create([
            'department_id' => $department->id,
            'employee_id' => 1,
            'started_at' => now()->subYear(),
            'ended_at' => null,
        ]);

        expect(DepartmentHeadHistory::current()->where('department_id', $department->id)->count())->toBe(1);

        // Change head - end previous and start new
        $firstHead->update(['ended_at' => now()]);

        $secondHead = DepartmentHeadHistory::factory()->create([
            'department_id' => $department->id,
            'employee_id' => 2,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        // Verify only one current head
        $currentHeads = DepartmentHeadHistory::current()->where('department_id', $department->id)->get();
        expect($currentHeads)->toHaveCount(1);
        expect($currentHeads->first()->id)->toBe($secondHead->id);

        // Verify history is preserved
        expect($department->headHistory)->toHaveCount(2);
    });
});

describe('Cross-Model Integration', function () {
    it('creates complete organization structure with all models', function () {
        $tenant = Tenant::factory()->create();
        bindIntegrationTestTenant($tenant);

        $admin = createOrgIntegrationTestUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create location
        $location = WorkLocation::factory()->create([
            'name' => 'Manila Office',
            'code' => 'MNL',
            'location_type' => LocationType::Headquarters,
            'city' => 'Manila',
            'country' => 'PH',
            'status' => 'active',
        ]);

        // Create salary grades with steps
        $juniorGrade = SalaryGrade::factory()->create([
            'name' => 'Junior Grade',
            'minimum_salary' => 30000,
            'midpoint_salary' => 40000,
            'maximum_salary' => 50000,
        ]);
        SalaryStep::factory()->create([
            'salary_grade_id' => $juniorGrade->id,
            'step_number' => 1,
            'amount' => 30000,
        ]);
        SalaryStep::factory()->create([
            'salary_grade_id' => $juniorGrade->id,
            'step_number' => 2,
            'amount' => 40000,
        ]);

        $seniorGrade = SalaryGrade::factory()->create([
            'name' => 'Senior Grade',
            'minimum_salary' => 60000,
            'midpoint_salary' => 80000,
            'maximum_salary' => 100000,
        ]);

        // Create positions linked to grades
        $juniorPosition = Position::factory()->create([
            'title' => 'Junior Developer',
            'code' => 'JD-001',
            'salary_grade_id' => $juniorGrade->id,
            'job_level' => JobLevel::Junior,
            'employment_type' => EmploymentType::Regular,
        ]);

        $seniorPosition = Position::factory()->create([
            'title' => 'Senior Developer',
            'code' => 'SD-001',
            'salary_grade_id' => $seniorGrade->id,
            'job_level' => JobLevel::Senior,
            'employment_type' => EmploymentType::Regular,
        ]);

        // Create department hierarchy
        $company = Department::factory()->create([
            'name' => 'Company',
            'code' => 'COMP',
        ]);
        $engineering = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
            'parent_id' => $company->id,
        ]);
        $frontend = Department::factory()->create([
            'name' => 'Frontend',
            'code' => 'FE',
            'parent_id' => $engineering->id,
        ]);

        // Verify complete structure
        expect(WorkLocation::count())->toBe(1);
        expect(SalaryGrade::count())->toBe(2);
        expect(SalaryStep::count())->toBe(2);
        expect(Position::count())->toBe(2);
        expect(Department::count())->toBe(3);

        // Verify relationships
        expect($juniorGrade->steps)->toHaveCount(2);
        expect($juniorGrade->positions)->toHaveCount(1);
        expect($company->children)->toHaveCount(1);
        expect($engineering->children)->toHaveCount(1);
        expect(Department::root()->count())->toBe(1);
    });
});
