<?php

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeatureGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

function setupTenantWithLimit(int $maxEmployees): Tenant
{
    $plan = Plan::factory()->starter()->create();
    $plan->update([
        'limits' => array_merge($plan->limits, ['max_employees' => $maxEmployees]),
    ]);

    return Tenant::factory()->withPlan($plan)->withTrial(14)->create();
}

function setupAuthForTenant(Tenant $tenant, TenantUserRole $role = TenantUserRole::Admin): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

describe('Employee limit enforcement', function () {
    it('allows creating employee when under limit', function () {
        $tenant = setupTenantWithLimit(5);
        app()->instance('tenant', $tenant);

        Employee::factory()->count(3)->create(['employment_status' => 'active']);

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinEmployeeLimit())->toBeTrue();
    });

    it('blocks creating employee when at limit', function () {
        $tenant = setupTenantWithLimit(3);
        app()->instance('tenant', $tenant);

        Employee::factory()->count(3)->create(['employment_status' => 'active']);

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinEmployeeLimit())->toBeFalse();
    });

    it('blocks creating employee when over limit', function () {
        $tenant = setupTenantWithLimit(2);
        app()->instance('tenant', $tenant);

        Employee::factory()->count(3)->create(['employment_status' => 'active']);

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinEmployeeLimit())->toBeFalse();
    });

    it('allows unlimited employees with -1 limit', function () {
        $plan = Plan::factory()->enterprise()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        app()->instance('tenant', $tenant);

        Employee::factory()->count(10)->create(['employment_status' => 'active']);

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinEmployeeLimit())->toBeTrue();
    });

    it('does not count inactive employees toward limit', function () {
        $tenant = setupTenantWithLimit(3);
        app()->instance('tenant', $tenant);

        Employee::factory()->count(2)->create(['employment_status' => 'active']);
        Employee::factory()->count(5)->create(['employment_status' => 'resigned']);

        $gate = new FeatureGateService($tenant);

        expect($gate->isWithinEmployeeLimit())->toBeTrue();
    });

    it('returns correct effective limit', function () {
        $tenant = setupTenantWithLimit(50);
        app()->instance('tenant', $tenant);

        $gate = new FeatureGateService($tenant);

        expect($gate->getEffectiveLimit('max_employees'))->toBe(50);
    });
});
