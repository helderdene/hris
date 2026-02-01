<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\PayslipPageController;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payroll\PayslipPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForSelfServicePayslip(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with an employee role in a specific tenant.
 */
function createEmployeeUserForSelfServicePayslip(Tenant $tenant, array $userAttributes = []): array
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $employee = Employee::factory()->create([
        'user_id' => $user->id,
    ]);

    return [$user, $employee];
}

/**
 * Helper to extract Inertia response data.
 */
function getInertiaResponseDataForPayslip(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

/**
 * Helper to get the Inertia component name.
 */
function getInertiaComponentForPayslip(\Inertia\Response $response): string
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('component');
    $property->setAccessible(true);

    return $property->getValue($response);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Self-Service Payslip Index', function () {
    it('displays payslip list for an employee with only approved and paid entries', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForSelfServicePayslip($tenant);

        [$user, $employee] = createEmployeeUserForSelfServicePayslip($tenant);

        PayrollEntry::factory()->draft()->forEmployee($employee)->create();
        PayrollEntry::factory()->computed()->forEmployee($employee)->create();
        PayrollEntry::factory()->reviewed()->forEmployee($employee)->create();
        PayrollEntry::factory()->approved()->forEmployee($employee)->create();
        PayrollEntry::factory()->paid()->forEmployee($employee)->create();

        $this->actingAs($user);

        $controller = app(PayslipPageController::class);
        $request = Request::create('/my/payslips', 'GET');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        $response = $controller->index($request);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getInertiaComponentForPayslip($response))->toBe('My/Payslips/Index');

        $data = getInertiaResponseDataForPayslip($response);
        expect($data['hasEmployeeProfile'])->toBeTrue();
        expect($data['payslips']->total())->toBe(2);
    });

    it('shows no employee profile when user has no linked employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForSelfServicePayslip($tenant);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->actingAs($user);

        $controller = app(PayslipPageController::class);
        $request = Request::create('/my/payslips', 'GET');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        $response = $controller->index($request);

        $data = getInertiaResponseDataForPayslip($response);
        expect($data['hasEmployeeProfile'])->toBeFalse();
        expect($data['payslips'])->toBeNull();
    });
});

describe('Self-Service Payslip Show', function () {
    it('displays an individual payslip detail', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForSelfServicePayslip($tenant);

        [$user, $employee] = createEmployeeUserForSelfServicePayslip($tenant);

        $entry = PayrollEntry::factory()->approved()->forEmployee($employee)->create();

        $this->actingAs($user);

        $controller = app(PayslipPageController::class);
        $request = Request::create("/my/payslips/{$entry->id}", 'GET');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        $response = $controller->show($request, $tenant->slug, $entry);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getInertiaComponentForPayslip($response))->toBe('My/Payslips/Show');

        $data = getInertiaResponseDataForPayslip($response);
        expect($data['entry']['id'])->toBe($entry->id);
    });

    it('forbids viewing another employee payslip', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForSelfServicePayslip($tenant);

        [$user, $employee] = createEmployeeUserForSelfServicePayslip($tenant);

        $otherEmployee = Employee::factory()->create();
        $entry = PayrollEntry::factory()->approved()->forEmployee($otherEmployee)->create();

        $this->actingAs($user);

        $controller = app(PayslipPageController::class);
        $request = Request::create("/my/payslips/{$entry->id}", 'GET');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        $controller->show($request, $tenant->slug, $entry);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    it('forbids viewing a draft payslip', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForSelfServicePayslip($tenant);

        [$user, $employee] = createEmployeeUserForSelfServicePayslip($tenant);

        $entry = PayrollEntry::factory()->draft()->forEmployee($employee)->create();

        $this->actingAs($user);

        $controller = app(PayslipPageController::class);
        $request = Request::create("/my/payslips/{$entry->id}", 'GET');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        $controller->show($request, $tenant->slug, $entry);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

describe('Self-Service Payslip PDF Download', function () {
    it('can download a payslip pdf', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForSelfServicePayslip($tenant);

        [$user, $employee] = createEmployeeUserForSelfServicePayslip($tenant);

        $entry = PayrollEntry::factory()->approved()->forEmployee($employee)->create();

        $this->actingAs($user);

        $mockService = Mockery::mock(PayslipPdfService::class);
        $mockService->shouldReceive('generateSingle')
            ->once()
            ->andReturn('%PDF-1.4 fake content');
        app()->instance(PayslipPdfService::class, $mockService);

        $controller = app(PayslipPageController::class);
        $request = Request::create("/my/payslips/{$entry->id}/pdf", 'GET');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        $response = $controller->downloadPdf($request, $tenant->slug, $entry);

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    });

    it('forbids downloading another employee payslip pdf', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForSelfServicePayslip($tenant);

        [$user, $employee] = createEmployeeUserForSelfServicePayslip($tenant);

        $otherEmployee = Employee::factory()->create();
        $entry = PayrollEntry::factory()->approved()->forEmployee($otherEmployee)->create();

        $this->actingAs($user);

        $controller = app(PayslipPageController::class);
        $request = Request::create("/my/payslips/{$entry->id}/pdf", 'GET');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        $controller->downloadPdf($request, $tenant->slug, $entry);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
