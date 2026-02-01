<?php

use App\Enums\EmploymentStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\My\MyLoanApplicationController;
use App\Models\Employee;
use App\Models\LoanApplication;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantForMyLoanApp(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createEmployeeUserForMyLoanApp(Tenant $tenant): array
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'employment_status' => EmploymentStatus::Active,
    ]);

    return [$user, $employee];
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('My Loan Applications Pages', function () {
    it('renders the index page with applications', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantForMyLoanApp($tenant);

        [$user, $employee] = createEmployeeUserForMyLoanApp($tenant);
        $this->actingAs($user);

        LoanApplication::factory()->count(2)->forEmployee($employee)->create();

        $controller = new MyLoanApplicationController;
        $request = Request::create('/my/loan-applications', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->index($request);

        $reflection = new ReflectionClass($response);
        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);

        expect($componentProperty->getValue($response))->toBe('My/LoanApplications/Index');
    });

    it('renders the create page', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantForMyLoanApp($tenant);

        [$user] = createEmployeeUserForMyLoanApp($tenant);
        $this->actingAs($user);

        $controller = new MyLoanApplicationController;
        $request = Request::create('/my/loan-applications/create', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->create($request);

        $reflection = new ReflectionClass($response);
        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);

        expect($componentProperty->getValue($response))->toBe('My/LoanApplications/Create');
    });

    it('renders the show page for own application', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantForMyLoanApp($tenant);

        [$user, $employee] = createEmployeeUserForMyLoanApp($tenant);
        $this->actingAs($user);

        $application = LoanApplication::factory()->forEmployee($employee)->create();

        $controller = new MyLoanApplicationController;
        $request = Request::create("/my/loan-applications/{$application->id}", 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->show($request, 'acme', $application);

        $reflection = new ReflectionClass($response);
        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);

        expect($componentProperty->getValue($response))->toBe('My/LoanApplications/Show');
    });

    it('forbids viewing another employees application', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantForMyLoanApp($tenant);

        [$user] = createEmployeeUserForMyLoanApp($tenant);
        $this->actingAs($user);

        $otherEmployee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $application = LoanApplication::factory()->forEmployee($otherEmployee)->create();

        $controller = new MyLoanApplicationController;
        $request = Request::create("/my/loan-applications/{$application->id}", 'GET');
        $request->setUserResolver(fn () => $user);

        expect(fn () => $controller->show($request, 'acme', $application))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('index returns only own applications', function () {
        $tenant = Tenant::factory()->create(['slug' => 'acme']);
        bindTenantForMyLoanApp($tenant);

        [$user, $employee] = createEmployeeUserForMyLoanApp($tenant);
        $this->actingAs($user);

        LoanApplication::factory()->count(3)->forEmployee($employee)->create();
        LoanApplication::factory()->count(2)->create(); // other employees

        $controller = new MyLoanApplicationController;
        $request = Request::create('/my/loan-applications', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->index($request);

        $reflection = new ReflectionClass($response);
        $props = $reflection->getProperty('props');
        $props->setAccessible(true);
        $propsValue = $props->getValue($response);

        $applications = $propsValue['applications'] instanceof \Closure
            ? ($propsValue['applications'])()
            : $propsValue['applications'];

        expect($applications)->toHaveCount(3);
    });
});
