<?php

use App\Enums\HolidayType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Requests\CopyHolidaysRequest;
use App\Models\Holiday;
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
function bindTenantContextForCopyHolidays(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForCopyHolidays(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated copy holidays request.
 */
function createCopyHolidaysRequest(array $data, User $user): CopyHolidaysRequest
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

describe('Copy Holidays to Next Year Feature', function () {
    it('successfully copies holidays from current year to target year', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCopyHolidays($tenant);

        $hrManager = createTenantUserForCopyHolidays($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $currentYear = now()->year;
        $targetYear = $currentYear + 1;

        // Create holidays for current year
        Holiday::factory()->create([
            'name' => 'New Year\'s Day',
            'date' => "{$currentYear}-01-01",
            'year' => $currentYear,
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
        ]);

        Holiday::factory()->create([
            'name' => 'Independence Day',
            'date' => "{$currentYear}-06-12",
            'year' => $currentYear,
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
        ]);

        Holiday::factory()->create([
            'name' => 'Christmas Day',
            'date' => "{$currentYear}-12-25",
            'year' => $currentYear,
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
        ]);

        $controller = new HolidayController;
        $request = createCopyHolidaysRequest(['target_year' => $targetYear], $hrManager);
        $response = $controller->copyToYear($request);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['copied_count'])->toBe(3);
        expect($data['target_year'])->toBe($targetYear);
        expect($data['holidays'])->toHaveCount(3);

        // Verify holidays were created in target year
        $copiedHolidays = Holiday::forYear($targetYear)->get();
        expect($copiedHolidays)->toHaveCount(3);
    });

    it('adjusts dates correctly when copying (year increment maintains month/day)', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCopyHolidays($tenant);

        $hrManager = createTenantUserForCopyHolidays($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $currentYear = now()->year;
        $targetYear = $currentYear + 1;

        // Create a holiday with a specific date
        Holiday::factory()->create([
            'name' => 'Test Holiday',
            'date' => "{$currentYear}-06-15",
            'year' => $currentYear,
            'holiday_type' => HolidayType::SpecialNonWorking,
            'is_national' => true,
            'description' => 'Test holiday description',
        ]);

        $controller = new HolidayController;
        $request = createCopyHolidaysRequest(['target_year' => $targetYear], $hrManager);
        $response = $controller->copyToYear($request);

        $data = json_decode($response->getContent(), true);
        expect($data['copied_count'])->toBe(1);

        // Verify the copied holiday has the correct date
        $copiedHoliday = Holiday::forYear($targetYear)->first();
        expect($copiedHoliday->date->format('Y-m-d'))->toBe("{$targetYear}-06-15");
        expect($copiedHoliday->year)->toBe($targetYear);
        expect($copiedHoliday->name)->toBe('Test Holiday');
        expect($copiedHoliday->holiday_type)->toBe(HolidayType::SpecialNonWorking);
        expect($copiedHoliday->description)->toBe('Test holiday description');
    });

    it('returns list of copied holidays for review', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForCopyHolidays($tenant);

        $hrManager = createTenantUserForCopyHolidays($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $currentYear = now()->year;
        $targetYear = $currentYear + 1;

        // Create holidays for current year
        Holiday::factory()->create([
            'name' => 'Labor Day',
            'date' => "{$currentYear}-05-01",
            'year' => $currentYear,
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
        ]);

        Holiday::factory()->create([
            'name' => 'Bonifacio Day',
            'date' => "{$currentYear}-11-30",
            'year' => $currentYear,
            'holiday_type' => HolidayType::Regular,
            'is_national' => true,
        ]);

        $controller = new HolidayController;
        $request = createCopyHolidaysRequest(['target_year' => $targetYear], $hrManager);
        $response = $controller->copyToYear($request);

        $data = json_decode($response->getContent(), true);

        // Verify response structure includes holidays for review
        expect($data)->toHaveKey('holidays');
        expect($data)->toHaveKey('copied_count');
        expect($data)->toHaveKey('target_year');
        expect($data)->toHaveKey('message');

        // Verify each holiday in the response has required fields for review
        foreach ($data['holidays'] as $holiday) {
            expect($holiday)->toHaveKey('id');
            expect($holiday)->toHaveKey('name');
            expect($holiday)->toHaveKey('date');
            expect($holiday)->toHaveKey('holiday_type');
            expect($holiday)->toHaveKey('holiday_type_label');
            expect($holiday)->toHaveKey('is_national');
            expect($holiday)->toHaveKey('year');
        }
    });

    it('validates that target_year parameter is required', function () {
        $rules = (new CopyHolidaysRequest)->rules();

        // Test missing target_year
        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('target_year'))->toBeTrue();

        // Test with valid target_year
        $currentYear = now()->year;
        $validValidator = Validator::make(['target_year' => $currentYear + 1], $rules);
        expect($validValidator->fails())->toBeFalse();
    });
});
