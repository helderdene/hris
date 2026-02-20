<?php

use App\Enums\Module;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantUserRole;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\User;
use App\Services\FeatureGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

function createTenantForGating(string $planType): Tenant
{
    $plan = Plan::factory()->{$planType}()->create();

    return Tenant::factory()->withPlan($plan)->create(['trial_ends_at' => null]);
}

function createUserForGating(Tenant $tenant, TenantUserRole $role = TenantUserRole::Admin): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function bindTenantForGating(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Create an authenticated request with a user.
 */
function makeRequestWithUser(User $user): Request
{
    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    return $request;
}

/**
 * A simple next closure for middleware testing.
 */
function passThrough(): Closure
{
    return fn (Request $request) => new Response('OK', 200);
}

// ─────────────────────────────────────────────────────────
// FeatureGateService
// ─────────────────────────────────────────────────────────

describe('FeatureGateService', function () {
    it('returns correct modules for starter tier', function () {
        $tenant = createTenantForGating('starter');
        $gate = new FeatureGateService($tenant);

        expect($gate->hasModule(Module::HrManagement))->toBeTrue();
        expect($gate->hasModule(Module::Payroll))->toBeTrue();
        expect($gate->hasModule(Module::Recruitment))->toBeFalse();
        expect($gate->hasModule(Module::PerformanceManagement))->toBeFalse();
        expect($gate->hasModule(Module::ComplianceTraining))->toBeFalse();
    });

    it('returns correct modules for professional tier', function () {
        $tenant = createTenantForGating('professional');
        $gate = new FeatureGateService($tenant);

        expect($gate->hasModule(Module::HrManagement))->toBeTrue();
        expect($gate->hasModule(Module::Recruitment))->toBeTrue();
        expect($gate->hasModule(Module::PerformanceManagement))->toBeTrue();
        expect($gate->hasModule(Module::TrainingDevelopment))->toBeTrue();
        expect($gate->hasModule(Module::ComplianceTraining))->toBeFalse();
    });

    it('returns correct modules for enterprise tier', function () {
        $tenant = createTenantForGating('enterprise');
        $gate = new FeatureGateService($tenant);

        expect($gate->hasModule(Module::ComplianceTraining))->toBeTrue();
        expect($gate->hasModule(Module::AuditSecurity))->toBeTrue();
        expect($gate->hasModule(Module::CareersPortal))->toBeTrue();
    });

    it('reports active access for active subscription', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $price = \App\Models\PlanPrice::factory()->create(['plan_id' => $plan->id]);
        Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
            'plan_price_id' => $price->id,
        ]);
        $gate = new FeatureGateService($tenant);

        expect($gate->hasActiveAccess())->toBeTrue();
    });

    it('reports active access for valid trial', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        $gate = new FeatureGateService($tenant);

        expect($gate->hasActiveAccess())->toBeTrue();
        expect($gate->isOnTrial())->toBeTrue();
    });

    it('reports no active access for expired trial without subscription', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create();
        $gate = new FeatureGateService($tenant);

        expect($gate->hasActiveAccess())->toBeFalse();
    });

    it('returns correct shareable array', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        $gate = new FeatureGateService($tenant);

        $shared = $gate->toShareableArray();

        expect($shared)->toHaveKeys(['plan', 'status', 'is_on_trial', 'trial_ends_at', 'available_modules']);
        expect($shared['plan'])->toBe('starter');
        expect($shared['is_on_trial'])->toBeTrue();
        expect($shared['trial_ends_at'])->not->toBeNull();
        expect($shared['available_modules'])->toBeArray();
        expect($shared['available_modules'])->toContain('hr_management');
        expect($shared['available_modules'])->not->toContain('recruitment');
    });

    it('returns plan slug and subscription status', function () {
        $plan = Plan::factory()->professional()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $price = \App\Models\PlanPrice::factory()->create(['plan_id' => $plan->id]);
        Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
            'plan_price_id' => $price->id,
        ]);
        $gate = new FeatureGateService($tenant);

        expect($gate->currentPlanSlug())->toBe('professional');
        expect($gate->subscriptionStatus())->toBe(SubscriptionStatus::Active);
    });

    it('getLimit returns plan limit', function () {
        $tenant = createTenantForGating('starter');
        $gate = new FeatureGateService($tenant);

        expect($gate->getLimit('max_employees'))->toBe(50);
        expect($gate->getLimit('max_biometric_devices'))->toBe(2);
    });

    it('getEffectiveLimit includes add-on extra units', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        bindTenantForGating($tenant);

        // Starter: max_employees = 50, add-on adds 10 units per quantity
        TenantAddon::factory()->employeeSlots(1)->create(['tenant_id' => $tenant->id]);

        $gate = new FeatureGateService($tenant);

        expect($gate->getEffectiveLimit('max_employees'))->toBe(60);
    });

    it('getEffectiveLimit returns null when no plan', function () {
        $tenant = Tenant::factory()->create();
        $gate = new FeatureGateService($tenant);

        expect($gate->getEffectiveLimit('max_employees'))->toBeNull();
    });

    it('getEffectiveLimit returns -1 for unlimited', function () {
        $tenant = createTenantForGating('enterprise');
        $gate = new FeatureGateService($tenant);

        expect($gate->getEffectiveLimit('max_employees'))->toBe(-1);
    });
});

// ─────────────────────────────────────────────────────────
// EnsureActiveSubscription middleware (unit tests)
// ─────────────────────────────────────────────────────────

describe('EnsureActiveSubscription middleware', function () {
    it('allows access with active subscription', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $price = \App\Models\PlanPrice::factory()->create(['plan_id' => $plan->id]);
        Subscription::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
            'plan_price_id' => $price->id,
        ]);
        $user = createUserForGating($tenant);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureActiveSubscription;
        $response = $middleware->handle($request, passThrough());

        expect($response->getStatusCode())->toBe(200);
    });

    it('allows access with valid trial', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        $user = createUserForGating($tenant);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureActiveSubscription;
        $response = $middleware->handle($request, passThrough());

        expect($response->getStatusCode())->toBe(200);
    });

    it('redirects to billing when no active access', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create();
        $user = createUserForGating($tenant);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureActiveSubscription;
        $response = $middleware->handle($request, passThrough());

        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/billing');
    });

    it('allows Super Admin to bypass subscription check', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create();
        $user = User::factory()->create(['is_super_admin' => true]);
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureActiveSubscription;
        $response = $middleware->handle($request, passThrough());

        expect($response->getStatusCode())->toBe(200);
    });
});

// ─────────────────────────────────────────────────────────
// EnsureModuleAccess middleware (unit tests)
// ─────────────────────────────────────────────────────────

describe('EnsureModuleAccess middleware', function () {
    it('allows when tenant has required module', function () {
        $plan = Plan::factory()->professional()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = createUserForGating($tenant);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureModuleAccess;
        $response = $middleware->handle($request, passThrough(), 'recruitment');

        expect($response->getStatusCode())->toBe(200);
    });

    it('redirects when tenant lacks module', function () {
        $tenant = createTenantForGating('starter');
        $user = createUserForGating($tenant);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureModuleAccess;
        $response = $middleware->handle($request, passThrough(), 'recruitment');

        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/billing/upgrade');
    });

    it('allows Super Admin to bypass module check', function () {
        $tenant = createTenantForGating('starter');
        $user = User::factory()->create(['is_super_admin' => true]);
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Admin->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureModuleAccess;
        $response = $middleware->handle($request, passThrough(), 'recruitment');

        expect($response->getStatusCode())->toBe(200);
    });

    it('allows when any of multiple modules matches', function () {
        $plan = Plan::factory()->professional()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = createUserForGating($tenant);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureModuleAccess;
        $response = $middleware->handle($request, passThrough(), 'compliance_training', 'recruitment');

        expect($response->getStatusCode())->toBe(200);
    });

    it('redirects when none of multiple modules match', function () {
        $tenant = createTenantForGating('starter');
        $user = createUserForGating($tenant);
        bindTenantForGating($tenant);

        $request = makeRequestWithUser($user);
        $middleware = new EnsureModuleAccess;
        $response = $middleware->handle($request, passThrough(), 'compliance_training', 'recruitment');

        expect($response->getStatusCode())->toBe(302);
    });
});
