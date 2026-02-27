<?php

use App\Enums\EmploymentStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Http\Requests\SeparateEmployeeRequest;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindSeparationTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createSeparationTenantUser(Tenant $tenant, TenantUserRole $role): User
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
 * Helper to create a validated SeparateEmployeeRequest.
 */
function createSeparateRequest(array $data): SeparateEmployeeRequest
{
    $request = SeparateEmployeeRequest::create('/employees/1/separate', 'POST', $data);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $rules = (new SeparateEmployeeRequest)->rules();
    $validator = Validator::make($data, $rules);

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

describe('Employee Separation', function () {
    it('successfully separates an active employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindSeparationTenantContext($tenant);

        $admin = createSeparationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $request = createSeparateRequest([
            'employment_status' => 'resigned',
            'termination_date' => '2026-02-27',
            'remarks' => 'Voluntary resignation',
        ]);

        $controller = new EmployeeController;
        $response = $controller->separate($request, $employee);

        expect($response->getStatusCode())->toBe(302);

        $employee->refresh();
        expect($employee->employment_status)->toBe(EmploymentStatus::Resigned);
        expect($employee->termination_date->toDateString())->toBe('2026-02-27');
    });

    it('separates with each valid employment status', function (string $status, EmploymentStatus $expectedEnum) {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindSeparationTenantContext($tenant);

        $admin = createSeparationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $request = createSeparateRequest([
            'employment_status' => $status,
            'termination_date' => '2026-02-27',
        ]);

        $controller = new EmployeeController;
        $controller->separate($request, $employee);

        $employee->refresh();
        expect($employee->employment_status)->toBe($expectedEnum);
    })->with([
        'resigned' => ['resigned', EmploymentStatus::Resigned],
        'terminated' => ['terminated', EmploymentStatus::Terminated],
        'retired' => ['retired', EmploymentStatus::Retired],
        'end_of_contract' => ['end_of_contract', EmploymentStatus::EndOfContract],
        'deceased' => ['deceased', EmploymentStatus::Deceased],
    ]);

    it('denies separation for unauthorized users', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindSeparationTenantContext($tenant);

        $viewer = createSeparationTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($viewer);

        $employee = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
        ]);

        $request = createSeparateRequest([
            'employment_status' => 'resigned',
            'termination_date' => '2026-02-27',
        ]);

        $controller = new EmployeeController;

        expect(fn () => $controller->separate($request, $employee))
            ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    });
});

describe('SeparateEmployeeRequest validation', function () {
    it('requires employment_status', function () {
        $rules = (new SeparateEmployeeRequest)->rules();
        $validator = Validator::make(
            ['termination_date' => '2026-02-27'],
            $rules
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('employment_status'))->toBeTrue();
    });

    it('requires termination_date', function () {
        $rules = (new SeparateEmployeeRequest)->rules();
        $validator = Validator::make(
            ['employment_status' => 'resigned'],
            $rules
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('termination_date'))->toBeTrue();
    });

    it('rejects active as employment status', function () {
        $rules = (new SeparateEmployeeRequest)->rules();
        $validator = Validator::make(
            ['employment_status' => 'active', 'termination_date' => '2026-02-27'],
            $rules
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('employment_status'))->toBeTrue();
    });

    it('rejects invalid employment status', function () {
        $rules = (new SeparateEmployeeRequest)->rules();
        $validator = Validator::make(
            ['employment_status' => 'invalid_status', 'termination_date' => '2026-02-27'],
            $rules
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('employment_status'))->toBeTrue();
    });

    it('allows remarks to be nullable', function () {
        $rules = (new SeparateEmployeeRequest)->rules();
        $validator = Validator::make(
            ['employment_status' => 'resigned', 'termination_date' => '2026-02-27'],
            $rules
        );

        expect($validator->fails())->toBeFalse();
    });

    it('rejects remarks exceeding 500 characters', function () {
        $rules = (new SeparateEmployeeRequest)->rules();
        $validator = Validator::make(
            [
                'employment_status' => 'resigned',
                'termination_date' => '2026-02-27',
                'remarks' => str_repeat('a', 501),
            ],
            $rules
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('remarks'))->toBeTrue();
    });
});
