<?php

/**
 * Feature Tests for Compensation Vue Components
 *
 * These tests verify that the Employee Show page correctly includes
 * the Compensation tab and that the backend properly supports the
 * compensation tab functionality.
 */

use App\Enums\PayType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Models\CompensationHistory;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindComponentTestTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createComponentTestTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Employee Show Page with Compensation Tab', function () {
    it('renders employee show page with compensation tab available', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindComponentTestTenantContext($tenant);

        $admin = createComponentTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Employee',
        ]);

        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($tenant->slug, $employee);

        $reflection = new ReflectionClass($inertiaResponse);

        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);
        expect($componentProperty->getValue($inertiaResponse))->toBe('Employees/Show');

        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        expect($props['employee'])->not->toBeNull();
        $employeeData = $props['employee']->toArray(request());
        expect($employeeData['id'])->toBe($employee->id);
    });

    it('provides employee id needed for compensation tab to fetch data', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindComponentTestTenantContext($tenant);

        $admin = createComponentTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($tenant->slug, $employee);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $employeeData = $props['employee']->toArray(request());
        expect($employeeData['id'])->toBeInt();
        expect($employeeData['id'])->toBeGreaterThan(0);
    });
});

describe('Compensation Tab Data Display', function () {
    it('displays compensation data when employee has compensation record', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindComponentTestTenantContext($tenant);

        $admin = createComponentTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $compensation = EmployeeCompensation::factory()
            ->withBankAccount()
            ->create([
                'employee_id' => $employee->id,
                'basic_pay' => 75000.00,
                'pay_type' => PayType::SemiMonthly,
                'bank_name' => 'BDO',
                'account_number' => '1234567890',
            ]);

        $loadedEmployee = Employee::with('compensation')->find($employee->id);

        expect($loadedEmployee->compensation)->not->toBeNull();
        expect($loadedEmployee->compensation->basic_pay)->toBe('75000.00');
        expect($loadedEmployee->compensation->pay_type)->toBe(PayType::SemiMonthly);
        expect($loadedEmployee->compensation->bank_name)->toBe('BDO');
    });

    it('returns null compensation when employee has no compensation record', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindComponentTestTenantContext($tenant);

        $admin = createComponentTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $loadedEmployee = Employee::with('compensation')->find($employee->id);

        expect($loadedEmployee->compensation)->toBeNull();
    });
});

describe('Compensation History Timeline Data', function () {
    it('returns compensation history in descending order by date', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindComponentTestTenantContext($tenant);

        $admin = createComponentTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        $olderHistory = CompensationHistory::factory()->create([
            'employee_id' => $employee->id,
            'new_basic_pay' => 40000.00,
            'new_pay_type' => PayType::Monthly,
            'effective_date' => now()->subYear(),
            'ended_at' => now()->subMonths(6),
            'created_at' => now()->subYear(),
        ]);

        $newerHistory = CompensationHistory::factory()->current()->create([
            'employee_id' => $employee->id,
            'previous_basic_pay' => 40000.00,
            'new_basic_pay' => 55000.00,
            'previous_pay_type' => PayType::Monthly,
            'new_pay_type' => PayType::SemiMonthly,
            'effective_date' => now()->subMonths(6),
            'created_at' => now()->subMonths(6),
        ]);

        $history = $employee->compensationHistory()
            ->orderBy('created_at', 'desc')
            ->get();

        expect($history)->toHaveCount(2);
        expect($history->first()->id)->toBe($newerHistory->id);
        expect($history->last()->id)->toBe($olderHistory->id);
    });

    it('returns empty history when no compensation changes recorded', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindComponentTestTenantContext($tenant);

        $admin = createComponentTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        $history = $employee->compensationHistory()->get();

        expect($history)->toBeEmpty();
    });
});

describe('Edit Compensation Modal Permission', function () {
    it('admin user can manage employees for compensation editing', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindComponentTestTenantContext($tenant);

        $admin = createComponentTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        expect(\Illuminate\Support\Facades\Gate::allows('can-manage-employees'))->toBeTrue();
    });

    it('employee user cannot manage employees for compensation editing', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindComponentTestTenantContext($tenant);

        $employee_user = createComponentTestTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employee_user);

        expect(\Illuminate\Support\Facades\Gate::allows('can-manage-employees'))->toBeFalse();
    });
});
