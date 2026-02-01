<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PhilhealthContributionController;
use App\Http\Requests\StorePhilhealthContributionTableRequest;
use App\Models\PhilhealthContributionTable;
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
function bindTenantContextForPhilhealth(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPhilhealth(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store PhilHealth table request.
 */
function createStorePhilhealthRequest(array $data, User $user): StorePhilhealthContributionTableRequest
{
    $request = StorePhilhealthContributionTableRequest::create('/api/organization/contributions/philhealth', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StorePhilhealthContributionTableRequest)->rules());
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

describe('PhilHealth Contribution Table API', function () {
    it('returns list of PhilHealth contribution tables', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $hrManager = createTenantUserForPhilhealth($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        PhilhealthContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => '2024 PhilHealth Table',
            'is_active' => true,
        ]);

        PhilhealthContributionTable::factory()->create([
            'effective_from' => '2025-01-01',
            'description' => '2025 PhilHealth Table',
            'is_active' => true,
        ]);

        $controller = new PhilhealthContributionController;
        $request = Request::create('/api/organization/contributions/philhealth', 'GET');
        $response = $controller->index($request);

        // Response is AnonymousResourceCollection - access as collection
        expect($response->count())->toBe(2);
        expect($response->first()->description)->toBe('2025 PhilHealth Table');
    });

    it('creates PhilHealth table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $hrManager = createTenantUserForPhilhealth($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new PhilhealthContributionController;

        $tableData = [
            'effective_from' => '2025-01-01',
            'description' => 'Test PhilHealth Table',
            'contribution_rate' => 0.05,
            'employee_share_rate' => 0.5,
            'employer_share_rate' => 0.5,
            'salary_floor' => 10000,
            'salary_ceiling' => 100000,
            'min_contribution' => 500,
            'max_contribution' => 5000,
            'is_active' => true,
        ];

        $storeRequest = createStorePhilhealthRequest($tableData, $hrManager);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        // When calling controller directly (not through HTTP), Resource doesn't wrap in 'data'
        $data = json_decode($response->getContent(), true);
        expect($data['description'])->toBe('Test PhilHealth Table');
        expect($data['contribution_rate_percent'])->toEqual(5.0);

        $this->assertDatabaseHas('philhealth_contribution_tables', [
            'description' => 'Test PhilHealth Table',
            'contribution_rate' => 0.05,
            'is_active' => true,
        ]);
    });

    it('shows single PhilHealth table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $hrManager = createTenantUserForPhilhealth($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = PhilhealthContributionTable::factory()->create([
            'effective_from' => '2025-01-01',
            'description' => 'Test Table',
        ]);

        $controller = new PhilhealthContributionController;
        $response = $controller->show($table);

        // Response is PhilhealthContributionTableResource - access properties directly
        expect($response->description)->toBe('Test Table');
    });

    it('updates PhilHealth table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $hrManager = createTenantUserForPhilhealth($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = PhilhealthContributionTable::factory()->create([
            'description' => 'Original Description',
        ]);

        $controller = new PhilhealthContributionController;

        $updateData = [
            'effective_from' => $table->effective_from->format('Y-m-d'),
            'description' => 'Updated Description',
            'contribution_rate' => $table->contribution_rate,
            'employee_share_rate' => $table->employee_share_rate,
            'employer_share_rate' => $table->employer_share_rate,
            'salary_floor' => $table->salary_floor,
            'salary_ceiling' => $table->salary_ceiling,
            'min_contribution' => $table->min_contribution,
            'max_contribution' => $table->max_contribution,
            'is_active' => true,
        ];

        $request = StorePhilhealthContributionTableRequest::create(
            "/api/organization/contributions/philhealth/{$table->id}",
            'PUT',
            $updateData
        );
        $request->setUserResolver(fn () => $hrManager);
        $request->setContainer(app());

        $validator = Validator::make($updateData, (new StorePhilhealthContributionTableRequest)->rules());

        $reflection = new ReflectionClass($request);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($request, $validator);

        $response = $controller->update($request, $table);

        // Response is PhilhealthContributionTableResource - access properties directly
        expect($response->description)->toBe('Updated Description');

        $this->assertDatabaseHas('philhealth_contribution_tables', [
            'id' => $table->id,
            'description' => 'Updated Description',
        ]);
    });

    it('soft deletes PhilHealth table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $hrManager = createTenantUserForPhilhealth($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = PhilhealthContributionTable::factory()->create();

        $controller = new PhilhealthContributionController;
        $response = $controller->destroy($table);

        expect($response->getStatusCode())->toBe(200);

        $this->assertSoftDeleted('philhealth_contribution_tables', [
            'id' => $table->id,
        ]);
    });

    it('validates required fields when creating PhilHealth table', function () {
        $rules = (new StorePhilhealthContributionTableRequest)->rules();

        // Test missing required fields
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('effective_from'))->toBeTrue();
        expect($validator->errors()->has('contribution_rate'))->toBeTrue();
        expect($validator->errors()->has('salary_floor'))->toBeTrue();
        expect($validator->errors()->has('salary_ceiling'))->toBeTrue();

        // Test valid data passes
        $validData = [
            'effective_from' => '2025-01-01',
            'contribution_rate' => 0.05,
            'employee_share_rate' => 0.5,
            'employer_share_rate' => 0.5,
            'salary_floor' => 10000,
            'salary_ceiling' => 100000,
            'min_contribution' => 500,
            'max_contribution' => 5000,
            'is_active' => true,
        ];

        $validValidator = Validator::make($validData, $rules);
        expect($validValidator->fails())->toBeFalse();
    });

    it('prevents unauthorized user from creating PhilHealth table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $employee = createTenantUserForPhilhealth($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $controller = new PhilhealthContributionController;

        $tableData = [
            'effective_from' => '2025-01-01',
            'contribution_rate' => 0.05,
            'employee_share_rate' => 0.5,
            'employer_share_rate' => 0.5,
            'salary_floor' => 10000,
            'salary_ceiling' => 100000,
            'min_contribution' => 500,
            'max_contribution' => 5000,
            'is_active' => true,
        ];

        $request = createStorePhilhealthRequest($tableData, $employee);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->store($request);
    });
});

describe('PhilHealth Contribution Table Model', function () {
    it('finds current active table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        PhilhealthContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => 'Old Table',
            'is_active' => true,
        ]);

        $currentTable = PhilhealthContributionTable::factory()->create([
            'effective_from' => now()->subMonth(),
            'description' => 'Current Table',
            'is_active' => true,
        ]);

        PhilhealthContributionTable::factory()->create([
            'effective_from' => now()->addMonth(),
            'description' => 'Future Table',
            'is_active' => true,
        ]);

        $current = PhilhealthContributionTable::current();

        expect($current)->not->toBeNull();
        expect($current->id)->toBe($currentTable->id);
        expect($current->description)->toBe('Current Table');
    });

    it('finds table effective at specific date', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        PhilhealthContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => '2024 Table',
            'is_active' => true,
        ]);

        $table2025 = PhilhealthContributionTable::factory()->create([
            'effective_from' => '2025-01-01',
            'description' => '2025 Table',
            'is_active' => true,
        ]);

        $effectiveTable = PhilhealthContributionTable::effectiveAt(\Carbon\Carbon::parse('2025-06-15'));

        expect($effectiveTable)->not->toBeNull();
        expect($effectiveTable->id)->toBe($table2025->id);
    });

    it('calculates contribution for salary within range', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $table = PhilhealthContributionTable::factory()->year2025()->create();

        // Test salary of ₱25,000 (within floor-ceiling range)
        // 5% rate = ₱1,250 total, split 50/50
        $contribution = $table->calculateContribution(25000);

        expect($contribution['total'])->toBe(1250.00);
        expect($contribution['employee_share'])->toBe(625.00);
        expect($contribution['employer_share'])->toBe(625.00);
        expect($contribution['basis_salary'])->toBe(25000.00);
    });

    it('calculates contribution for salary below floor', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $table = PhilhealthContributionTable::factory()->year2025()->create();

        // Test salary of ₱5,000 (below floor of ₱10,000)
        // Should use floor as basis: 5% of ₱10,000 = ₱500 total
        $contribution = $table->calculateContribution(5000);

        expect((float) $contribution['total'])->toEqual(500.00);
        expect((float) $contribution['employee_share'])->toEqual(250.00);
        expect((float) $contribution['employer_share'])->toEqual(250.00);
        expect((float) $contribution['basis_salary'])->toEqual(10000.00);
    });

    it('calculates contribution for salary above ceiling', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        $table = PhilhealthContributionTable::factory()->year2025()->create();

        // Test salary of ₱150,000 (above ceiling of ₱100,000)
        // Should use ceiling as basis: 5% of ₱100,000 = ₱5,000 total
        $contribution = $table->calculateContribution(150000);

        expect((float) $contribution['total'])->toEqual(5000.00);
        expect((float) $contribution['employee_share'])->toEqual(2500.00);
        expect((float) $contribution['employer_share'])->toEqual(2500.00);
        expect((float) $contribution['basis_salary'])->toEqual(100000.00);
    });

    it('applies minimum contribution limit', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        // Create table with higher min contribution
        $table = PhilhealthContributionTable::factory()->create([
            'contribution_rate' => 0.01, // 1%
            'salary_floor' => 10000,
            'salary_ceiling' => 100000,
            'min_contribution' => 1000, // Higher min
            'max_contribution' => 5000,
            'employee_share_rate' => 0.5,
            'employer_share_rate' => 0.5,
        ]);

        // 1% of ₱20,000 = ₱200, but min is ₱1,000
        $contribution = $table->calculateContribution(20000);

        expect($contribution['total'])->toBe(1000.00);
    });

    it('applies maximum contribution limit', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        // Create table with lower max contribution
        $table = PhilhealthContributionTable::factory()->create([
            'contribution_rate' => 0.10, // 10%
            'salary_floor' => 10000,
            'salary_ceiling' => 100000,
            'min_contribution' => 500,
            'max_contribution' => 3000, // Lower max
            'employee_share_rate' => 0.5,
            'employer_share_rate' => 0.5,
        ]);

        // 10% of ₱50,000 = ₱5,000, but max is ₱3,000
        $contribution = $table->calculateContribution(50000);

        expect($contribution['total'])->toBe(3000.00);
    });

    it('excludes inactive tables from current lookup', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPhilhealth($tenant);

        PhilhealthContributionTable::factory()->inactive()->create([
            'effective_from' => now()->subDay(),
            'description' => 'Inactive Table',
        ]);

        $current = PhilhealthContributionTable::current();

        expect($current)->toBeNull();
    });
});
