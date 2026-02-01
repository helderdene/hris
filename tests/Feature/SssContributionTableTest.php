<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\SssContributionController;
use App\Http\Requests\StoreSssContributionTableRequest;
use App\Models\SssContributionTable;
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
function bindTenantContextForSss(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForSss(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store SSS table request.
 */
function createStoreSssRequest(array $data, User $user): StoreSssContributionTableRequest
{
    $request = StoreSssContributionTableRequest::create('/api/organization/contributions/sss', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreSssContributionTableRequest)->rules());
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

describe('SSS Contribution Table API', function () {
    it('returns list of SSS contribution tables', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $hrManager = createTenantUserForSss($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create SSS tables with different effective dates
        SssContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => '2024 SSS Table',
            'is_active' => true,
        ]);

        SssContributionTable::factory()->create([
            'effective_from' => '2025-01-01',
            'description' => '2025 SSS Table',
            'is_active' => true,
        ]);

        $controller = new SssContributionController;
        $request = Request::create('/api/organization/contributions/sss', 'GET');
        $response = $controller->index($request);

        // Response is AnonymousResourceCollection - access as collection
        expect($response->count())->toBe(2);
        expect($response->first()->description)->toBe('2025 SSS Table');
    });

    it('creates SSS table with brackets', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $hrManager = createTenantUserForSss($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new SssContributionController;

        $tableData = [
            'effective_from' => '2025-01-01',
            'description' => 'Test SSS Table',
            'employee_rate' => 0.045,
            'employer_rate' => 0.095,
            'is_active' => true,
            'brackets' => [
                [
                    'min_salary' => 0,
                    'max_salary' => 4249.99,
                    'monthly_salary_credit' => 4000,
                    'employee_contribution' => 180,
                    'employer_contribution' => 380,
                    'total_contribution' => 560,
                    'ec_contribution' => 10,
                ],
                [
                    'min_salary' => 4250,
                    'max_salary' => 4749.99,
                    'monthly_salary_credit' => 4500,
                    'employee_contribution' => 202.50,
                    'employer_contribution' => 427.50,
                    'total_contribution' => 630,
                    'ec_contribution' => 10,
                ],
            ],
        ];

        $storeRequest = createStoreSssRequest($tableData, $hrManager);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        // When calling controller directly (not through HTTP), Resource doesn't wrap in 'data'
        $data = json_decode($response->getContent(), true);
        expect($data['description'])->toBe('Test SSS Table');
        expect($data['brackets'])->toHaveCount(2);

        // Verify database records
        $this->assertDatabaseHas('sss_contribution_tables', [
            'description' => 'Test SSS Table',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('sss_contribution_brackets', [
            'monthly_salary_credit' => 4000,
            'employee_contribution' => 180,
        ]);
    });

    it('shows single SSS table with brackets', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $hrManager = createTenantUserForSss($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = SssContributionTable::factory()->withBrackets()->create([
            'effective_from' => '2025-01-01',
            'description' => 'Test Table',
        ]);

        $controller = new SssContributionController;
        $response = $controller->show($table);

        // Response is SssContributionTableResource - access properties directly
        expect($response->description)->toBe('Test Table');
        expect($response->brackets)->not->toBeEmpty();
    });

    it('updates SSS table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $hrManager = createTenantUserForSss($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = SssContributionTable::factory()->withBrackets()->create([
            'description' => 'Original Description',
        ]);

        $controller = new SssContributionController;

        $updateData = [
            'effective_from' => $table->effective_from->format('Y-m-d'),
            'description' => 'Updated Description',
            'employee_rate' => $table->employee_rate,
            'employer_rate' => $table->employer_rate,
            'is_active' => true,
            'brackets' => $table->brackets->map(fn ($b) => [
                'min_salary' => $b->min_salary,
                'max_salary' => $b->max_salary,
                'monthly_salary_credit' => $b->monthly_salary_credit,
                'employee_contribution' => $b->employee_contribution,
                'employer_contribution' => $b->employer_contribution,
                'total_contribution' => $b->total_contribution,
                'ec_contribution' => $b->ec_contribution,
            ])->toArray(),
        ];

        $request = StoreSssContributionTableRequest::create(
            "/api/organization/contributions/sss/{$table->id}",
            'PUT',
            $updateData
        );
        $request->setUserResolver(fn () => $hrManager);
        $request->setContainer(app());

        $validator = Validator::make($updateData, (new StoreSssContributionTableRequest)->rules());

        $reflection = new ReflectionClass($request);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($request, $validator);

        $response = $controller->update($request, $table);

        // Response is SssContributionTableResource - access properties directly
        expect($response->description)->toBe('Updated Description');

        $this->assertDatabaseHas('sss_contribution_tables', [
            'id' => $table->id,
            'description' => 'Updated Description',
        ]);
    });

    it('soft deletes SSS table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $hrManager = createTenantUserForSss($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = SssContributionTable::factory()->create();

        $controller = new SssContributionController;
        $response = $controller->destroy($table);

        expect($response->getStatusCode())->toBe(200);

        $this->assertSoftDeleted('sss_contribution_tables', [
            'id' => $table->id,
        ]);
    });

    it('validates required fields when creating SSS table', function () {
        $rules = (new StoreSssContributionTableRequest)->rules();

        // Test missing required fields
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('effective_from'))->toBeTrue();
        expect($validator->errors()->has('brackets'))->toBeTrue();

        // Test valid data passes
        $validData = [
            'effective_from' => '2025-01-01',
            'employee_rate' => 0.045,
            'employer_rate' => 0.095,
            'is_active' => true,
            'brackets' => [
                [
                    'min_salary' => 0,
                    'max_salary' => 4249.99,
                    'monthly_salary_credit' => 4000,
                    'employee_contribution' => 180,
                    'employer_contribution' => 380,
                    'total_contribution' => 560,
                    'ec_contribution' => 10,
                ],
            ],
        ];

        $validValidator = Validator::make($validData, $rules);
        expect($validValidator->fails())->toBeFalse();
    });

    it('prevents unauthorized user from creating SSS table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $employee = createTenantUserForSss($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $controller = new SssContributionController;

        $tableData = [
            'effective_from' => '2025-01-01',
            'employee_rate' => 0.045,
            'employer_rate' => 0.095,
            'is_active' => true,
            'brackets' => [
                [
                    'min_salary' => 0,
                    'max_salary' => 4249.99,
                    'monthly_salary_credit' => 4000,
                    'employee_contribution' => 180,
                    'employer_contribution' => 380,
                    'total_contribution' => 560,
                    'ec_contribution' => 10,
                ],
            ],
        ];

        $request = createStoreSssRequest($tableData, $employee);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->store($request);
    });
});

describe('SSS Contribution Table Model', function () {
    it('finds current active table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        // Create older table
        SssContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => 'Old Table',
            'is_active' => true,
        ]);

        // Create current table
        $currentTable = SssContributionTable::factory()->create([
            'effective_from' => now()->subMonth(),
            'description' => 'Current Table',
            'is_active' => true,
        ]);

        // Create future table
        SssContributionTable::factory()->create([
            'effective_from' => now()->addMonth(),
            'description' => 'Future Table',
            'is_active' => true,
        ]);

        $current = SssContributionTable::current();

        expect($current)->not->toBeNull();
        expect($current->id)->toBe($currentTable->id);
        expect($current->description)->toBe('Current Table');
    });

    it('finds table effective at specific date', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        SssContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => '2024 Table',
            'is_active' => true,
        ]);

        $table2025 = SssContributionTable::factory()->create([
            'effective_from' => '2025-01-01',
            'description' => '2025 Table',
            'is_active' => true,
        ]);

        // Query for mid-2025 date
        $effectiveTable = SssContributionTable::effectiveAt(\Carbon\Carbon::parse('2025-06-15'));

        expect($effectiveTable)->not->toBeNull();
        expect($effectiveTable->id)->toBe($table2025->id);
    });

    it('finds correct bracket for salary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $table = SssContributionTable::factory()->withBrackets()->create();

        // Test salary at ₱25,000 (should be MSC ₱25,000 bracket)
        $bracket = $table->findBracketForSalary(25000);

        expect($bracket)->not->toBeNull();
        expect((float) $bracket->monthly_salary_credit)->toEqual(25000.00);
        expect((float) $bracket->employee_contribution)->toEqual(1125.00);
        expect((float) $bracket->employer_contribution)->toEqual(2375.00);
    });

    it('finds bracket for salary at boundary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $table = SssContributionTable::factory()->withBrackets()->create();

        // Test salary exactly at min boundary ₱4,250
        $bracket = $table->findBracketForSalary(4250);

        expect($bracket)->not->toBeNull();
        expect((float) $bracket->monthly_salary_credit)->toEqual(4500.00);
    });

    it('finds bracket for minimum salary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $table = SssContributionTable::factory()->withBrackets()->create();

        // Test minimum salary
        $bracket = $table->findBracketForSalary(1000);

        expect($bracket)->not->toBeNull();
        expect((float) $bracket->monthly_salary_credit)->toEqual(4000.00);
    });

    it('finds bracket for maximum salary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        $table = SssContributionTable::factory()->withBrackets()->create();

        // Test salary above max bracket
        $bracket = $table->findBracketForSalary(50000);

        expect($bracket)->not->toBeNull();
        expect((float) $bracket->monthly_salary_credit)->toEqual(30000.00);
    });

    it('excludes inactive tables from current lookup', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForSss($tenant);

        SssContributionTable::factory()->inactive()->create([
            'effective_from' => now()->subDay(),
            'description' => 'Inactive Table',
        ]);

        $current = SssContributionTable::current();

        expect($current)->toBeNull();
    });
});
