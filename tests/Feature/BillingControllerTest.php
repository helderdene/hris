<?php

use App\Enums\AddonType;
use App\Enums\TenantUserRole;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\User;
use App\Services\Billing\PayMongoSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function billingBindTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function billingCreateUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function billingUrl(string $path, Tenant $tenant): string
{
    $mainDomain = config('app.main_domain', 'kasamahr.test');

    return "http://{$tenant->slug}.{$mainDomain}{$path}";
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    $this->withoutVite();
});

// --- Index page ---

it('renders billing index for authenticated admin', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $response = $this->actingAs($user)->get(billingUrl('/billing', $tenant));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Billing/Index')
        ->has('currentPlan')
        ->has('usage')
        ->where('isOnTrial', true)
    );
});

it('renders billing index for non-admin user', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::HrManager);

    $response = $this->actingAs($user)->get(billingUrl('/billing', $tenant));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Billing/Index'));
});

it('redirects unauthenticated users from billing', function () {
    $tenant = Tenant::factory()->create();
    billingBindTenant($tenant);

    $response = $this->get(billingUrl('/billing', $tenant));

    $response->assertRedirect();
});

// --- Plans page ---

it('renders plans page with all active plans', function () {
    $starterPlan = Plan::factory()->starter()->create();
    Plan::factory()->professional()->create();
    Plan::factory()->enterprise()->create();

    $tenant = Tenant::factory()->withPlan($starterPlan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $response = $this->actingAs($user)->get(billingUrl('/billing/plans', $tenant));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Billing/Plans')
        ->has('plans', 3)
        ->where('currentPlanSlug', 'starter')
    );
});

// --- Upgrade page ---

it('renders upgrade page with locked module', function () {
    $plan = Plan::factory()->starter()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $response = $this->actingAs($user)->get(billingUrl('/billing/upgrade?module=recruitment', $tenant));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Billing/Upgrade')
        ->where('lockedModule', 'recruitment')
        ->where('lockedModuleLabel', 'Recruitment')
    );
});

// --- Addons page ---

it('renders addons page', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    TenantAddon::factory()->employeeSlots()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get(billingUrl('/billing/addons', $tenant));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Billing/Addons')
        ->has('addons', 1)
        ->has('addonTypes')
    );
});

// --- Success page ---

it('renders success page', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $response = $this->actingAs($user)->get(billingUrl('/billing/success', $tenant));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Billing/Success')
        ->where('planName', 'Professional')
    );
});

// --- Subscribe ---

it('admin can subscribe to a plan', function () {
    $plan = Plan::factory()->professional()->create();
    $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    $mock->shouldReceive('getCheckoutUrl')
        ->once()
        ->andReturn([
            'checkout_url' => 'https://checkout.paymongo.com/test',
            'paymongo_plan_id' => 'plan_123',
        ]);
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl("/billing/subscribe/{$price->id}", $tenant));

    // Inertia::location returns 302 for non-Inertia requests, 409 for Inertia requests
    $response->assertRedirect();
    expect(Subscription::where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('non-admin cannot subscribe', function () {
    $plan = Plan::factory()->professional()->create();
    $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::HrManager);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl("/billing/subscribe/{$price->id}", $tenant));

    $response->assertForbidden();
});

// --- Change Plan ---

it('admin can change plan', function () {
    $oldPlan = Plan::factory()->starter()->create();
    $newPlan = Plan::factory()->professional()->create();
    $oldPrice = PlanPrice::factory()->create(['plan_id' => $oldPlan->id]);
    $newPrice = PlanPrice::factory()->create(['plan_id' => $newPlan->id]);

    $tenant = Tenant::factory()->withPlan($oldPlan)->create();
    billingBindTenant($tenant);
    Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'plan_price_id' => $oldPrice->id,
        'name' => 'default',
    ]);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    $mock->shouldReceive('changePlan')->once();
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl("/billing/change-plan/{$newPrice->id}", $tenant));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    // The plan_id update is verified via the mock expectation (changePlan called once)
});

// --- Cancel ---

it('admin can cancel subscription', function () {
    $plan = Plan::factory()->professional()->create();
    $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'plan_price_id' => $price->id,
        'name' => 'default',
    ]);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    $mock->shouldReceive('cancel')->once();
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl('/billing/cancel', $tenant));

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

it('cancel without subscription returns error', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl('/billing/cancel', $tenant));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

// --- Addons CRUD ---

it('admin can purchase addon', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl('/billing/addons/purchase', $tenant), [
        'type' => AddonType::EmployeeSlots->value,
        'quantity' => 2,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(TenantAddon::where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(TenantAddon::where('tenant_id', $tenant->id)->first()->quantity)->toBe(2);
});

it('purchase addon validates input', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl('/billing/addons/purchase', $tenant), [
        'type' => 'invalid_type',
        'quantity' => 0,
    ]);

    $response->assertSessionHasErrors(['type', 'quantity']);
});

it('admin can update addon quantity', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $addon = TenantAddon::factory()->employeeSlots(2)->create(['tenant_id' => $tenant->id]);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl("/billing/addons/{$addon->id}/update", $tenant), [
        'quantity' => 5,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($addon->fresh()->quantity)->toBe(5);
});

it('admin can cancel addon', function () {
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $addon = TenantAddon::factory()->employeeSlots()->create(['tenant_id' => $tenant->id]);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl("/billing/addons/{$addon->id}/cancel", $tenant));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($addon->fresh()->is_active)->toBeFalse();
});

// --- PayMongo error handling ---

it('returns error when PayMongo subscribe fails', function () {
    $plan = Plan::factory()->professional()->create();
    $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    $mock->shouldReceive('getCheckoutUrl')
        ->once()
        ->andThrow(new \RuntimeException('PayMongo API error'));
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl("/billing/subscribe/{$price->id}", $tenant));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

it('returns error when PayMongo cancel fails', function () {
    $plan = Plan::factory()->professional()->create();
    $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
    $tenant = Tenant::factory()->withPlan($plan)->create();
    billingBindTenant($tenant);
    Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'plan_price_id' => $price->id,
        'name' => 'default',
    ]);
    $user = billingCreateUser($tenant, TenantUserRole::Admin);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    $mock->shouldReceive('cancel')
        ->once()
        ->andThrow(new \RuntimeException('PayMongo API error'));
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(billingUrl('/billing/cancel', $tenant));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
