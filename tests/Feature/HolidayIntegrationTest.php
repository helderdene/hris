<?php

/**
 * Strategic Gap Tests for Philippine Holiday Calendar Feature
 *
 * Task Group 10.3: Additional tests to fill critical coverage gaps.
 * These tests focus on:
 * - End-to-end holiday CRUD workflow
 * - Holiday show endpoint
 * - Calendar display with mixed national/local holidays
 * - Year-over-year holiday management workflow
 */

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
function bindTenantForHolidayIntegrationTests(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForHolidayIntegrationTests(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function createStoreRequestForHolidayIntegrationTests(array $data, User $user): StoreHolidayRequest
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
function createUpdateRequestForHolidayIntegrationTests(array $data, User $user): UpdateHolidayRequest
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

describe('End-to-End Holiday CRUD Workflow', function () {
    it('completes a full holiday lifecycle: create, read, update, delete', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayIntegrationTests($tenant);

        $hrManager = createTenantUserForHolidayIntegrationTests($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new HolidayController;
        $currentYear = now()->year;

        // Step 1: CREATE - Create a new holiday
        $createData = [
            'name' => 'Founding Anniversary',
            'date' => "{$currentYear}-03-15",
            'holiday_type' => 'special_non_working',
            'description' => 'Company founding anniversary',
            'is_national' => false,
        ];

        $storeRequest = createStoreRequestForHolidayIntegrationTests($createData, $hrManager);
        $createResponse = $controller->store($storeRequest);

        expect($createResponse->getStatusCode())->toBe(201);
        $createData = json_decode($createResponse->getContent(), true);
        $holidayId = $createData['id'];

        // Verify holiday was created
        $holiday = Holiday::find($holidayId);
        expect($holiday)->not->toBeNull();
        expect($holiday->name)->toBe('Founding Anniversary');

        // Step 2: READ - Retrieve the holiday using show endpoint
        $showResponse = $controller->show($holiday);
        $showData = $showResponse->toArray(request());

        expect($showData['id'])->toBe($holidayId);
        expect($showData['name'])->toBe('Founding Anniversary');
        expect($showData['holiday_type'])->toBe('special_non_working');
        expect($showData['holiday_type_label'])->toBe('Special Non-Working Day');

        // Step 3: UPDATE - Modify the holiday
        $updateData = [
            'name' => 'Company Founding Day',
            'holiday_type' => 'special_working',
            'description' => 'Updated company founding day celebration',
        ];

        $updateRequest = createUpdateRequestForHolidayIntegrationTests($updateData, $hrManager);
        $updateResponse = $controller->update($updateRequest, $holiday);
        $updatedData = $updateResponse->toArray(request());

        expect($updatedData['name'])->toBe('Company Founding Day');
        expect($updatedData['holiday_type'])->toBe('special_working');
        expect($updatedData['description'])->toBe('Updated company founding day celebration');

        // Verify in database
        $holiday->refresh();
        expect($holiday->name)->toBe('Company Founding Day');
        expect($holiday->holiday_type)->toBe(HolidayType::SpecialWorking);

        // Step 4: DELETE - Soft delete the holiday
        $deleteResponse = $controller->destroy($holiday);
        expect($deleteResponse->getStatusCode())->toBe(200);

        // Verify soft delete
        expect(Holiday::find($holidayId))->toBeNull();
        expect(Holiday::withTrashed()->find($holidayId))->not->toBeNull();
    });
});

describe('Holiday Show Endpoint', function () {
    it('returns a single holiday with all required fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayIntegrationTests($tenant);

        $hrManager = createTenantUserForHolidayIntegrationTests($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $location = WorkLocation::factory()->create([
            'name' => 'Davao Office',
            'code' => 'DVO',
        ]);

        $holiday = Holiday::factory()->create([
            'name' => 'Kadayawan Festival',
            'date' => '2026-08-17',
            'holiday_type' => HolidayType::SpecialNonWorking,
            'description' => 'Davao City festival celebration',
            'is_national' => false,
            'work_location_id' => $location->id,
            'year' => 2026,
        ]);

        $controller = new HolidayController;
        $response = $controller->show($holiday);
        $data = $response->toArray(request());

        // Verify all required fields are present
        expect($data)->toHaveKey('id');
        expect($data)->toHaveKey('name');
        expect($data)->toHaveKey('date');
        expect($data)->toHaveKey('holiday_type');
        expect($data)->toHaveKey('holiday_type_label');
        expect($data)->toHaveKey('description');
        expect($data)->toHaveKey('is_national');
        expect($data)->toHaveKey('year');
        expect($data)->toHaveKey('scope_label');
        expect($data)->toHaveKey('work_location');

        // Verify values
        expect($data['name'])->toBe('Kadayawan Festival');
        expect($data['holiday_type'])->toBe('special_non_working');
        expect($data['holiday_type_label'])->toBe('Special Non-Working Day');
        expect($data['is_national'])->toBeFalse();
        expect($data['scope_label'])->toBe('Davao Office');
        expect($data['work_location']['name'])->toBe('Davao Office');
    });
});

describe('Calendar Display with Mixed Holidays', function () {
    it('displays calendar with both national and multiple location-specific holidays', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayIntegrationTests($tenant);

        $employee = createTenantUserForHolidayIntegrationTests($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $currentYear = now()->year;

        // Create multiple work locations
        $manilaLocation = WorkLocation::factory()->create(['name' => 'Manila Office', 'code' => 'MNL']);
        $cebuLocation = WorkLocation::factory()->create(['name' => 'Cebu Office', 'code' => 'CEB']);

        // Create national holidays
        Holiday::factory()->national()->create([
            'name' => 'Independence Day',
            'date' => "{$currentYear}-06-12",
            'holiday_type' => HolidayType::Regular,
            'year' => $currentYear,
        ]);

        Holiday::factory()->national()->create([
            'name' => 'Christmas Day',
            'date' => "{$currentYear}-12-25",
            'holiday_type' => HolidayType::Regular,
            'year' => $currentYear,
        ]);

        // Create Manila-specific holiday
        Holiday::factory()->local($manilaLocation)->create([
            'name' => 'Manila Day',
            'date' => "{$currentYear}-06-24",
            'holiday_type' => HolidayType::SpecialNonWorking,
            'year' => $currentYear,
        ]);

        // Create Cebu-specific holiday
        Holiday::factory()->local($cebuLocation)->create([
            'name' => 'Sinulog Festival',
            'date' => "{$currentYear}-01-19",
            'holiday_type' => HolidayType::SpecialNonWorking,
            'year' => $currentYear,
        ]);

        $controller = new HolidayController;

        // Test calendar without location filter - should show ALL holidays
        $allRequest = Request::create('/api/organization/holidays/calendar', 'GET', [
            'year' => $currentYear,
        ]);
        $allResponse = $controller->calendar($allRequest);
        $allData = json_decode($allResponse->getContent(), true);

        expect($allData['total_holidays'])->toBe(4);

        // Test calendar with Manila location filter - should show national + Manila holidays
        $manilaRequest = Request::create('/api/organization/holidays/calendar', 'GET', [
            'year' => $currentYear,
            'work_location_id' => $manilaLocation->id,
        ]);
        $manilaResponse = $controller->calendar($manilaRequest);
        $manilaData = json_decode($manilaResponse->getContent(), true);

        expect($manilaData['total_holidays'])->toBe(3);
        $manilaHolidayNames = collect($manilaData['months'])
            ->flatMap(fn ($m) => collect($m['holidays'])->pluck('name'))
            ->toArray();
        expect($manilaHolidayNames)->toContain('Independence Day');
        expect($manilaHolidayNames)->toContain('Christmas Day');
        expect($manilaHolidayNames)->toContain('Manila Day');
        expect($manilaHolidayNames)->not->toContain('Sinulog Festival');

        // Test calendar with Cebu location filter - should show national + Cebu holidays
        $cebuRequest = Request::create('/api/organization/holidays/calendar', 'GET', [
            'year' => $currentYear,
            'work_location_id' => $cebuLocation->id,
        ]);
        $cebuResponse = $controller->calendar($cebuRequest);
        $cebuData = json_decode($cebuResponse->getContent(), true);

        expect($cebuData['total_holidays'])->toBe(3);
        $cebuHolidayNames = collect($cebuData['months'])
            ->flatMap(fn ($m) => collect($m['holidays'])->pluck('name'))
            ->toArray();
        expect($cebuHolidayNames)->toContain('Independence Day');
        expect($cebuHolidayNames)->toContain('Christmas Day');
        expect($cebuHolidayNames)->toContain('Sinulog Festival');
        expect($cebuHolidayNames)->not->toContain('Manila Day');
    });
});

describe('Year-Over-Year Holiday Management', function () {
    it('manages holidays across multiple years correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayIntegrationTests($tenant);

        $hrManager = createTenantUserForHolidayIntegrationTests($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $currentYear = now()->year;
        $nextYear = $currentYear + 1;

        // Create holidays for current year
        Holiday::factory()->create([
            'name' => 'New Year',
            'date' => "{$currentYear}-01-01",
            'holiday_type' => HolidayType::Regular,
            'year' => $currentYear,
        ]);

        Holiday::factory()->create([
            'name' => 'Labor Day',
            'date' => "{$currentYear}-05-01",
            'holiday_type' => HolidayType::Regular,
            'year' => $currentYear,
        ]);

        // Create a holiday for next year (different from current year)
        Holiday::factory()->create([
            'name' => 'Next Year Special Holiday',
            'date' => "{$nextYear}-07-04",
            'holiday_type' => HolidayType::SpecialWorking,
            'year' => $nextYear,
        ]);

        $controller = new HolidayController;

        // Verify year-based filtering works correctly
        $currentYearRequest = Request::create('/api/organization/holidays', 'GET', [
            'year' => $currentYear,
        ]);
        $currentYearResponse = $controller->index($currentYearRequest);
        expect($currentYearResponse->count())->toBe(2);

        $nextYearRequest = Request::create('/api/organization/holidays', 'GET', [
            'year' => $nextYear,
        ]);
        $nextYearResponse = $controller->index($nextYearRequest);
        expect($nextYearResponse->count())->toBe(1);
        expect($nextYearResponse->first()->name)->toBe('Next Year Special Holiday');

        // Verify calendar endpoint also respects year filtering
        $currentYearCalendarRequest = Request::create('/api/organization/holidays/calendar', 'GET', [
            'year' => $currentYear,
        ]);
        $currentYearCalendarResponse = $controller->calendar($currentYearCalendarRequest);
        $currentYearCalendarData = json_decode($currentYearCalendarResponse->getContent(), true);

        expect($currentYearCalendarData['year'])->toBe($currentYear);
        expect($currentYearCalendarData['total_holidays'])->toBe(2);
    });
});

describe('Holiday Type Premium Rate Integration', function () {
    it('correctly retrieves premium rates for all holiday types', function () {
        $tenant = Tenant::factory()->create([
            'payroll_settings' => [
                'pay_frequency' => 'semi-monthly',
                'cutoff_day' => 15,
                'double_holiday_rate' => 400, // Custom tenant rate
            ],
        ]);
        bindTenantForHolidayIntegrationTests($tenant);

        $currentYear = now()->year;

        // Create holidays of each type
        $regularHoliday = Holiday::factory()->create([
            'name' => 'Regular Holiday Test',
            'date' => "{$currentYear}-01-01",
            'holiday_type' => HolidayType::Regular,
            'year' => $currentYear,
        ]);

        $specialNonWorkingHoliday = Holiday::factory()->create([
            'name' => 'Special Non-Working Test',
            'date' => "{$currentYear}-02-01",
            'holiday_type' => HolidayType::SpecialNonWorking,
            'year' => $currentYear,
        ]);

        $specialWorkingHoliday = Holiday::factory()->create([
            'name' => 'Special Working Test',
            'date' => "{$currentYear}-03-01",
            'holiday_type' => HolidayType::SpecialWorking,
            'year' => $currentYear,
        ]);

        $doubleHoliday = Holiday::factory()->create([
            'name' => 'Double Holiday Test',
            'date' => "{$currentYear}-04-01",
            'holiday_type' => HolidayType::Double,
            'year' => $currentYear,
        ]);

        // Test premium rates
        expect($regularHoliday->holiday_type->premiumRate())->toBe(200);
        expect($specialNonWorkingHoliday->holiday_type->premiumRate())->toBe(130);
        expect($specialWorkingHoliday->holiday_type->premiumRate())->toBe(100);

        // Double holiday should use tenant-configured rate via premiumRateForTenant
        expect($doubleHoliday->holiday_type->premiumRateForTenant())->toBe(400);

        // Also verify default when no tenant rate is set
        expect(HolidayType::Double->premiumRate())->toBe(300);
    });
});
