<?php

use App\Enums\PayrollPeriodStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PayrollPeriodController;
use App\Http\Requests\GeneratePayrollPeriodsRequest;
use App\Http\Requests\StorePayrollPeriodRequest;
use App\Http\Requests\UpdatePayrollPeriodRequest;
use App\Http\Requests\UpdatePayrollPeriodStatusRequest;
use App\Models\PayrollCycle;
use App\Models\PayrollPeriod;
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
function bindTenantContextForPayrollPeriod(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPayrollPeriod(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store payroll period request.
 */
function createStorePayrollPeriodRequest(array $data, User $user): StorePayrollPeriodRequest
{
    $request = StorePayrollPeriodRequest::create('/api/organization/payroll-periods', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StorePayrollPeriodRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update payroll period request.
 */
function createUpdatePayrollPeriodRequest(array $data, User $user): UpdatePayrollPeriodRequest
{
    $request = UpdatePayrollPeriodRequest::create('/api/organization/payroll-periods/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdatePayrollPeriodRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated generate payroll periods request.
 */
function createGeneratePayrollPeriodsRequest(array $data, User $user): GeneratePayrollPeriodsRequest
{
    $request = GeneratePayrollPeriodsRequest::create('/api/organization/payroll-periods/generate', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new GeneratePayrollPeriodsRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update payroll period status request.
 */
function createUpdatePayrollPeriodStatusRequest(array $data, User $user): UpdatePayrollPeriodStatusRequest
{
    $request = UpdatePayrollPeriodStatusRequest::create('/api/organization/payroll-periods/1/status', 'PATCH', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdatePayrollPeriodStatusRequest)->rules());
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

describe('PayrollPeriod API', function () {
    describe('index', function () {
        it('returns payroll period list', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(1)->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(2)->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2025)->periodNumber(1)->create();

            $controller = app(PayrollPeriodController::class);
            $request = Request::create('/api/organization/payroll-periods', 'GET');
            $response = $controller->index($request);

            expect($response->count())->toBe(3);
        });

        it('filters periods by year', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(1)->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(2)->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2025)->periodNumber(1)->create();

            $controller = app(PayrollPeriodController::class);
            $request = Request::create('/api/organization/payroll-periods', 'GET', ['year' => 2026]);
            $response = $controller->index($request);

            expect($response->count())->toBe(2);
        });

        it('filters periods by status', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(1)->draft()->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(2)->open()->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(3)->closed()->create();

            $controller = app(PayrollPeriodController::class);

            $draftRequest = Request::create('/api/organization/payroll-periods', 'GET', ['status' => 'draft']);
            $draftResponse = $controller->index($draftRequest);
            expect($draftResponse->count())->toBe(1);

            $openRequest = Request::create('/api/organization/payroll-periods', 'GET', ['status' => 'open']);
            $openResponse = $controller->index($openRequest);
            expect($openResponse->count())->toBe(1);
        });

        it('filters periods by cycle', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle1 = PayrollCycle::factory()->semiMonthly()->create();
            $cycle2 = PayrollCycle::factory()->monthly()->create();

            PayrollPeriod::factory()->forCycle($cycle1)->forYear(2026)->periodNumber(1)->create();
            PayrollPeriod::factory()->forCycle($cycle1)->forYear(2026)->periodNumber(2)->create();
            PayrollPeriod::factory()->forCycle($cycle2)->forYear(2026)->periodNumber(1)->create();

            $controller = app(PayrollPeriodController::class);
            $request = Request::create('/api/organization/payroll-periods', 'GET', ['payroll_cycle_id' => $cycle1->id]);
            $response = $controller->index($request);

            expect($response->count())->toBe(2);
        });
    });

    describe('store', function () {
        it('creates payroll period with validation', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();

            $periodData = [
                'payroll_cycle_id' => $cycle->id,
                'name' => 'January 2026 - 1st Half',
                'period_type' => 'regular',
                'year' => 2026,
                'period_number' => 1,
                'cutoff_start' => '2026-01-01',
                'cutoff_end' => '2026-01-15',
                'pay_date' => '2026-01-25',
                'status' => 'draft',
                'notes' => 'Test period',
            ];

            $controller = app(PayrollPeriodController::class);
            $storeRequest = createStorePayrollPeriodRequest($periodData, $hrManager);
            $response = $controller->store($storeRequest);

            expect($response->getStatusCode())->toBe(201);

            $data = json_decode($response->getContent(), true);
            expect($data['name'])->toBe('January 2026 - 1st Half');
            expect($data['period_type'])->toBe('regular');
            expect($data['year'])->toBe(2026);
            expect($data['period_number'])->toBe(1);
            expect($data['status'])->toBe('draft');

            $this->assertDatabaseHas('payroll_periods', [
                'name' => 'January 2026 - 1st Half',
                'payroll_cycle_id' => $cycle->id,
            ]);
        });

        it('validates required fields when creating period', function () {
            $rules = (new StorePayrollPeriodRequest)->rules();

            $validator = Validator::make([], $rules);
            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('payroll_cycle_id'))->toBeTrue();
            expect($validator->errors()->has('name'))->toBeTrue();
            expect($validator->errors()->has('period_type'))->toBeTrue();
            expect($validator->errors()->has('year'))->toBeTrue();
            expect($validator->errors()->has('period_number'))->toBeTrue();
            expect($validator->errors()->has('cutoff_start'))->toBeTrue();
            expect($validator->errors()->has('cutoff_end'))->toBeTrue();
            expect($validator->errors()->has('pay_date'))->toBeTrue();
        });
    });

    describe('update', function () {
        it('updates payroll period', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->draft()->create([
                'name' => 'Original Name',
            ]);

            $updateData = [
                'name' => 'Updated Name',
                'notes' => 'Updated notes',
            ];

            $controller = app(PayrollPeriodController::class);
            $updateRequest = createUpdatePayrollPeriodRequest($updateData, $hrManager);
            $response = $controller->update($updateRequest, $period);

            $data = $response->toArray(request());
            expect($data['name'])->toBe('Updated Name');
            expect($data['notes'])->toBe('Updated notes');

            $this->assertDatabaseHas('payroll_periods', [
                'id' => $period->id,
                'name' => 'Updated Name',
            ]);
        });

        it('prevents updating non-editable periods', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            // Processing status is not editable (only draft and open are editable)
            $period = PayrollPeriod::factory()->forCycle($cycle)->processing()->create();

            $updateData = ['name' => 'Updated Name'];

            $controller = app(PayrollPeriodController::class);
            $updateRequest = createUpdatePayrollPeriodRequest($updateData, $hrManager);
            $response = $controller->update($updateRequest, $period);

            expect($response->getStatusCode())->toBe(422);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toContain('Cannot update');
        });
    });

    describe('destroy', function () {
        it('soft deletes draft payroll period', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->draft()->create();

            $controller = app(PayrollPeriodController::class);
            $response = $controller->destroy($period);

            expect($response->getStatusCode())->toBe(200);

            $this->assertSoftDeleted('payroll_periods', [
                'id' => $period->id,
            ]);
        });

        it('prevents deleting non-draft periods', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->open()->create();

            $controller = app(PayrollPeriodController::class);
            $response = $controller->destroy($period);

            expect($response->getStatusCode())->toBe(422);

            // Verify period still exists
            expect(PayrollPeriod::find($period->id))->not->toBeNull();
        });
    });

    describe('generate', function () {
        it('generates periods for a semi-monthly cycle', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();

            $generateData = [
                'payroll_cycle_id' => $cycle->id,
                'year' => 2026,
                'overwrite_existing' => false,
            ];

            $controller = app(PayrollPeriodController::class);
            $generateRequest = createGeneratePayrollPeriodsRequest($generateData, $hrManager);
            $response = $controller->generate($generateRequest);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['generated_count'])->toBe(24);
            expect($data['message'])->toContain('24');

            // Verify periods in database
            $periodCount = PayrollPeriod::where('payroll_cycle_id', $cycle->id)
                ->where('year', 2026)
                ->count();
            expect($periodCount)->toBe(24);
        });

        it('generates periods for a monthly cycle', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->monthly()->create();

            $generateData = [
                'payroll_cycle_id' => $cycle->id,
                'year' => 2026,
                'overwrite_existing' => false,
            ];

            $controller = app(PayrollPeriodController::class);
            $generateRequest = createGeneratePayrollPeriodsRequest($generateData, $hrManager);
            $response = $controller->generate($generateRequest);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['generated_count'])->toBe(12);
        });

        it('returns error for non-recurring cycle', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->supplemental()->create();

            $generateData = [
                'payroll_cycle_id' => $cycle->id,
                'year' => 2026,
                'overwrite_existing' => false,
            ];

            $controller = app(PayrollPeriodController::class);
            $generateRequest = createGeneratePayrollPeriodsRequest($generateData, $hrManager);
            $response = $controller->generate($generateRequest);

            expect($response->getStatusCode())->toBe(422);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toContain('Cannot generate');
        });

        it('overwrites existing draft periods when flag is set', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $controller = app(PayrollPeriodController::class);

            // First generation
            $generateData = [
                'payroll_cycle_id' => $cycle->id,
                'year' => 2026,
                'overwrite_existing' => false,
            ];

            $generateRequest = createGeneratePayrollPeriodsRequest($generateData, $hrManager);
            $controller->generate($generateRequest);

            // Second generation with overwrite
            $generateData['overwrite_existing'] = true;
            $generateRequest2 = createGeneratePayrollPeriodsRequest($generateData, $hrManager);
            $response = $controller->generate($generateRequest2);

            expect($response->getStatusCode())->toBe(200);

            $data = json_decode($response->getContent(), true);
            expect($data['generated_count'])->toBe(24);

            // Verify still only 24 periods
            $periodCount = PayrollPeriod::where('payroll_cycle_id', $cycle->id)
                ->where('year', 2026)
                ->count();
            expect($periodCount)->toBe(24);
        });
    });

    describe('updateStatus', function () {
        it('transitions period from draft to open', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->draft()->create();

            $statusData = ['status' => 'open'];

            $controller = app(PayrollPeriodController::class);
            $statusRequest = createUpdatePayrollPeriodStatusRequest($statusData, $hrManager);
            $response = $controller->updateStatus($statusRequest, $period);

            expect($response)->toBeInstanceOf(\App\Http\Resources\PayrollPeriodResource::class);

            $period->refresh();
            expect($period->status)->toBe(PayrollPeriodStatus::Open);
            expect($period->opened_at)->not->toBeNull();
        });

        it('transitions period from open to processing', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->open()->create();

            $statusData = ['status' => 'processing'];

            $controller = app(PayrollPeriodController::class);
            $statusRequest = createUpdatePayrollPeriodStatusRequest($statusData, $hrManager);
            $response = $controller->updateStatus($statusRequest, $period);

            expect($response)->toBeInstanceOf(\App\Http\Resources\PayrollPeriodResource::class);

            $period->refresh();
            expect($period->status)->toBe(PayrollPeriodStatus::Processing);
        });

        it('transitions period from processing to closed', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->processing()->create();

            $statusData = ['status' => 'closed'];

            $controller = app(PayrollPeriodController::class);
            $statusRequest = createUpdatePayrollPeriodStatusRequest($statusData, $hrManager);
            $response = $controller->updateStatus($statusRequest, $period);

            expect($response)->toBeInstanceOf(\App\Http\Resources\PayrollPeriodResource::class);

            $period->refresh();
            expect($period->status)->toBe(PayrollPeriodStatus::Closed);
            expect($period->closed_at)->not->toBeNull();
            expect($period->closed_by)->toBe($hrManager->id);
        });

        it('prevents invalid status transitions', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->draft()->create();

            // Try to skip to closed (invalid)
            $statusData = ['status' => 'closed'];

            $controller = app(PayrollPeriodController::class);
            $statusRequest = createUpdatePayrollPeriodStatusRequest($statusData, $hrManager);
            $response = $controller->updateStatus($statusRequest, $period);

            expect($response->getStatusCode())->toBe(422);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toContain('Cannot transition');

            // Period should still be draft
            $period->refresh();
            expect($period->status)->toBe(PayrollPeriodStatus::Draft);
        });

        it('prevents transitioning from closed status', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $hrManager = createTenantUserForPayrollPeriod($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->closed()->create();

            // Try to go back to open (invalid)
            $statusData = ['status' => 'open'];

            $controller = app(PayrollPeriodController::class);
            $statusRequest = createUpdatePayrollPeriodStatusRequest($statusData, $hrManager);
            $response = $controller->updateStatus($statusRequest, $period);

            expect($response->getStatusCode())->toBe(422);

            // Period should still be closed
            $period->refresh();
            expect($period->status)->toBe(PayrollPeriodStatus::Closed);
        });
    });

    describe('authorization', function () {
        it('prevents unauthorized user from creating payroll period', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $employee = createTenantUserForPayrollPeriod($tenant, TenantUserRole::Employee);
            $this->actingAs($employee);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();

            $periodData = [
                'payroll_cycle_id' => $cycle->id,
                'name' => 'Test Period',
                'period_type' => 'regular',
                'year' => 2026,
                'period_number' => 1,
                'cutoff_start' => '2026-01-01',
                'cutoff_end' => '2026-01-15',
                'pay_date' => '2026-01-25',
            ];

            $controller = app(PayrollPeriodController::class);
            $storeRequest = createStorePayrollPeriodRequest($periodData, $employee);

            $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

            $controller->store($storeRequest);
        });

        it('prevents unauthorized user from generating periods', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollPeriod($tenant);

            $employee = createTenantUserForPayrollPeriod($tenant, TenantUserRole::Employee);
            $this->actingAs($employee);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();

            $generateData = [
                'payroll_cycle_id' => $cycle->id,
                'year' => 2026,
                'overwrite_existing' => false,
            ];

            $controller = app(PayrollPeriodController::class);
            $generateRequest = createGeneratePayrollPeriodsRequest($generateData, $employee);

            $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

            $controller->generate($generateRequest);
        });
    });
});
