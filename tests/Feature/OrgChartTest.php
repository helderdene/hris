<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DepartmentController;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createOrgChartTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to bind tenant to the application container for tests.
 */
function bindOrgChartTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Org Chart Visualization', function () {
    it('renders department hierarchy correctly', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindOrgChartTenantContext($tenant);

        $admin = createOrgChartTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create hierarchical department structure
        $ceo = Department::factory()->create([
            'name' => 'Executive Office',
            'code' => 'CEO',
            'status' => 'active',
        ]);

        $engineering = Department::factory()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
            'parent_id' => $ceo->id,
            'status' => 'active',
        ]);

        $frontend = Department::factory()->create([
            'name' => 'Frontend Team',
            'code' => 'FE',
            'parent_id' => $engineering->id,
            'status' => 'active',
        ]);

        $hr = Department::factory()->create([
            'name' => 'Human Resources',
            'code' => 'HR',
            'parent_id' => $ceo->id,
            'status' => 'active',
        ]);

        // Test API returns hierarchy correctly using controller directly
        $controller = new DepartmentController;
        $response = $controller->index();

        // Should return all 4 departments
        expect($response->count())->toBe(4);

        // Convert response to array for easier assertions
        $data = $response->collection->map(fn ($item) => $item->toArray(request()))->toArray();

        // CEO should be root (no parent)
        $ceoData = collect($data)->firstWhere('code', 'CEO');
        expect($ceoData['parent_id'])->toBeNull();

        // Engineering should have CEO as parent
        $engData = collect($data)->firstWhere('code', 'ENG');
        expect($engData['parent_id'])->toBe($ceo->id);

        // Frontend should have Engineering as parent
        $feData = collect($data)->firstWhere('code', 'FE');
        expect($feData['parent_id'])->toBe($engineering->id);

        // HR should have CEO as parent
        $hrData = collect($data)->firstWhere('code', 'HR');
        expect($hrData['parent_id'])->toBe($ceo->id);
    });

    it('displays department details including name, code, and head', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testcorp']);
        bindOrgChartTenantContext($tenant);

        $admin = createOrgChartTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create department with all details
        $department = Department::factory()->create([
            'name' => 'Marketing Department',
            'code' => 'MKT',
            'description' => 'Handles all marketing activities',
            'status' => 'active',
            'department_head_id' => null, // No head assigned yet
        ]);

        // Test API returns department details correctly using controller directly
        $controller = new DepartmentController;
        $response = $controller->show($department);

        $data = $response->toArray(request());

        // Verify all department details are returned
        expect($data['name'])->toBe('Marketing Department');
        expect($data['code'])->toBe('MKT');
        expect($data['status'])->toBe('active');
        expect($data['department_head_id'])->toBeNull();
        expect($data['description'])->toBe('Handles all marketing activities');
    });

    it('returns departments for tree structure with proper relationships', function () {
        $tenant = Tenant::factory()->create(['slug' => 'responsive']);
        bindOrgChartTenantContext($tenant);

        $admin = createOrgChartTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create a complex hierarchy for testing responsive behavior
        $root = Department::factory()->create([
            'name' => 'Company',
            'code' => 'COMP',
            'status' => 'active',
        ]);

        // Create multiple branches to test width handling
        $dept1 = Department::factory()->create([
            'name' => 'Sales',
            'code' => 'SALES',
            'parent_id' => $root->id,
            'status' => 'active',
        ]);

        $dept2 = Department::factory()->create([
            'name' => 'Operations',
            'code' => 'OPS',
            'parent_id' => $root->id,
            'status' => 'active',
        ]);

        $dept3 = Department::factory()->create([
            'name' => 'Finance',
            'code' => 'FIN',
            'parent_id' => $root->id,
            'status' => 'inactive',
        ]);

        // Test API returns correct data using controller directly
        $controller = new DepartmentController;
        $response = $controller->index();

        // Verify we get all departments including inactive
        expect($response->count())->toBe(4);

        // Convert response to array for easier assertions
        $data = $response->collection->map(fn ($item) => $item->toArray(request()))->toArray();

        // Verify root department has children
        $rootData = collect($data)->firstWhere('code', 'COMP');
        expect($rootData['children_count'])->toBe(3);

        // Verify status values are included for styling
        $finData = collect($data)->firstWhere('code', 'FIN');
        expect($finData['status'])->toBe('inactive');
    });
});
