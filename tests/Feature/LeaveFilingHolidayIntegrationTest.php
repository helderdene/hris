<?php

/**
 * Tests for Leave Filing Holiday Integration
 *
 * These tests verify the holiday calendar API endpoints that power the
 * useHolidayCalendar composable used in leave filing date pickers.
 *
 * Task Group 8.1: Write 3-4 focused tests for leave filing integration
 */

use App\Enums\HolidayType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\HolidayController;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForLeave(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForLeave(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Leave Filing Holiday Integration', function () {
    /**
     * Test 1: useHolidayCalendar composable fetches holidays for date range
     *
     * This test verifies that the API can fetch holidays within a specific
     * date range. The composable's fetchHolidaysForRange method relies on
     * the year and month filters to efficiently query holidays.
     */
    it('fetches holidays for a date range via calendar API', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeave($tenant);

        $employee = createTenantUserForLeave($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $currentYear = now()->year;

        // Create holidays for the date range
        Holiday::factory()->create([
            'name' => 'New Year Day',
            'date' => "{$currentYear}-01-01",
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear,
        ]);

        Holiday::factory()->create([
            'name' => 'Araw ng Kagitingan',
            'date' => "{$currentYear}-04-09",
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear,
        ]);

        Holiday::factory()->create([
            'name' => 'Christmas Day',
            'date' => "{$currentYear}-12-25",
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear,
        ]);

        // Create a holiday for next year (should not be included)
        Holiday::factory()->create([
            'name' => 'Next Year Holiday',
            'date' => ($currentYear + 1).'-01-01',
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear + 1,
        ]);

        $controller = new HolidayController;

        // Simulate composable fetching holidays for current year
        $request = Request::create('/api/organization/holidays/calendar', 'GET', [
            'year' => $currentYear,
        ]);

        $response = $controller->calendar($request);
        $data = json_decode($response->getContent(), true);

        // Verify the API returns holidays for the requested year
        expect($data['year'])->toBe($currentYear);
        expect($data['total_holidays'])->toBe(3);
        expect($data['months'])->toHaveCount(3); // January, April, December

        // Verify holidays are grouped correctly by month
        $months = collect($data['months']);
        $januaryMonth = $months->firstWhere('month_number', 1);
        expect($januaryMonth['holidays'])->toHaveCount(1);
        expect($januaryMonth['holidays'][0]['name'])->toBe('New Year Day');

        $aprilMonth = $months->firstWhere('month_number', 4);
        expect($aprilMonth['holidays'])->toHaveCount(1);
        expect($aprilMonth['holidays'][0]['name'])->toBe('Araw ng Kagitingan');

        $decemberMonth = $months->firstWhere('month_number', 12);
        expect($decemberMonth['holidays'])->toHaveCount(1);
        expect($decemberMonth['holidays'][0]['name'])->toBe('Christmas Day');
    });

    /**
     * Test 2: Holidays are available for date picker highlighting
     *
     * This test verifies that the API returns holiday data in a format
     * suitable for highlighting dates in a date picker calendar.
     * The composable's isHoliday method relies on the date field.
     */
    it('returns holidays with date information for date picker highlighting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeave($tenant);

        $employee = createTenantUserForLeave($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $currentYear = now()->year;

        // Create holidays with different types
        Holiday::factory()->create([
            'name' => 'Independence Day',
            'date' => "{$currentYear}-06-12",
            'holiday_type' => HolidayType::Regular,
            'description' => 'Philippine Independence Day',
            'is_national' => true,
            'year' => $currentYear,
        ]);

        Holiday::factory()->create([
            'name' => 'Ninoy Aquino Day',
            'date' => "{$currentYear}-08-21",
            'holiday_type' => HolidayType::SpecialNonWorking,
            'description' => 'Ninoy Aquino Day',
            'is_national' => true,
            'year' => $currentYear,
        ]);

        $controller = new HolidayController;

        // Fetch holidays for the entire year (for date picker rendering)
        $request = Request::create('/api/organization/holidays/calendar', 'GET', [
            'year' => $currentYear,
        ]);

        $response = $controller->calendar($request);
        $data = json_decode($response->getContent(), true);

        // Check that holidays have all required fields for date picker
        $months = collect($data['months']);
        $juneMonth = $months->firstWhere('month_number', 6);

        $independenceDay = $juneMonth['holidays'][0];
        expect($independenceDay)->toHaveKey('date');
        expect($independenceDay)->toHaveKey('name');
        expect($independenceDay)->toHaveKey('holiday_type');
        expect($independenceDay)->toHaveKey('holiday_type_label');
        expect($independenceDay['date'])->toBe("{$currentYear}-06-12");
        expect($independenceDay['name'])->toBe('Independence Day');
        expect($independenceDay['holiday_type_label'])->toBe('Regular Holiday');

        $augustMonth = $months->firstWhere('month_number', 8);
        $ninoyDay = $augustMonth['holidays'][0];
        expect($ninoyDay['date'])->toBe("{$currentYear}-08-21");
        expect($ninoyDay['name'])->toBe('Ninoy Aquino Day');
        expect($ninoyDay['holiday_type_label'])->toBe('Special Non-Working Day');
    });

    /**
     * Test 3: Get holidays in a specific date range for leave warning
     *
     * This test verifies that holidays can be identified within a
     * specific leave date range. The composable's getHolidaysInRange
     * method uses this to show warnings when leave includes holidays.
     */
    it('identifies holidays within a leave date range for warning display', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeave($tenant);

        $employee = createTenantUserForLeave($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $currentYear = now()->year;

        // Create holidays
        Holiday::factory()->create([
            'name' => 'Labor Day',
            'date' => "{$currentYear}-05-01",
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear,
        ]);

        Holiday::factory()->create([
            'name' => 'Independence Day',
            'date' => "{$currentYear}-06-12",
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear,
        ]);

        Holiday::factory()->create([
            'name' => 'Christmas Day',
            'date' => "{$currentYear}-12-25",
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear,
        ]);

        $controller = new HolidayController;

        // Simulate checking if a leave from April 28 to May 5 includes holidays
        // This simulates what the composable does with getHolidaysInRange
        $request = Request::create('/api/organization/holidays', 'GET', [
            'year' => $currentYear,
            'month' => 5, // Filter to May only for this specific check
        ]);

        $response = $controller->index($request);

        // May holidays should include Labor Day (May 1)
        expect($response->count())->toBe(1);
        expect($response->first()->name)->toBe('Labor Day');
        expect($response->first()->date->format('Y-m-d'))->toBe("{$currentYear}-05-01");

        // Now check June - should have Independence Day
        $juneRequest = Request::create('/api/organization/holidays', 'GET', [
            'year' => $currentYear,
            'month' => 6,
        ]);
        $juneResponse = $controller->index($juneRequest);
        expect($juneResponse->count())->toBe(1);
        expect($juneResponse->first()->name)->toBe('Independence Day');
    });

    /**
     * Test 4: Location-specific holidays are included for employee context
     *
     * This test verifies that when an employee's work location is specified,
     * both national holidays and their location-specific holidays are returned.
     * This is important for accurate holiday calculations in leave filing.
     */
    it('includes both national and location-specific holidays for employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForLeave($tenant);

        $employee = createTenantUserForLeave($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $currentYear = now()->year;

        // Create a work location (Manila)
        $manilaLocation = WorkLocation::factory()->create([
            'name' => 'Manila Office',
            'code' => 'MNL',
            'status' => 'active',
        ]);

        // Create another work location (Cebu)
        $cebuLocation = WorkLocation::factory()->create([
            'name' => 'Cebu Office',
            'code' => 'CEB',
            'status' => 'active',
        ]);

        // Create a national holiday
        Holiday::factory()->national()->create([
            'name' => 'Independence Day',
            'date' => "{$currentYear}-06-12",
            'holiday_type' => HolidayType::Regular,
            'year' => $currentYear,
        ]);

        // Create a Manila-specific holiday
        Holiday::factory()->local($manilaLocation)->create([
            'name' => 'Manila Day',
            'date' => "{$currentYear}-06-24",
            'holiday_type' => HolidayType::SpecialNonWorking,
            'year' => $currentYear,
        ]);

        // Create a Cebu-specific holiday (should NOT be included for Manila employee)
        Holiday::factory()->local($cebuLocation)->create([
            'name' => 'Cebu Foundation Day',
            'date' => "{$currentYear}-08-06",
            'holiday_type' => HolidayType::SpecialNonWorking,
            'year' => $currentYear,
        ]);

        $controller = new HolidayController;

        // Simulate an employee from Manila checking holidays (via calendar endpoint)
        $request = Request::create('/api/organization/holidays/calendar', 'GET', [
            'year' => $currentYear,
            'work_location_id' => $manilaLocation->id,
        ]);

        $response = $controller->calendar($request);
        $data = json_decode($response->getContent(), true);

        // Should include national + Manila holidays only (2 total)
        expect($data['total_holidays'])->toBe(2);

        // Flatten all holidays from months to verify
        $allHolidays = collect($data['months'])->flatMap(fn ($m) => $m['holidays']);
        $holidayNames = $allHolidays->pluck('name')->toArray();

        expect($holidayNames)->toContain('Independence Day');
        expect($holidayNames)->toContain('Manila Day');
        expect($holidayNames)->not->toContain('Cebu Foundation Day');

        // Now check with index endpoint for Manila location
        $indexRequest = Request::create('/api/organization/holidays', 'GET', [
            'year' => $currentYear,
            'work_location_id' => $manilaLocation->id,
        ]);

        $indexResponse = $controller->index($indexRequest);
        expect($indexResponse->count())->toBe(2);
    });
});
