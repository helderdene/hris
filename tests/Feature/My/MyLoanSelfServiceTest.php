<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\MyLoanController;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForLoans(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserWithRoleForLoans(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('renders My/Loans/Index component', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLoans($tenant);

    $user = createUserWithRoleForLoans($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLoanController;
    $request = Request::create('/my/loans', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);

    expect($componentProperty->getValue($response))->toBe('My/Loans/Index');
});

it('returns employee loans', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLoans($tenant);

    $user = createUserWithRoleForLoans($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    EmployeeLoan::factory()->count(3)->forEmployee($employee)->create();

    $controller = new MyLoanController;
    $request = Request::create('/my/loans', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['loans'])->toHaveCount(3)
        ->and($props['employee'])->not->toBeNull()
        ->and($props['employee']['id'])->toBe($employee->id);
});

it('returns empty loans when no employee profile', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLoans($tenant);

    $user = createUserWithRoleForLoans($tenant, TenantUserRole::Employee);
    $this->actingAs($user);

    $controller = new MyLoanController;
    $request = Request::create('/my/loans', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['employee'])->toBeNull()
        ->and($props['loans'])->toBeEmpty();
});

it('returns loan detail with payments on show', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLoans($tenant);

    $user = createUserWithRoleForLoans($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $loan = EmployeeLoan::factory()->forEmployee($employee)->create();

    $controller = new MyLoanController;
    $request = Request::create("/my/loans/{$loan->id}", 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->show($request, $loan);

    $reflection = new ReflectionClass($response);
    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($componentProperty->getValue($response))->toBe('My/Loans/Show')
        ->and($props['loan']['id'])->toBe($loan->id)
        ->and($props['loan'])->toHaveKeys([
            'id', 'loan_type', 'loan_type_label', 'loan_code',
            'principal_amount', 'total_amount', 'remaining_balance',
            'status', 'status_label', 'payments',
        ]);
});

it('returns 403 when employee views another employee loan', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForLoans($tenant);

    $user = createUserWithRoleForLoans($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $otherEmployee = Employee::factory()->create();
    $otherLoan = EmployeeLoan::factory()->forEmployee($otherEmployee)->create();

    $controller = new MyLoanController;
    $request = Request::create("/my/loans/{$otherLoan->id}", 'GET');
    $request->setUserResolver(fn () => $user);

    $controller->show($request, $otherLoan);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
