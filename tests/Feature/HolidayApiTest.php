<?php

use App\Enums\HolidayType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForHoliday(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForHoliday(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store holiday request.
 */
function createStoreHolidayRequest(array $data, User $user): StoreHolidayRequest
{
    $request = StoreHolidayRequest::create('/api/organization/holidays', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreHolidayRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update holiday request.
 */
function createUpdateHolidayRequest(array $data, User $user): UpdateHolidayRequest
{
    $request = UpdateHolidayRequest::create('/api/organization/holidays/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdateHolidayRequest)->rules());
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

describe('Holiday API', function () {
    it('returns holiday list with filters on index', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHoliday($tenant);

        $hrManager = createTenantUserForHoliday($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create holidays for different years and months
        Holiday::factory()->create([
            'name' => 'New Year 2025',
            'date' => '2025-01-01',
            'year' => 2025,
            'holiday_type' => HolidayType::Regular,
        ]);

        Holiday::factory()->create([
            'name' => 'Independence Day 2025',
            'date' => '2025-06-12',
            'year' => 2025,
            'holiday_type' => HolidayType::Regular,
        ]);

        Holiday::factory()->create([
            'name' => 'New Year 2026',
            'date' => '2026-01-01',
            'year' => 2026,
            'holiday_type' => HolidayType::Regular,
        ]);

        $controller = new HolidayController;

        // Test without filters - returns all
        $request = Request::create('/api/organization/holidays', 'GET');
        $response = $controller->index($request);
        expect($response->count())->toBe(3);

        // Test filter by year
        $yearRequest = Request::create('/api/organization/holidays', 'GET', ['year' => 2025]);
        $yearResponse = $controller->index($yearRequest);
        expect($yearResponse->count())->toBe(2);

        // Test filter by month
        $monthRequest = Request::create('/api/organization/holidays', 'GET', ['month' => 1]);
        $monthResponse = $controller->index($monthRequest);
        expect($monthResponse->count())->toBe(2);

        // Test combined filter
        $combinedRequest = Request::create('/api/organization/holidays', 'GET', ['year' => 2025, 'month' => 6]);
        $combinedResponse = $controller->index($combinedRequest);
        expect($combinedResponse->count())->toBe(1);
        expect($combinedResponse->first()->name)->toBe('Independence Day 2025');
    });

    it('returns calendar-formatted data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHoliday($tenant);

        $hrManager = createTenantUserForHoliday($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create holidays for current year
        $currentYear = now()->year;

        Holiday::factory()->create([
            'name' => 'January Holiday',
            'date' => "{$currentYear}-01-15",
            'year' => $currentYear,
            'holiday_type' => HolidayType::Regular,
        ]);

        Holiday::factory()->create([
            'name' => 'January Holiday 2',
            'date' => "{$currentYear}-01-25",
            'year' => $currentYear,
            'holiday_type' => HolidayType::SpecialNonWorking,
        ]);

        Holiday::factory()->create([
            'name' => 'June Holiday',
            'date' => "{$currentYear}-06-12",
            'year' => $currentYear,
            'holiday_type' => HolidayType::Regular,
        ]);

        $controller = new HolidayController;

        $request = Request::create('/api/organization/holidays/calendar', 'GET', ['year' => $currentYear]);
        $response = $controller->calendar($request);

        $data = json_decode($response->getContent(), true);

        expect($data['year'])->toBe($currentYear);
        expect($data['total_holidays'])->toBe(3);
        expect($data['months'])->toHaveCount(2); // January and June

        // Check first month (January)
        $january = collect($data['months'])->firstWhere('month_number', 1);
        expect($january['month'])->toBe('January');
        expect($january['holidays'])->toHaveCount(2);
    });

    it('creates holiday with validation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHoliday($tenant);

        $hrManager = createTenantUserForHoliday($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new HolidayController;

        $holidayData = [
            'name' => 'Christmas Day',
            'date' => '2025-12-25',
            'holiday_type' => 'regular',
            'description' => 'National Christmas holiday',
            'is_national' => true,
        ];

        $storeRequest = createStoreHolidayRequest($holidayData, $hrManager);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['name'])->toBe('Christmas Day');
        expect($data['date'])->toBe('2025-12-25');
        expect($data['holiday_type'])->toBe('regular');
        expect($data['holiday_type_label'])->toBe('Regular Holiday');
        expect($data['is_national'])->toBeTrue();
        expect($data['year'])->toBe(2025);

        // Verify the holiday was created in the database
        $createdHoliday = Holiday::where('name', 'Christmas Day')->first();
        expect($createdHoliday)->not->toBeNull();
        expect($createdHoliday->date->toDateString())->toBe('2025-12-25');
        expect($createdHoliday->year)->toBe(2025);
    });

    it('updates holiday', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHoliday($tenant);

        $hrManager = createTenantUserForHoliday($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new HolidayController;

        $holiday = Holiday::factory()->create([
            'name' => 'Original Holiday',
            'date' => '2025-01-01',
            'holiday_type' => HolidayType::Regular,
            'year' => 2025,
        ]);

        $updateData = [
            'name' => 'Updated Holiday Name',
            'description' => 'New description',
        ];

        $updateRequest = createUpdateHolidayRequest($updateData, $hrManager);
        $response = $controller->update($updateRequest, $holiday);

        $data = $response->toArray(request());
        expect($data['name'])->toBe('Updated Holiday Name');
        expect($data['description'])->toBe('New description');

        $this->assertDatabaseHas('holidays', [
            'id' => $holiday->id,
            'name' => 'Updated Holiday Name',
        ]);
    });

    it('soft deletes holiday', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHoliday($tenant);

        $hrManager = createTenantUserForHoliday($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new HolidayController;

        $holiday = Holiday::factory()->create([
            'name' => 'Holiday to Delete',
            'date' => '2025-05-01',
            'year' => 2025,
        ]);

        $response = $controller->destroy($holiday);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('Holiday deleted successfully.');

        // Verify soft delete - record should still exist but with deleted_at set
        $this->assertSoftDeleted('holidays', [
            'id' => $holiday->id,
        ]);

        // Verify it's not returned in queries
        expect(Holiday::find($holiday->id))->toBeNull();
        expect(Holiday::withTrashed()->find($holiday->id))->not->toBeNull();
    });

    it('filters by work_location_id and returns location-specific holidays', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHoliday($tenant);

        $hrManager = createTenantUserForHoliday($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $location = WorkLocation::factory()->create([
            'name' => 'Manila Office',
        ]);

        $otherLocation = WorkLocation::factory()->create([
            'name' => 'Cebu Office',
        ]);

        // National holiday
        Holiday::factory()->national()->create([
            'name' => 'National Holiday',
            'date' => '2025-06-12',
            'year' => 2025,
        ]);

        // Manila-specific holiday
        Holiday::factory()->local($location)->create([
            'name' => 'Manila Local Holiday',
            'date' => '2025-08-26',
            'year' => 2025,
        ]);

        // Cebu-specific holiday
        Holiday::factory()->local($otherLocation)->create([
            'name' => 'Cebu Local Holiday',
            'date' => '2025-08-27',
            'year' => 2025,
        ]);

        $controller = new HolidayController;

        // Filter by Manila location - should return national + Manila holidays
        $request = Request::create('/api/organization/holidays', 'GET', ['work_location_id' => $location->id]);
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
        $names = $response->pluck('name')->toArray();
        expect($names)->toContain('National Holiday');
        expect($names)->toContain('Manila Local Holiday');
        expect($names)->not->toContain('Cebu Local Holiday');
    });

    it('prevents unauthorized user from creating holiday', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForHoliday($tenant);

        // Create a regular employee user (not HR Manager or HR Staff)
        $employee = createTenantUserForHoliday($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $controller = new HolidayController;

        $holidayData = [
            'name' => 'Unauthorized Holiday',
            'date' => '2025-12-25',
            'holiday_type' => 'regular',
            'is_national' => true,
        ];

        $storeRequest = createStoreHolidayRequest($holidayData, $employee);

        // This should throw an authorization exception
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->store($storeRequest);
    });

    it('validates required fields when creating holiday', function () {
        $rules = (new StoreHolidayRequest)->rules();

        // Test missing required fields
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('date'))->toBeTrue();
        expect($validator->errors()->has('holiday_type'))->toBeTrue();

        // Test invalid holiday type
        $invalidTypeValidator = Validator::make([
            'name' => 'Test Holiday',
            'date' => '2025-12-25',
            'holiday_type' => 'invalid_type',
        ], $rules);

        expect($invalidTypeValidator->fails())->toBeTrue();
        expect($invalidTypeValidator->errors()->has('holiday_type'))->toBeTrue();

        // Test valid data passes
        $validValidator = Validator::make([
            'name' => 'Valid Holiday',
            'date' => '2025-12-25',
            'holiday_type' => 'regular',
        ], $rules);

        expect($validValidator->fails())->toBeFalse();
    });
});
