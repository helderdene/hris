<?php

/**
 * Tests for Tenant Holiday Settings UI Component
 *
 * Tests the TenantHolidaySettings Vue component functionality including:
 * - Component rendering with current double_holiday_rate value
 * - Rate can be updated via form
 * - Validation prevents invalid rate values
 *
 * These tests verify the API integration layer that the Vue component relies on.
 */

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
function bindTenantForHolidaySettingsUI(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForHolidaySettingsUI(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function createPayrollSettingsRequestForUI(array $data, User $user): UpdatePayrollSettingsRequest
{
    $request = UpdatePayrollSettingsRequest::create(
        '/api/tenant/payroll-settings',
        'PATCH',
        $data
    );
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $rules = (new UpdatePayrollSettingsRequest)->rules();
    $validator = Validator::make($data, $rules);

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('Tenant Holiday Settings UI Component', function () {
    it('returns current double_holiday_rate value via show endpoint', function () {
        // Create a tenant with specific double holiday rate
        $tenant = Tenant::factory()->create([
            'payroll_settings' => [
                'pay_frequency' => 'semi-monthly',
                'cutoff_day' => 15,
                'double_holiday_rate' => 350,
            ],
        ]);
        bindTenantForHolidaySettingsUI($tenant);

        $admin = createTenantUserForHolidaySettingsUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Test the controller show method directly
        $controller = new TenantPayrollSettingsController;
        $response = $controller->show();

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['double_holiday_rate'])->toBe(350);
        expect($data['pay_frequency'])->toBe('semi-monthly');
        expect($data['cutoff_day'])->toBe(15);
    });

    it('allows updating double_holiday_rate via update endpoint', function () {
        $tenant = Tenant::factory()->create([
            'payroll_settings' => [
                'pay_frequency' => 'semi-monthly',
                'cutoff_day' => 15,
                'double_holiday_rate' => 300,
            ],
        ]);
        bindTenantForHolidaySettingsUI($tenant);

        $admin = createTenantUserForHolidaySettingsUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new TenantPayrollSettingsController;

        // Create a validated request to update the double holiday rate
        $updateRequest = createPayrollSettingsRequestForUI([
            'double_holiday_rate' => 400,
        ], $admin);

        $response = $controller->update($updateRequest);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['double_holiday_rate'])->toBe(400);

        // Verify the database was updated
        $tenant->refresh();
        expect($tenant->payroll_settings['double_holiday_rate'])->toBe(400);
    });

    it('validates double_holiday_rate against invalid values', function () {
        $tenant = Tenant::factory()->create([
            'payroll_settings' => [
                'pay_frequency' => 'semi-monthly',
                'cutoff_day' => 15,
                'double_holiday_rate' => 300,
            ],
        ]);
        bindTenantForHolidaySettingsUI($tenant);

        $admin = createTenantUserForHolidaySettingsUI($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Get validation rules
        $rules = (new UpdatePayrollSettingsRequest)->rules();

        // Test rate below minimum (100)
        $validator = Validator::make(['double_holiday_rate' => 50], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('double_holiday_rate'))->toBeTrue();

        // Test rate above maximum (500)
        $validator = Validator::make(['double_holiday_rate' => 600], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('double_holiday_rate'))->toBeTrue();

        // Test rate must be integer
        $validator = Validator::make(['double_holiday_rate' => 'abc'], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('double_holiday_rate'))->toBeTrue();

        // Test valid rate passes
        $validator = Validator::make(['double_holiday_rate' => 350], $rules);
        expect($validator->fails())->toBeFalse();
    });
});
