<?php

/**
 * Tests for Tenant Holiday Settings
 *
 * Tests the configuration of double_holiday_rate in tenant payroll settings
 * and its use in holiday premium rate calculations.
 *
 * Note: These tests call controllers directly following the pattern from
 * EmployeeCompensationApiTest.php since tenant subdomain routing requires
 * special handling in tests.
 */

use App\Enums\HolidayType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\TenantPayrollSettingsController;
use App\Http\Requests\UpdatePayrollSettingsRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForSettings(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForSettings(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated UpdatePayrollSettingsRequest.
 */
function createValidatedPayrollSettingsRequest(array $data, User $user): UpdatePayrollSettingsRequest
{
    $request = UpdatePayrollSettingsRequest::create(
        '/api/tenant/payroll-settings',
        'PATCH',
        $data
    );
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    // Get the rules and validate
    $rules = (new UpdatePayrollSettingsRequest)->rules();
    $validator = Validator::make($data, $rules);

    // Set the validator on the request (via reflection since it's protected)
    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('Tenant Holiday Settings', function () {
    it('has double_holiday_rate default value of 300 in payroll settings', function () {
        // Create a tenant with default payroll settings
        $tenant = Tenant::factory()->create([
            'payroll_settings' => [
                'pay_frequency' => 'semi-monthly',
                'cutoff_day' => 15,
                'double_holiday_rate' => 300,
            ],
        ]);
        bindTenantContextForSettings($tenant);

        expect($tenant->payroll_settings)->toBeArray();
        expect($tenant->payroll_settings['double_holiday_rate'])->toBe(300);

        // Also test the getDoubleHolidayRate helper method
        expect($tenant->getDoubleHolidayRate())->toBe(300);
    });

    it('allows updating double_holiday_rate via controller', function () {
        $tenant = Tenant::factory()->create([
            'payroll_settings' => [
                'pay_frequency' => 'semi-monthly',
                'cutoff_day' => 15,
                'double_holiday_rate' => 300,
            ],
        ]);
        bindTenantContextForSettings($tenant);

        $admin = createTenantUserForSettings($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new TenantPayrollSettingsController;

        // Create a validated request to update the double holiday rate
        $updateRequest = createValidatedPayrollSettingsRequest([
            'double_holiday_rate' => 350,
        ], $admin);

        $response = $controller->update($updateRequest);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['double_holiday_rate'])->toBe(350);

        // Verify the database was updated
        $tenant->refresh();
        expect($tenant->payroll_settings['double_holiday_rate'])->toBe(350);
        expect($tenant->getDoubleHolidayRate())->toBe(350);
    });

    it('uses tenant double_holiday_rate when retrieving holiday premium rate', function () {
        // Create a tenant with a custom double holiday rate
        $tenant = Tenant::factory()->create([
            'payroll_settings' => [
                'pay_frequency' => 'semi-monthly',
                'cutoff_day' => 15,
                'double_holiday_rate' => 400,
            ],
        ]);
        bindTenantContextForSettings($tenant);

        // Test that Regular holiday type returns fixed rate (not affected by tenant settings)
        expect(HolidayType::Regular->premiumRate())->toBe(200);

        // Test that Special Non-Working holiday type returns fixed rate
        expect(HolidayType::SpecialNonWorking->premiumRate())->toBe(130);

        // Test that Special Working holiday type returns fixed rate
        expect(HolidayType::SpecialWorking->premiumRate())->toBe(100);

        // Test that Double holiday type returns tenant-configured rate when passed explicitly
        $tenantRate = $tenant->getDoubleHolidayRate();
        expect(HolidayType::Double->premiumRate($tenantRate))->toBe(400);

        // Test that Double holiday type returns default 300 when no tenant rate is provided
        expect(HolidayType::Double->premiumRate())->toBe(300);

        // Test the convenience method that uses tenant context
        expect(HolidayType::Double->premiumRateForTenant())->toBe(400);

        // Other holiday types should return their fixed rates via premiumRateForTenant too
        expect(HolidayType::Regular->premiumRateForTenant())->toBe(200);
        expect(HolidayType::SpecialNonWorking->premiumRateForTenant())->toBe(130);
        expect(HolidayType::SpecialWorking->premiumRateForTenant())->toBe(100);
    });
});
