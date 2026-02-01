<?php

use App\Enums\HolidayType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\OrganizationController;
use App\Http\Requests\CopyHolidaysRequest;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantForHolidayPage(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForHolidayPage(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated CopyHolidaysRequest for Holiday Page tests.
 */
function createCopyHolidaysRequestForPage(array $data, User $user): CopyHolidaysRequest
{
    $request = CopyHolidaysRequest::create('/api/organization/holidays/copy-to-year', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new CopyHolidaysRequest)->rules());
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

describe('Holiday List Table Rendering', function () {
    it('renders holiday list table with holidays data', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayPage($tenant);

        $admin = createTenantUserForHolidayPage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create some holidays
        Holiday::factory()->create([
            'name' => 'Christmas Day',
            'date' => '2026-12-25',
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => 2026,
        ]);

        Holiday::factory()->create([
            'name' => 'New Year',
            'date' => '2026-01-01',
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => 2026,
        ]);

        // Test the controller directly
        $controller = new OrganizationController;
        $inertiaResponse = $controller->holidaysIndex();

        // Use reflection to access protected properties
        $reflection = new ReflectionClass($inertiaResponse);

        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);
        expect($componentProperty->getValue($inertiaResponse))->toBe('Organization/Holidays/Index');

        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Check that holidays are returned
        $holidays = $props['holidays']->collection;
        expect($holidays)->toHaveCount(2);

        // Verify holiday types are passed
        expect($props['holidayTypes'])->toBeArray();
        expect(count($props['holidayTypes']))->toBe(4); // Regular, SpecialNonWorking, SpecialWorking, Double
    });

    it('shows empty state when no holidays exist', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayPage($tenant);

        $admin = createTenantUserForHolidayPage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new OrganizationController;
        $inertiaResponse = $controller->holidaysIndex();

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Check that no holidays are returned
        $holidays = $props['holidays']->collection;
        expect($holidays)->toHaveCount(0);
    });
});

describe('Holiday Type Badges', function () {
    it('returns correct holiday type labels for badge display', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayPage($tenant);

        $admin = createTenantUserForHolidayPage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create holidays of different types
        Holiday::factory()->create([
            'name' => 'Regular Holiday Test',
            'date' => '2026-06-12',
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => 2026,
        ]);

        Holiday::factory()->create([
            'name' => 'Special Non-Working Test',
            'date' => '2026-08-21',
            'holiday_type' => HolidayType::SpecialNonWorking,
            'is_national' => true,
            'year' => 2026,
        ]);

        Holiday::factory()->create([
            'name' => 'Special Working Test',
            'date' => '2026-02-10',
            'holiday_type' => HolidayType::SpecialWorking,
            'is_national' => true,
            'year' => 2026,
        ]);

        Holiday::factory()->create([
            'name' => 'Double Holiday Test',
            'date' => '2026-04-09',
            'holiday_type' => HolidayType::Double,
            'is_national' => true,
            'year' => 2026,
        ]);

        $controller = new OrganizationController;
        $inertiaResponse = $controller->holidaysIndex();

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $holidays = $props['holidays']->collection;
        expect($holidays)->toHaveCount(4);

        // Check that holiday type labels are correct
        $holidayArray = $holidays->keyBy('name');

        expect($holidayArray['Regular Holiday Test']->toArray(request())['holiday_type_label'])
            ->toBe('Regular Holiday');
        expect($holidayArray['Special Non-Working Test']->toArray(request())['holiday_type_label'])
            ->toBe('Special Non-Working Day');
        expect($holidayArray['Special Working Test']->toArray(request())['holiday_type_label'])
            ->toBe('Special Working Day');
        expect($holidayArray['Double Holiday Test']->toArray(request())['holiday_type_label'])
            ->toBe('Double Holiday');
    });
});

describe('Holiday Scope Badges', function () {
    it('returns correct scope labels for national and location-specific holidays', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayPage($tenant);

        $admin = createTenantUserForHolidayPage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create a work location
        $location = WorkLocation::factory()->create([
            'name' => 'Manila Office',
            'code' => 'MNL',
            'status' => 'active',
        ]);

        // Create national holiday
        Holiday::factory()->create([
            'name' => 'National Holiday',
            'date' => '2026-06-12',
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => 2026,
        ]);

        // Create location-specific holiday
        Holiday::factory()->create([
            'name' => 'Manila Day',
            'date' => '2026-06-24',
            'holiday_type' => HolidayType::SpecialNonWorking,
            'is_national' => false,
            'work_location_id' => $location->id,
            'year' => 2026,
        ]);

        $controller = new OrganizationController;
        $inertiaResponse = $controller->holidaysIndex();

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $holidays = $props['holidays']->collection;
        $holidayArray = $holidays->keyBy('name');

        // Check scope labels
        expect($holidayArray['National Holiday']->toArray(request())['scope_label'])
            ->toBe('National');
        expect($holidayArray['Manila Day']->toArray(request())['scope_label'])
            ->toBe('Manila Office');
    });
});

describe('Copy to Next Year Button Functionality', function () {
    it('copies holidays to next year via controller method', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayPage($tenant);

        $hrManager = createTenantUserForHolidayPage($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $currentYear = now()->year;

        // Create holidays for current year
        Holiday::factory()->create([
            'name' => 'New Year',
            'date' => "{$currentYear}-01-01",
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear,
        ]);

        Holiday::factory()->create([
            'name' => 'Christmas',
            'date' => "{$currentYear}-12-25",
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => $currentYear,
        ]);

        $targetYear = $currentYear + 1;

        // Create validated request
        $request = createCopyHolidaysRequestForPage([
            'target_year' => $targetYear,
        ], $hrManager);

        // Call the controller method directly
        $controller = new HolidayController;
        $response = $controller->copyToYear($request);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['copied_count'])->toBe(2);
        expect($data['target_year'])->toBe($targetYear);
        expect($data['message'])->toContain('Successfully copied');

        // Verify holidays were created for next year by checking year column only
        $newYearHoliday = Holiday::query()
            ->where('name', 'New Year')
            ->where('year', $targetYear)
            ->first();

        expect($newYearHoliday)->not->toBeNull();
        expect($newYearHoliday->date->format('Y-m-d'))->toBe("{$targetYear}-01-01");

        $christmasHoliday = Holiday::query()
            ->where('name', 'Christmas')
            ->where('year', $targetYear)
            ->first();

        expect($christmasHoliday)->not->toBeNull();
        expect($christmasHoliday->date->format('Y-m-d'))->toBe("{$targetYear}-12-25");
    });
});

describe('Work Locations for Form Modal', function () {
    it('passes active work locations to the holidays page', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayPage($tenant);

        $admin = createTenantUserForHolidayPage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create work locations
        WorkLocation::factory()->create([
            'name' => 'Manila Office',
            'code' => 'MNL',
            'status' => 'active',
        ]);

        WorkLocation::factory()->create([
            'name' => 'Closed Office',
            'code' => 'CLO',
            'status' => 'inactive',
        ]);

        $controller = new OrganizationController;
        $inertiaResponse = $controller->holidaysIndex();

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        // Only active locations should be passed
        $workLocations = $props['workLocations']->collection;
        expect($workLocations)->toHaveCount(1);
        expect($workLocations->first()->name)->toBe('Manila Office');
    });
});

describe('Holiday Data Ordering', function () {
    it('orders holidays by date ascending', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForHolidayPage($tenant);

        $admin = createTenantUserForHolidayPage($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create holidays out of order
        Holiday::factory()->create([
            'name' => 'Christmas Day',
            'date' => '2026-12-25',
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => 2026,
        ]);

        Holiday::factory()->create([
            'name' => 'New Year',
            'date' => '2026-01-01',
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => 2026,
        ]);

        Holiday::factory()->create([
            'name' => 'Independence Day',
            'date' => '2026-06-12',
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
            'year' => 2026,
        ]);

        $controller = new OrganizationController;
        $inertiaResponse = $controller->holidaysIndex();

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $holidays = $props['holidays']->collection;
        $holidayNames = $holidays->pluck('name')->toArray();

        // Should be ordered by date: New Year (Jan 1), Independence (Jun 12), Christmas (Dec 25)
        expect($holidayNames)->toBe(['New Year', 'Independence Day', 'Christmas Day']);
    });
});
