<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\WithholdingTaxController;
use App\Http\Requests\StoreWithholdingTaxTableRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WithholdingTaxBracket;
use App\Models\WithholdingTaxTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForTax(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForTax(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store withholding tax table request.
 */
function createStoreTaxRequest(array $data, User $user): StoreWithholdingTaxTableRequest
{
    $request = StoreWithholdingTaxTableRequest::create('/api/organization/contributions/tax', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreWithholdingTaxTableRequest)->rules());
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

describe('Withholding Tax Table API', function () {
    it('returns list of withholding tax tables', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $hrManager = createTenantUserForTax($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create tax tables with different pay periods
        WithholdingTaxTable::factory()->monthly()->create([
            'effective_from' => '2024-01-01',
            'description' => 'Monthly Tax Table 2024',
            'is_active' => true,
        ]);

        WithholdingTaxTable::factory()->semiMonthly()->create([
            'effective_from' => '2025-01-01',
            'description' => 'Semi-Monthly Tax Table 2025',
            'is_active' => true,
        ]);

        $controller = new WithholdingTaxController;
        $request = Request::create('/api/organization/contributions/tax', 'GET');
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('creates withholding tax table with brackets', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $hrManager = createTenantUserForTax($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new WithholdingTaxController;

        $tableData = [
            'pay_period' => 'monthly',
            'effective_from' => '2025-01-01',
            'description' => 'TRAIN Law 2025',
            'is_active' => true,
            'brackets' => [
                [
                    'min_compensation' => 0,
                    'max_compensation' => 20833,
                    'base_tax' => 0,
                    'excess_rate' => 0,
                ],
                [
                    'min_compensation' => 20833,
                    'max_compensation' => 33333,
                    'base_tax' => 0,
                    'excess_rate' => 0.15,
                ],
                [
                    'min_compensation' => 33333,
                    'max_compensation' => 66667,
                    'base_tax' => 1875,
                    'excess_rate' => 0.20,
                ],
            ],
        ];

        $storeRequest = createStoreTaxRequest($tableData, $hrManager);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['description'])->toBe('TRAIN Law 2025');
        expect($data['pay_period'])->toBe('monthly');
        expect($data['brackets'])->toHaveCount(3);

        $this->assertDatabaseHas('withholding_tax_tables', [
            'description' => 'TRAIN Law 2025',
            'pay_period' => 'monthly',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('withholding_tax_brackets', [
            'min_compensation' => 0,
            'max_compensation' => 20833,
            'base_tax' => 0,
            'excess_rate' => 0,
        ]);
    });

    it('shows single withholding tax table with brackets', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $hrManager = createTenantUserForTax($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create([
            'effective_from' => '2025-01-01',
            'description' => 'Test Tax Table',
        ]);

        $controller = new WithholdingTaxController;
        $response = $controller->show($table);

        expect($response->description)->toBe('Test Tax Table');
        expect($response->brackets)->not->toBeEmpty();
    });

    it('updates withholding tax table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $hrManager = createTenantUserForTax($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create([
            'description' => 'Original Description',
        ]);

        $controller = new WithholdingTaxController;

        $updateData = [
            'pay_period' => $table->pay_period,
            'effective_from' => $table->effective_from->format('Y-m-d'),
            'description' => 'Updated Description',
            'is_active' => true,
            'brackets' => $table->brackets->map(fn ($b) => [
                'min_compensation' => $b->min_compensation,
                'max_compensation' => $b->max_compensation,
                'base_tax' => $b->base_tax,
                'excess_rate' => $b->excess_rate,
            ])->toArray(),
        ];

        $request = StoreWithholdingTaxTableRequest::create(
            "/api/organization/contributions/tax/{$table->id}",
            'PUT',
            $updateData
        );
        $request->setUserResolver(fn () => $hrManager);
        $request->setContainer(app());

        $validator = Validator::make($updateData, (new StoreWithholdingTaxTableRequest)->rules());

        $reflection = new ReflectionClass($request);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($request, $validator);

        $response = $controller->update($request, $table);

        expect($response->description)->toBe('Updated Description');

        $this->assertDatabaseHas('withholding_tax_tables', [
            'id' => $table->id,
            'description' => 'Updated Description',
        ]);
    });

    it('soft deletes withholding tax table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $hrManager = createTenantUserForTax($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $table = WithholdingTaxTable::factory()->monthly()->create();

        $controller = new WithholdingTaxController;
        $response = $controller->destroy($table);

        expect($response->getStatusCode())->toBe(200);

        $this->assertSoftDeleted('withholding_tax_tables', [
            'id' => $table->id,
        ]);
    });

    it('validates required fields when creating tax table', function () {
        $rules = (new StoreWithholdingTaxTableRequest)->rules();

        // Test missing required fields
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('pay_period'))->toBeTrue();
        expect($validator->errors()->has('effective_from'))->toBeTrue();
        expect($validator->errors()->has('brackets'))->toBeTrue();

        // Test valid data passes
        $validData = [
            'pay_period' => 'monthly',
            'effective_from' => '2025-01-01',
            'is_active' => true,
            'brackets' => [
                [
                    'min_compensation' => 0,
                    'max_compensation' => 20833,
                    'base_tax' => 0,
                    'excess_rate' => 0,
                ],
            ],
        ];

        $validValidator = Validator::make($validData, $rules);
        expect($validValidator->fails())->toBeFalse();
    });

    it('validates pay period values', function () {
        $rules = (new StoreWithholdingTaxTableRequest)->rules();

        $invalidData = [
            'pay_period' => 'invalid_period',
            'effective_from' => '2025-01-01',
            'brackets' => [
                [
                    'min_compensation' => 0,
                    'max_compensation' => 20833,
                    'base_tax' => 0,
                    'excess_rate' => 0,
                ],
            ],
        ];

        $validator = Validator::make($invalidData, $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('pay_period'))->toBeTrue();
    });

    it('prevents unauthorized user from creating tax table', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $employee = createTenantUserForTax($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $controller = new WithholdingTaxController;

        $tableData = [
            'pay_period' => 'monthly',
            'effective_from' => '2025-01-01',
            'is_active' => true,
            'brackets' => [
                [
                    'min_compensation' => 0,
                    'max_compensation' => 20833,
                    'base_tax' => 0,
                    'excess_rate' => 0,
                ],
            ],
        ];

        $request = createStoreTaxRequest($tableData, $employee);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->store($request);
    });
});

describe('Withholding Tax Table Model', function () {
    it('finds current active table for pay period', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        // Create older table
        WithholdingTaxTable::factory()->monthly()->create([
            'effective_from' => '2024-01-01',
            'description' => 'Old Table',
            'is_active' => true,
        ]);

        // Create current table
        $currentTable = WithholdingTaxTable::factory()->monthly()->create([
            'effective_from' => now()->subMonth(),
            'description' => 'Current Table',
            'is_active' => true,
        ]);

        // Create future table
        WithholdingTaxTable::factory()->monthly()->create([
            'effective_from' => now()->addMonth(),
            'description' => 'Future Table',
            'is_active' => true,
        ]);

        $current = WithholdingTaxTable::current('monthly');

        expect($current)->not->toBeNull();
        expect($current->id)->toBe($currentTable->id);
        expect($current->description)->toBe('Current Table');
    });

    it('finds table effective at specific date for pay period', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        WithholdingTaxTable::factory()->monthly()->create([
            'effective_from' => '2024-01-01',
            'description' => '2024 Table',
            'is_active' => true,
        ]);

        $table2025 = WithholdingTaxTable::factory()->monthly()->create([
            'effective_from' => '2025-01-01',
            'description' => '2025 Table',
            'is_active' => true,
        ]);

        $effectiveTable = WithholdingTaxTable::effectiveAt(\Carbon\Carbon::parse('2025-06-15'), 'monthly');

        expect($effectiveTable)->not->toBeNull();
        expect($effectiveTable->id)->toBe($table2025->id);
    });

    it('separates tables by pay period', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $monthlyTable = WithholdingTaxTable::factory()->monthly()->create([
            'effective_from' => now()->subDay(),
            'description' => 'Monthly Table',
            'is_active' => true,
        ]);

        $weeklyTable = WithholdingTaxTable::factory()->weekly()->create([
            'effective_from' => now()->subDay(),
            'description' => 'Weekly Table',
            'is_active' => true,
        ]);

        $currentMonthly = WithholdingTaxTable::current('monthly');
        $currentWeekly = WithholdingTaxTable::current('weekly');

        expect($currentMonthly->id)->toBe($monthlyTable->id);
        expect($currentWeekly->id)->toBe($weeklyTable->id);
    });

    it('finds correct bracket for compensation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create();

        // Test compensation at ₱50,000 (should be 20% bracket)
        $bracket = $table->findBracketForCompensation(50000);

        expect($bracket)->not->toBeNull();
        expect((float) $bracket->excess_rate)->toEqual(0.20);
    });

    it('finds bracket for compensation at boundary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create();

        // Test compensation exactly at bracket boundary
        // At 33333, the 15% bracket (max_compensation = 33333) still applies
        $bracket = $table->findBracketForCompensation(33333);

        expect($bracket)->not->toBeNull();
        expect((float) $bracket->excess_rate)->toEqual(0.15);

        // Just above the boundary enters the 20% bracket
        $bracketAbove = $table->findBracketForCompensation(33334);
        expect((float) $bracketAbove->excess_rate)->toEqual(0.20);
    });

    it('finds tax-exempt bracket for low compensation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create();

        // Test minimum compensation (tax exempt)
        $bracket = $table->findBracketForCompensation(15000);

        expect($bracket)->not->toBeNull();
        expect((float) $bracket->excess_rate)->toEqual(0);
        expect((float) $bracket->base_tax)->toEqual(0);
    });

    it('finds highest bracket for maximum compensation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create();

        // Test very high compensation
        $bracket = $table->findBracketForCompensation(1000000);

        expect($bracket)->not->toBeNull();
        expect((float) $bracket->excess_rate)->toEqual(0.35);
    });

    it('excludes inactive tables from current lookup', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        WithholdingTaxTable::factory()->monthly()->inactive()->create([
            'effective_from' => now()->subDay(),
            'description' => 'Inactive Table',
        ]);

        $current = WithholdingTaxTable::current('monthly');

        expect($current)->toBeNull();
    });
});

describe('Withholding Tax Calculation', function () {
    it('calculates tax correctly for exempt bracket', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create();

        $tax = $table->calculateTax(15000);

        expect($tax)->toEqual(0);
    });

    it('calculates tax correctly for 15% bracket', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create();

        // For monthly ₱25,000: excess over ₱20,833 = ₱4,167, tax = ₱4,167 * 0.15 = ₱625.05
        $tax = $table->calculateTax(25000);

        expect($tax)->toBeGreaterThan(0);
        expect($tax)->toBeLessThan(1000);
    });

    it('calculates tax correctly for highest bracket', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $table = WithholdingTaxTable::factory()->monthly()->withBrackets()->create();

        // For monthly ₱1,000,000: base tax ₱183,541.67 + 35% of excess over ₱666,667
        $tax = $table->calculateTax(1000000);

        expect($tax)->toBeGreaterThan(183541.67);
    });

    it('calculates tax correctly using bracket model', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForTax($tenant);

        $table = WithholdingTaxTable::factory()->monthly()->create();

        $bracket = WithholdingTaxBracket::factory()->forTable($table)->create([
            'min_compensation' => 33333,
            'max_compensation' => 66667,
            'base_tax' => 1875,
            'excess_rate' => 0.20,
        ]);

        // For ₱50,000: base ₱1,875 + 20% of (₱50,000 - ₱33,333) = ₱1,875 + ₱3,333.40 = ₱5,208.40
        $tax = $bracket->calculateTax(50000);

        expect($tax)->toBeGreaterThan(5200);
        expect($tax)->toBeLessThan(5300);
    });
});
