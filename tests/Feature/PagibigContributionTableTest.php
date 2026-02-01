<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PagibigContributionController;
use App\Http\Requests\StorePagibigContributionTableRequest;
use App\Models\PagibigContributionTable;
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
function bindTenantContextForPagibig(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPagibig(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store Pag-IBIG table request.
 */
function createStorePagibigRequest(array $data, User $user): StorePagibigContributionTableRequest
{
    $request = StorePagibigContributionTableRequest::create('/api/organization/contributions/pagibig', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StorePagibigContributionTableRequest)->rules());
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

describe('Pag-IBIG Contribution Table API', function () {
    it('returns list of Pag-IBIG contribution tables', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $hrManager = createTenantUserForPagibig($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        PagibigContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => '2024 Pag-IBIG Table',
            'is_active' => true,
        ]);

        PagibigContributionTable::factory()->create([
            'effective_from' => '2025-01-01',
            'description' => '2025 Pag-IBIG Table',
            'is_active' => true,
        ]);

        $controller = new PagibigContributionController;
        $request = Request::create('/api/organization/contributions/pagibig', 'GET');
        $response = $controller->index($request);

        // Response is AnonymousResourceCollection - access as collection
        expect($response->count())->toBe(2);
        expect($response->first()->description)->toBe('2025 Pag-IBIG Table');
    });

    it('creates Pag-IBIG table with tiers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $hrManager = createTenantUserForPagibig($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new PagibigContributionController;

        $tableData = [
            'effective_from' => '2025-01-01',
            'description' => 'Test Pag-IBIG Table',
            'max_monthly_compensation' => 5000,
            'is_active' => true,
            'tiers' => [
                [
                    'min_salary' => 0,
                    'max_salary' => 1500,
                    'employee_rate' => 0.01,
                    'employer_rate' => 0.02,
                ],
                [
                    'min_salary' => 1500.01,
                    'max_salary' => null,
                    'employee_rate' => 0.02,
                    'employer_rate' => 0.02,
                ],
            ],
        ];

        $storeRequest = createStorePagibigRequest($tableData, $hrManager);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        // When calling controller directly (not through HTTP), Resource doesn't wrap in 'data'
        $data = json_decode($response->getContent(), true);
        expect($data['description'])->toBe('Test Pag-IBIG Table');
        expect($data['tiers'])->toHaveCount(2);

        $this->assertDatabaseHas('pagibig_contribution_tables', [
            'description' => 'Test Pag-IBIG Table',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('pagibig_contribution_tiers', [
            'employee_rate' => 0.01,
            'employer_rate' => 0.02,
        ]);
    });

    it('shows single Pag-IBIG table with tiers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $hrManager = createTenantUserForPagibig($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = PagibigContributionTable::factory()->withTiers()->create([
            'effective_from' => '2025-01-01',
            'description' => 'Test Table',
        ]);

        $controller = new PagibigContributionController;
        $response = $controller->show($table);

        // Response is PagibigContributionTableResource - access properties directly
        expect($response->description)->toBe('Test Table');
        expect($response->tiers)->not->toBeEmpty();
    });

    it('updates Pag-IBIG table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $hrManager = createTenantUserForPagibig($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = PagibigContributionTable::factory()->withTiers()->create([
            'description' => 'Original Description',
        ]);

        $controller = new PagibigContributionController;

        $updateData = [
            'effective_from' => $table->effective_from->format('Y-m-d'),
            'description' => 'Updated Description',
            'max_monthly_compensation' => $table->max_monthly_compensation,
            'is_active' => true,
            'tiers' => $table->tiers->map(fn ($t) => [
                'min_salary' => $t->min_salary,
                'max_salary' => $t->max_salary,
                'employee_rate' => $t->employee_rate,
                'employer_rate' => $t->employer_rate,
            ])->toArray(),
        ];

        $request = StorePagibigContributionTableRequest::create(
            "/api/organization/contributions/pagibig/{$table->id}",
            'PUT',
            $updateData
        );
        $request->setUserResolver(fn () => $hrManager);
        $request->setContainer(app());

        $validator = Validator::make($updateData, (new StorePagibigContributionTableRequest)->rules());

        $reflection = new ReflectionClass($request);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($request, $validator);

        $response = $controller->update($request, $table);

        // Response is PagibigContributionTableResource - access properties directly
        expect($response->description)->toBe('Updated Description');

        $this->assertDatabaseHas('pagibig_contribution_tables', [
            'id' => $table->id,
            'description' => 'Updated Description',
        ]);
    });

    it('soft deletes Pag-IBIG table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $hrManager = createTenantUserForPagibig($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = PagibigContributionTable::factory()->create();

        $controller = new PagibigContributionController;
        $response = $controller->destroy($table);

        expect($response->getStatusCode())->toBe(200);

        $this->assertSoftDeleted('pagibig_contribution_tables', [
            'id' => $table->id,
        ]);
    });

    it('validates required fields when creating Pag-IBIG table', function () {
        $rules = (new StorePagibigContributionTableRequest)->rules();

        // Test missing required fields
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('effective_from'))->toBeTrue();
        expect($validator->errors()->has('tiers'))->toBeTrue();

        // Test valid data passes
        $validData = [
            'effective_from' => '2025-01-01',
            'max_monthly_compensation' => 5000,
            'is_active' => true,
            'tiers' => [
                [
                    'min_salary' => 0,
                    'max_salary' => 1500,
                    'employee_rate' => 0.01,
                    'employer_rate' => 0.02,
                ],
            ],
        ];

        $validValidator = Validator::make($validData, $rules);
        expect($validValidator->fails())->toBeFalse();
    });

    it('prevents unauthorized user from creating Pag-IBIG table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $employee = createTenantUserForPagibig($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $controller = new PagibigContributionController;

        $tableData = [
            'effective_from' => '2025-01-01',
            'max_monthly_compensation' => 5000,
            'is_active' => true,
            'tiers' => [
                [
                    'min_salary' => 0,
                    'max_salary' => 1500,
                    'employee_rate' => 0.01,
                    'employer_rate' => 0.02,
                ],
            ],
        ];

        $request = createStorePagibigRequest($tableData, $employee);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->store($request);
    });
});

describe('Pag-IBIG Contribution Table Model', function () {
    it('finds current active table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        PagibigContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => 'Old Table',
            'is_active' => true,
        ]);

        $currentTable = PagibigContributionTable::factory()->create([
            'effective_from' => now()->subMonth(),
            'description' => 'Current Table',
            'is_active' => true,
        ]);

        PagibigContributionTable::factory()->create([
            'effective_from' => now()->addMonth(),
            'description' => 'Future Table',
            'is_active' => true,
        ]);

        $current = PagibigContributionTable::current();

        expect($current)->not->toBeNull();
        expect($current->id)->toBe($currentTable->id);
        expect($current->description)->toBe('Current Table');
    });

    it('finds table effective at specific date', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        PagibigContributionTable::factory()->create([
            'effective_from' => '2024-01-01',
            'description' => '2024 Table',
            'is_active' => true,
        ]);

        $table2025 = PagibigContributionTable::factory()->create([
            'effective_from' => '2025-01-01',
            'description' => '2025 Table',
            'is_active' => true,
        ]);

        $effectiveTable = PagibigContributionTable::effectiveAt(\Carbon\Carbon::parse('2025-06-15'));

        expect($effectiveTable)->not->toBeNull();
        expect($effectiveTable->id)->toBe($table2025->id);
    });

    it('calculates contribution for salary in lower tier', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $table = PagibigContributionTable::factory()->withTiers()->create();

        // Test salary of ₱1,200 (in lower tier: 1% employee, 2% employer)
        // Capped at max_monthly_compensation of ₱5,000
        // Basis is min(1200, 5000) = ₱1,200
        // Employee: 1200 * 0.01 = ₱12
        // Employer: 1200 * 0.02 = ₱24
        $contribution = $table->calculateContribution(1200);

        expect($contribution['employee_share'])->toBe(12.00);
        expect($contribution['employer_share'])->toBe(24.00);
        expect($contribution['total'])->toBe(36.00);
    });

    it('calculates contribution for salary in higher tier', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $table = PagibigContributionTable::factory()->withTiers()->create();

        // Test salary of ₱3,000 (in higher tier: 2% employee, 2% employer)
        // Basis is min(3000, 5000) = ₱3,000
        // Employee: 3000 * 0.02 = ₱60
        // Employer: 3000 * 0.02 = ₱60
        $contribution = $table->calculateContribution(3000);

        expect($contribution['employee_share'])->toBe(60.00);
        expect($contribution['employer_share'])->toBe(60.00);
        expect($contribution['total'])->toBe(120.00);
    });

    it('caps contribution at max monthly compensation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $table = PagibigContributionTable::factory()->withTiers()->create([
            'max_monthly_compensation' => 5000,
        ]);

        // Test salary of ₱50,000 (above max compensation of ₱5,000)
        // Basis should be capped at ₱5,000
        // Employee: 5000 * 0.02 = ₱100
        // Employer: 5000 * 0.02 = ₱100
        $contribution = $table->calculateContribution(50000);

        expect((float) $contribution['basis_salary'])->toEqual(5000.00);
        expect((float) $contribution['employee_share'])->toEqual(100.00);
        expect((float) $contribution['employer_share'])->toEqual(100.00);
        expect((float) $contribution['total'])->toEqual(200.00);
    });

    it('calculates contribution at tier boundary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $table = PagibigContributionTable::factory()->withTiers()->create();

        // Test salary exactly at ₱1,500 (boundary - should be in lower tier)
        $contribution = $table->calculateContribution(1500);

        // Lower tier: 1% employee, 2% employer
        expect($contribution['employee_share'])->toBe(15.00);
        expect($contribution['employer_share'])->toBe(30.00);
        expect($contribution['total'])->toBe(45.00);
    });

    it('calculates contribution just above tier boundary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        $table = PagibigContributionTable::factory()->withTiers()->create();

        // Test salary at ₱2,000 (above boundary - should be in higher tier at 2%/2%)
        $contribution = $table->calculateContribution(2000);

        // Higher tier (2% employee, 2% employer): employee = 40.00, employer = 40.00
        // Lower tier would give: employee = 20.00 (1%), employer = 40.00 (2%)
        // Verify we're in higher tier by checking employee_share > what lower tier would give
        expect((float) $contribution['employee_share'])->toEqual(40.00);
        expect((float) $contribution['employer_share'])->toEqual(40.00);
    });

    it('excludes inactive tables from current lookup', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        PagibigContributionTable::factory()->inactive()->create([
            'effective_from' => now()->subDay(),
            'description' => 'Inactive Table',
        ]);

        $current = PagibigContributionTable::current();

        expect($current)->toBeNull();
    });

    it('returns null contribution when no tiers defined', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPagibig($tenant);

        // Create table without tiers
        $table = PagibigContributionTable::factory()->create();

        $contribution = $table->calculateContribution(25000);

        expect((float) $contribution['employee_share'])->toEqual(0.0);
        expect((float) $contribution['employer_share'])->toEqual(0.0);
        expect((float) $contribution['total'])->toEqual(0.0);
    });
});
