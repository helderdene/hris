<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\ContributionCalculatorController;
use App\Http\Requests\CalculateContributionRequest;
use App\Models\PagibigContributionTable;
use App\Models\PhilhealthContributionTable;
use App\Models\SssContributionTable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContributionCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForCalculator(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForCalculator(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated calculate request.
 */
function createCalculateRequest(array $data, User $user): CalculateContributionRequest
{
    $request = CalculateContributionRequest::create('/api/organization/contributions/calculate', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new CalculateContributionRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create all contribution tables for testing.
 */
function createAllContributionTables(): void
{
    SssContributionTable::factory()->year2025()->withBrackets()->create();
    PhilhealthContributionTable::factory()->year2025()->create();
    PagibigContributionTable::factory()->year2025()->withTiers()->create();
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('ContributionCalculatorService', function () {
    it('calculates all contributions for a given salary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        createAllContributionTables();

        $service = new ContributionCalculatorService;
        $result = $service->calculateAll(25000);

        // Verify SSS calculation
        expect($result['sss']['employee_share'])->toBe(1125.00);
        expect($result['sss']['employer_share'])->toBe(2375.00);
        expect($result['sss']['monthly_salary_credit'])->toBe(25000.00);
        expect($result['sss']['error'])->toBeNull();

        // Verify PhilHealth calculation (5% of ₱25,000 = ₱1,250 total, split 50/50)
        expect($result['philhealth']['employee_share'])->toBe(625.00);
        expect($result['philhealth']['employer_share'])->toBe(625.00);
        expect($result['philhealth']['error'])->toBeNull();

        // Verify Pag-IBIG calculation (₱25,000 capped at ₱5,000, 2%/2%)
        expect($result['pagibig']['employee_share'])->toBe(100.00);
        expect($result['pagibig']['employer_share'])->toBe(100.00);
        expect($result['pagibig']['error'])->toBeNull();

        // Verify totals
        $expectedEmployeeTotal = 1125.00 + 625.00 + 100.00;
        $expectedEmployerTotal = 2375.00 + 625.00 + 100.00;
        expect($result['totals']['employee_share'])->toBe($expectedEmployeeTotal);
        expect($result['totals']['employer_share'])->toBe($expectedEmployerTotal);
        expect($result['totals']['total'])->toBe($expectedEmployeeTotal + $expectedEmployerTotal);
    });

    it('calculates SSS contribution correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        SssContributionTable::factory()->year2025()->withBrackets()->create();

        $service = new ContributionCalculatorService;

        // Test various salary levels
        $testCases = [
            // [salary, expected_employee, expected_employer, expected_msc]
            [4000, 180.00, 380.00, 4000.00], // Below minimum MSC
            [10000, 450.00, 950.00, 10000.00], // Mid-range
            [25000, 1125.00, 2375.00, 25000.00], // Common salary
            [50000, 1350.00, 2850.00, 30000.00], // Above max MSC
        ];

        foreach ($testCases as $case) {
            [$salary, $expectedEmployee, $expectedEmployer, $expectedMsc] = $case;
            $result = $service->calculateSss($salary);

            expect($result['employee_share'])->toBe($expectedEmployee, "Employee share for salary {$salary}");
            expect($result['employer_share'])->toBe($expectedEmployer, "Employer share for salary {$salary}");
            expect($result['monthly_salary_credit'])->toBe($expectedMsc, "MSC for salary {$salary}");
        }
    });

    it('calculates PhilHealth contribution correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        PhilhealthContributionTable::factory()->year2025()->create();

        $service = new ContributionCalculatorService;

        // Test various salary levels
        $testCases = [
            // [salary, expected_total, expected_basis]
            [5000, 500.00, 10000.00], // Below floor - uses floor
            [25000, 1250.00, 25000.00], // Within range
            [50000, 2500.00, 50000.00], // Within range
            [150000, 5000.00, 100000.00], // Above ceiling - uses ceiling
        ];

        foreach ($testCases as $case) {
            [$salary, $expectedTotal, $expectedBasis] = $case;
            $result = $service->calculatePhilHealth($salary);

            expect((float) $result['total'])->toEqual($expectedTotal, "Total for salary {$salary}");
            expect((float) $result['basis_salary'])->toEqual($expectedBasis, "Basis for salary {$salary}");
            expect((float) $result['employee_share'])->toEqual($expectedTotal / 2, "Employee share for salary {$salary}");
            expect((float) $result['employer_share'])->toEqual($expectedTotal / 2, "Employer share for salary {$salary}");
        }
    });

    it('calculates Pag-IBIG contribution correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        PagibigContributionTable::factory()->year2025()->withTiers()->create();

        $service = new ContributionCalculatorService;

        // Test various salary levels
        $testCases = [
            // [salary, expected_employee, expected_employer, expected_basis]
            [1000, 10.00, 20.00, 1000.00], // Lower tier (1%/2%)
            [1500, 15.00, 30.00, 1500.00], // Boundary (lower tier)
            [3000, 60.00, 60.00, 3000.00], // Higher tier (2%/2%)
            [10000, 100.00, 100.00, 5000.00], // Above cap, higher tier
        ];

        foreach ($testCases as $case) {
            [$salary, $expectedEmployee, $expectedEmployer, $expectedBasis] = $case;
            $result = $service->calculatePagibig($salary);

            expect((float) $result['employee_share'])->toEqual($expectedEmployee, "Employee share for salary {$salary}");
            expect((float) $result['employer_share'])->toEqual($expectedEmployer, "Employer share for salary {$salary}");
            expect((float) $result['basis_salary'])->toEqual($expectedBasis, "Basis for salary {$salary}");
        }
    });

    it('returns error when no contribution tables exist', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        $service = new ContributionCalculatorService;
        $result = $service->calculateAll(25000);

        expect($result['sss']['error'])->not->toBeNull();
        expect($result['philhealth']['error'])->not->toBeNull();
        expect($result['pagibig']['error'])->not->toBeNull();

        // Totals should be zero
        expect((float) $result['totals']['employee_share'])->toEqual(0.0);
        expect((float) $result['totals']['employer_share'])->toEqual(0.0);
        expect((float) $result['totals']['total'])->toEqual(0.0);
    });

    it('uses effective date for historical calculations', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        // Create 2024 table
        SssContributionTable::factory()->withBrackets()->create([
            'effective_from' => '2024-01-01',
            'description' => '2024 Table',
        ]);

        // Create 2025 table with different rates (we'll modify the bracket)
        $table2025 = SssContributionTable::factory()->withBrackets()->create([
            'effective_from' => '2025-01-01',
            'description' => '2025 Table',
        ]);

        $service = new ContributionCalculatorService;

        // Query for 2024 date - should use 2024 table
        $result2024 = $service->calculateSss(25000, \Carbon\Carbon::parse('2024-06-15'));
        expect($result2024['error'])->toBeNull();

        // Query for 2025 date - should use 2025 table
        $result2025 = $service->calculateSss(25000, \Carbon\Carbon::parse('2025-06-15'));
        expect($result2025['table_id'])->toBe($table2025->id);
    });

    it('checks if all tables are configured', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        $service = new ContributionCalculatorService;

        // Initially no tables
        expect($service->hasAllTables())->toBeFalse();

        // Add only SSS
        SssContributionTable::factory()->year2025()->withBrackets()->create();
        expect($service->hasAllTables())->toBeFalse();

        // Add PhilHealth
        PhilhealthContributionTable::factory()->year2025()->create();
        expect($service->hasAllTables())->toBeFalse();

        // Add Pag-IBIG - now all tables exist
        PagibigContributionTable::factory()->year2025()->withTiers()->create();
        expect($service->hasAllTables())->toBeTrue();
    });

    it('gets active tables', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        createAllContributionTables();

        $service = new ContributionCalculatorService;
        $tables = $service->getActiveTables();

        expect($tables['sss'])->not->toBeNull();
        expect($tables['philhealth'])->not->toBeNull();
        expect($tables['pagibig'])->not->toBeNull();
    });
});

describe('ContributionCalculatorController', function () {
    it('calculates contributions via API', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        $hrManager = createTenantUserForCalculator($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        createAllContributionTables();

        $controller = app(ContributionCalculatorController::class);

        $requestData = [
            'salary' => 25000,
            'effective_date' => now()->format('Y-m-d'),
        ];

        $request = createCalculateRequest($requestData, $hrManager);
        $response = $controller->calculate($request);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);

        expect($data['data']['salary'])->toBe(25000);
        expect($data['data']['contributions']['sss'])->not->toBeNull();
        expect($data['data']['contributions']['philhealth'])->not->toBeNull();
        expect($data['data']['contributions']['pagibig'])->not->toBeNull();
        expect($data['data']['contributions']['totals'])->not->toBeNull();
    });

    it('validates required salary field', function () {
        $rules = (new CalculateContributionRequest)->rules();

        // Test missing salary
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('salary'))->toBeTrue();

        // Test invalid salary
        $invalidValidator = Validator::make(['salary' => 'not-a-number'], $rules);
        expect($invalidValidator->fails())->toBeTrue();

        // Test negative salary
        $negativeValidator = Validator::make(['salary' => -100], $rules);
        expect($negativeValidator->fails())->toBeTrue();

        // Test valid data
        $validValidator = Validator::make(['salary' => 25000], $rules);
        expect($validValidator->fails())->toBeFalse();
    });

    it('returns table status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        $hrManager = createTenantUserForCalculator($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Only create SSS table
        SssContributionTable::factory()->year2025()->withBrackets()->create();

        $controller = app(ContributionCalculatorController::class);
        $response = $controller->status();

        $data = json_decode($response->getContent(), true);

        expect($data['data']['has_all_tables'])->toBeFalse();
        expect($data['data']['sss_configured'])->toBeTrue();
        expect($data['data']['philhealth_configured'])->toBeFalse();
        expect($data['data']['pagibig_configured'])->toBeFalse();
    });
});

describe('Contribution Calculation Accuracy', function () {
    it('matches official 2025 SSS contribution for ₱10,000 salary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        SssContributionTable::factory()->year2025()->withBrackets()->create();

        $service = new ContributionCalculatorService;
        $result = $service->calculateSss(10000);

        // Official 2025 SSS rates for ₱10,000 salary (MSC ₱10,000)
        // Employee: ₱450, Employer: ₱950, Total: ₱1,400
        expect($result['monthly_salary_credit'])->toBe(10000.00);
        expect($result['employee_share'])->toBe(450.00);
        expect($result['employer_share'])->toBe(950.00);
        expect($result['total'])->toBe(1400.00);
        expect($result['ec_contribution'])->toBe(10.00);
    });

    it('matches official 2025 SSS contribution for ₱30,000 salary (max MSC)', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        SssContributionTable::factory()->year2025()->withBrackets()->create();

        $service = new ContributionCalculatorService;
        $result = $service->calculateSss(30000);

        // Official 2025 SSS rates for ₱30,000+ salary (MSC ₱30,000)
        // Employee: ₱1,350, Employer: ₱2,850, Total: ₱4,200
        expect($result['monthly_salary_credit'])->toBe(30000.00);
        expect($result['employee_share'])->toBe(1350.00);
        expect($result['employer_share'])->toBe(2850.00);
        expect($result['total'])->toBe(4200.00);
        expect($result['ec_contribution'])->toBe(30.00);
    });

    it('matches official 2025 PhilHealth contribution for ₱50,000 salary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        PhilhealthContributionTable::factory()->year2025()->create();

        $service = new ContributionCalculatorService;
        $result = $service->calculatePhilHealth(50000);

        // Official 2025 PhilHealth: 5% rate, 50/50 split
        // ₱50,000 * 5% = ₱2,500 total
        // Employee: ₱1,250, Employer: ₱1,250
        expect($result['total'])->toBe(2500.00);
        expect($result['employee_share'])->toBe(1250.00);
        expect($result['employer_share'])->toBe(1250.00);
    });

    it('matches official 2025 Pag-IBIG contribution for ₱10,000 salary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        PagibigContributionTable::factory()->year2025()->withTiers()->create();

        $service = new ContributionCalculatorService;
        $result = $service->calculatePagibig(10000);

        // Official 2025 Pag-IBIG: For salary > ₱1,500, 2%/2%
        // Capped at ₱5,000 max compensation
        // ₱5,000 * 2% = ₱100 employee, ₱100 employer
        expect((float) $result['basis_salary'])->toEqual(5000.00);
        expect((float) $result['employee_share'])->toEqual(100.00);
        expect((float) $result['employer_share'])->toEqual(100.00);
        expect((float) $result['total'])->toEqual(200.00);
    });

    it('calculates correct total deductions for typical employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCalculator($tenant);

        createAllContributionTables();

        $service = new ContributionCalculatorService;
        $result = $service->calculateAll(25000);

        // Expected breakdown for ₱25,000 salary:
        // SSS Employee: ₱1,125
        // PhilHealth Employee: ₱625
        // Pag-IBIG Employee: ₱100
        // Total Employee Deduction: ₱1,850

        $totalEmployeeDeduction = $result['totals']['employee_share'];
        expect($totalEmployeeDeduction)->toBe(1850.00);

        // SSS Employer: ₱2,375
        // PhilHealth Employer: ₱625
        // Pag-IBIG Employer: ₱100
        // Total Employer Contribution: ₱3,100

        $totalEmployerContribution = $result['totals']['employer_share'];
        expect($totalEmployerContribution)->toBe(3100.00);
    });
});
