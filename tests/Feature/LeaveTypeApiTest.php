<?php

use App\Enums\AccrualMethod;
use App\Enums\GenderRestriction;
use App\Enums\LeaveCategory;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Models\LeaveType;
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
function bindTenantContextForLeaveType(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForLeaveType(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store leave type request.
 */
function createStoreLeaveTypeRequest(array $data, User $user): StoreLeaveTypeRequest
{
    $request = StoreLeaveTypeRequest::create('/api/organization/leave-types', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreLeaveTypeRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update leave type request.
 */
function createUpdateLeaveTypeRequest(array $data, User $user): UpdateLeaveTypeRequest
{
    $request = UpdateLeaveTypeRequest::create('/api/organization/leave-types/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdateLeaveTypeRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Leave Type API', function () {
    it('returns leave type list', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        $hrManager = createTenantUserForLeaveType($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create leave types for different categories
        LeaveType::factory()->create([
            'name' => 'Service Incentive Leave',
            'code' => 'SIL',
            'leave_category' => LeaveCategory::Statutory,
        ]);

        LeaveType::factory()->create([
            'name' => 'Vacation Leave',
            'code' => 'VL',
            'leave_category' => LeaveCategory::Company,
        ]);

        LeaveType::factory()->create([
            'name' => 'Bereavement Leave',
            'code' => 'BL',
            'leave_category' => LeaveCategory::Special,
        ]);

        $controller = new LeaveTypeController;

        // Test without filters - returns all
        $request = Request::create('/api/organization/leave-types', 'GET');
        $response = $controller->index($request);
        expect($response->count())->toBe(3);

        // Test filter by category
        $categoryRequest = Request::create('/api/organization/leave-types', 'GET', ['category' => 'statutory']);
        $categoryResponse = $controller->index($categoryRequest);
        expect($categoryResponse->count())->toBe(1);
        expect($categoryResponse->first()->code)->toBe('SIL');
    });

    it('filters by active status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        $hrManager = createTenantUserForLeaveType($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        LeaveType::factory()->create([
            'code' => 'VL',
            'is_active' => true,
        ]);

        LeaveType::factory()->create([
            'code' => 'OL',
            'is_active' => false,
        ]);

        $controller = new LeaveTypeController;

        // Filter by active
        $activeRequest = Request::create('/api/organization/leave-types', 'GET', ['active' => '1']);
        $activeResponse = $controller->index($activeRequest);
        expect($activeResponse->count())->toBe(1);
        expect($activeResponse->first()->code)->toBe('VL');

        // Filter by inactive
        $inactiveRequest = Request::create('/api/organization/leave-types', 'GET', ['active' => '0']);
        $inactiveResponse = $controller->index($inactiveRequest);
        expect($inactiveResponse->count())->toBe(1);
        expect($inactiveResponse->first()->code)->toBe('OL');
    });

    it('creates leave type with validation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        $hrManager = createTenantUserForLeaveType($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new LeaveTypeController;

        $leaveTypeData = [
            'name' => 'Vacation Leave',
            'code' => 'VL',
            'description' => 'Annual vacation leave',
            'leave_category' => 'company',
            'accrual_method' => 'annual',
            'default_days_per_year' => 15,
            'allow_carry_over' => true,
            'max_carry_over_days' => 5,
            'is_convertible_to_cash' => true,
            'cash_conversion_rate' => 1.0,
            'max_convertible_days' => 5,
            'requires_approval' => true,
            'is_active' => true,
        ];

        $storeRequest = createStoreLeaveTypeRequest($leaveTypeData, $hrManager);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['name'])->toBe('Vacation Leave');
        expect($data['code'])->toBe('VL');
        expect($data['leave_category'])->toBe('company');
        expect((float) $data['default_days_per_year'])->toBe(15.0);
        expect($data['allow_carry_over'])->toBeTrue();
        expect($data['is_convertible_to_cash'])->toBeTrue();

        // Verify the leave type was created in the database
        $createdLeaveType = LeaveType::where('code', 'VL')->first();
        expect($createdLeaveType)->not->toBeNull();
        expect($createdLeaveType->name)->toBe('Vacation Leave');
    });

    it('updates leave type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        $hrManager = createTenantUserForLeaveType($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new LeaveTypeController;

        $leaveType = LeaveType::factory()->create([
            'name' => 'Original Leave',
            'code' => 'OL',
            'default_days_per_year' => 10,
        ]);

        $updateData = [
            'name' => 'Updated Leave Name',
            'default_days_per_year' => 12,
        ];

        $updateRequest = createUpdateLeaveTypeRequest($updateData, $hrManager);
        $response = $controller->update($updateRequest, '', $leaveType);

        $data = $response->toArray(request());
        expect($data['name'])->toBe('Updated Leave Name');
        expect($data['default_days_per_year'])->toBe(12.0);

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'name' => 'Updated Leave Name',
        ]);
    });

    it('soft deletes leave type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        $hrManager = createTenantUserForLeaveType($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new LeaveTypeController;

        $leaveType = LeaveType::factory()->create([
            'name' => 'Leave to Delete',
            'code' => 'DEL',
        ]);

        $response = $controller->destroy('', $leaveType);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('Leave type deleted successfully.');

        // Verify soft delete - record should still exist but with deleted_at set
        $this->assertSoftDeleted('leave_types', [
            'id' => $leaveType->id,
        ]);

        // Verify it's not returned in queries
        expect(LeaveType::find($leaveType->id))->toBeNull();
        expect(LeaveType::withTrashed()->find($leaveType->id))->not->toBeNull();
    });

    it('prevents unauthorized user from creating leave type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        // Create a regular employee user (not HR Manager)
        $employee = createTenantUserForLeaveType($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $controller = new LeaveTypeController;

        $leaveTypeData = [
            'name' => 'Unauthorized Leave',
            'code' => 'UL',
            'leave_category' => 'company',
            'accrual_method' => 'annual',
            'default_days_per_year' => 5,
        ];

        $storeRequest = createStoreLeaveTypeRequest($leaveTypeData, $employee);

        // This should throw an authorization exception
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->store($storeRequest);
    });

    it('validates required fields when creating leave type', function () {
        $rules = (new StoreLeaveTypeRequest)->rules();

        // Test missing required fields
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('code'))->toBeTrue();
        expect($validator->errors()->has('leave_category'))->toBeTrue();
        expect($validator->errors()->has('accrual_method'))->toBeTrue();
        expect($validator->errors()->has('default_days_per_year'))->toBeTrue();

        // Test invalid leave category
        $invalidCategoryValidator = Validator::make([
            'name' => 'Test Leave',
            'code' => 'TL',
            'leave_category' => 'invalid_category',
            'accrual_method' => 'annual',
            'default_days_per_year' => 5,
        ], $rules);

        expect($invalidCategoryValidator->fails())->toBeTrue();
        expect($invalidCategoryValidator->errors()->has('leave_category'))->toBeTrue();

        // Test valid data passes
        $validValidator = Validator::make([
            'name' => 'Valid Leave',
            'code' => 'VL',
            'leave_category' => 'company',
            'accrual_method' => 'annual',
            'default_days_per_year' => 10,
        ], $rules);

        expect($validValidator->fails())->toBeFalse();
    });

    it('validates unique code constraint', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        $hrManager = createTenantUserForLeaveType($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create an existing leave type
        LeaveType::factory()->create([
            'code' => 'VL',
        ]);

        $rules = (new StoreLeaveTypeRequest)->rules();

        $duplicateCodeValidator = Validator::make([
            'name' => 'Another Leave',
            'code' => 'VL', // Duplicate code
            'leave_category' => 'company',
            'accrual_method' => 'annual',
            'default_days_per_year' => 5,
        ], $rules);

        expect($duplicateCodeValidator->fails())->toBeTrue();
        expect($duplicateCodeValidator->errors()->has('code'))->toBeTrue();
    });

    it('seeds philippine statutory leaves', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        $hrManager = createTenantUserForLeaveType($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new LeaveTypeController;
        $response = $controller->seedStatutory();

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['count'])->toBe(6); // 6 statutory leave types

        // Verify specific statutory leaves exist
        $this->assertDatabaseHas('leave_types', ['code' => 'SIL', 'is_statutory' => true]);
        $this->assertDatabaseHas('leave_types', ['code' => 'MAT', 'is_statutory' => true]);
        $this->assertDatabaseHas('leave_types', ['code' => 'PAT', 'is_statutory' => true]);
        $this->assertDatabaseHas('leave_types', ['code' => 'SPL', 'is_statutory' => true]);
        $this->assertDatabaseHas('leave_types', ['code' => 'VAWC', 'is_statutory' => true]);
        $this->assertDatabaseHas('leave_types', ['code' => 'SLW', 'is_statutory' => true]);
    });

    it('does not duplicate statutory leaves on multiple seeds', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeaveType($tenant);

        $hrManager = createTenantUserForLeaveType($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new LeaveTypeController;

        // Seed twice
        $controller->seedStatutory();
        $response = $controller->seedStatutory();

        $data = json_decode($response->getContent(), true);
        expect($data['count'])->toBe(6);

        // Count should still be 6
        expect(LeaveType::statutory()->count())->toBe(6);
    });
});

describe('Leave Type Model', function () {
    beforeEach(function () {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);
    });

    it('has correct attribute casts', function () {
        $leaveType = LeaveType::factory()->create([
            'leave_category' => LeaveCategory::Statutory,
            'accrual_method' => AccrualMethod::Annual,
            'gender_restriction' => GenderRestriction::Female,
            'tenure_brackets' => [['years' => 1, 'days' => 5]],
            'eligible_employment_types' => ['regular', 'probationary'],
        ]);

        expect($leaveType->leave_category)->toBeInstanceOf(LeaveCategory::class);
        expect($leaveType->accrual_method)->toBeInstanceOf(AccrualMethod::class);
        expect($leaveType->gender_restriction)->toBeInstanceOf(GenderRestriction::class);
        expect($leaveType->tenure_brackets)->toBeArray();
        expect($leaveType->eligible_employment_types)->toBeArray();
    });

    it('filters by active scope', function () {
        LeaveType::factory()->create(['is_active' => true, 'code' => 'A1']);
        LeaveType::factory()->create(['is_active' => true, 'code' => 'A2']);
        LeaveType::factory()->create(['is_active' => false, 'code' => 'I1']);

        expect(LeaveType::active()->count())->toBe(2);
    });

    it('filters by statutory scope', function () {
        LeaveType::factory()->create(['is_statutory' => true, 'code' => 'S1']);
        LeaveType::factory()->create(['is_statutory' => false, 'code' => 'C1']);

        expect(LeaveType::statutory()->count())->toBe(1);
    });

    it('filters by category scope', function () {
        LeaveType::factory()->create(['leave_category' => LeaveCategory::Statutory, 'code' => 'ST1']);
        LeaveType::factory()->create(['leave_category' => LeaveCategory::Company, 'code' => 'CO1']);
        LeaveType::factory()->create(['leave_category' => LeaveCategory::Company, 'code' => 'CO2']);

        expect(LeaveType::byCategory(LeaveCategory::Company)->count())->toBe(2);
        expect(LeaveType::byCategory('statutory')->count())->toBe(1);
    });
});

describe('Leave Type Factory States', function () {
    beforeEach(function () {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);
    });

    it('creates service incentive leave', function () {
        $sil = LeaveType::factory()->serviceIncentiveLeave()->create();

        expect($sil->code)->toBe('SIL');
        expect((float) $sil->default_days_per_year)->toBe(5.0);
        expect($sil->min_tenure_months)->toBe(12);
        expect($sil->is_statutory)->toBeTrue();
        expect($sil->statutory_reference)->toBe('Labor Code Art. 95');
    });

    it('creates maternity leave', function () {
        $maternity = LeaveType::factory()->maternityLeave()->create();

        expect($maternity->code)->toBe('MAT');
        expect((float) $maternity->default_days_per_year)->toBe(105.0);
        expect($maternity->gender_restriction)->toBe(GenderRestriction::Female);
        expect($maternity->is_statutory)->toBeTrue();
        expect($maternity->statutory_reference)->toBe('RA 11210');
    });

    it('creates paternity leave', function () {
        $paternity = LeaveType::factory()->paternityLeave()->create();

        expect($paternity->code)->toBe('PAT');
        expect((float) $paternity->default_days_per_year)->toBe(7.0);
        expect($paternity->gender_restriction)->toBe(GenderRestriction::Male);
        expect($paternity->is_statutory)->toBeTrue();
    });

    it('creates solo parent leave', function () {
        $spl = LeaveType::factory()->soloParentLeave()->create();

        expect($spl->code)->toBe('SPL');
        expect((float) $spl->default_days_per_year)->toBe(7.0);
        expect($spl->gender_restriction)->toBeNull();
        expect($spl->is_statutory)->toBeTrue();
    });

    it('creates vawc leave', function () {
        $vawc = LeaveType::factory()->vawcLeave()->create();

        expect($vawc->code)->toBe('VAWC');
        expect((float) $vawc->default_days_per_year)->toBe(10.0);
        expect($vawc->gender_restriction)->toBe(GenderRestriction::Female);
        expect($vawc->is_statutory)->toBeTrue();
    });

    it('creates special leave for women', function () {
        $slw = LeaveType::factory()->specialLeaveForWomen()->create();

        expect($slw->code)->toBe('SLW');
        expect((float) $slw->default_days_per_year)->toBe(60.0);
        expect($slw->gender_restriction)->toBe(GenderRestriction::Female);
        expect($slw->is_statutory)->toBeTrue();
    });
});
