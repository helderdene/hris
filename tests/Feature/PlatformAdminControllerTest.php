<?php

use App\Enums\Module;
use App\Models\Plan;
use App\Models\PlanModule;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Models\User;
use App\Services\Billing\PayMongoSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function platformUrl(string $path): string
{
    $mainDomain = config('app.main_domain', 'kasamahr.test');

    return "http://{$mainDomain}{$path}";
}

function createSuperAdmin(): User
{
    return User::factory()->create(['is_super_admin' => true]);
}

function createRegularUser(): User
{
    return User::factory()->create(['is_super_admin' => false]);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    $this->withoutVite();
});

// --- Access Control ---

it('super admin can access admin dashboard', function () {
    $user = createSuperAdmin();

    $response = $this->actingAs($user)->get(platformUrl('/admin'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
});

it('non-super-admin gets 403 on admin dashboard', function () {
    $user = createRegularUser();

    $response = $this->actingAs($user)->get(platformUrl('/admin'));

    $response->assertForbidden();
});

it('unauthenticated users are redirected from admin', function () {
    $response = $this->get(platformUrl('/admin'));

    $response->assertRedirect();
});

// --- Dashboard ---

it('dashboard shows correct stats', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->professional()->create();
    $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);

    // Create tenants with different states
    // Active subscription tenant - no trial so it only counts as subscribed
    $activeTenant = Tenant::factory()->withPlan($plan)->create(['trial_ends_at' => null]);
    Subscription::factory()->active()->create([
        'tenant_id' => $activeTenant->id,
        'plan_price_id' => $price->id,
        'name' => 'default',
    ]);

    Tenant::factory()->withPlan($plan)->withTrial(14)->create();
    Tenant::factory()->withPlan($plan)->withExpiredTrial()->create();

    $response = $this->actingAs($user)->get(platformUrl('/admin'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->has('stats')
        ->where('stats.total_tenants', 3)
        ->where('stats.active_subscriptions', 1)
        ->where('stats.active_trials', 1)
        ->where('stats.expired_trials', 1)
    );
});

// --- Tenants List ---

it('renders tenants list with pagination', function () {
    $user = createSuperAdmin();
    Tenant::factory()->count(3)->create();

    $response = $this->actingAs($user)->get(platformUrl('/admin/tenants'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Tenants/Index')
        ->has('tenants.data', 3)
    );
});

it('filters tenants by search', function () {
    $user = createSuperAdmin();
    Tenant::factory()->create(['name' => 'Acme Corp']);
    Tenant::factory()->create(['name' => 'Beta Inc']);

    $response = $this->actingAs($user)->get(platformUrl('/admin/tenants?search=Acme'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('tenants.data', 1)
    );
});

it('filters tenants by plan', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->professional()->create();
    Tenant::factory()->withPlan($plan)->create();
    Tenant::factory()->create();

    $response = $this->actingAs($user)->get(platformUrl("/admin/tenants?plan_id={$plan->id}"));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('tenants.data', 1)
    );
});

it('filters tenants by status trial', function () {
    $user = createSuperAdmin();
    Tenant::factory()->withTrial(14)->create();
    Tenant::factory()->create(['trial_ends_at' => null]);

    $response = $this->actingAs($user)->get(platformUrl('/admin/tenants?status=trial'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('tenants.data', 1)
    );
});

it('sorts tenants by name', function () {
    $user = createSuperAdmin();
    Tenant::factory()->create(['name' => 'Zebra Corp']);
    Tenant::factory()->create(['name' => 'Alpha Corp']);

    $response = $this->actingAs($user)->get(platformUrl('/admin/tenants?sort=name&direction=asc'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('tenants.data.0.name', 'Alpha Corp')
    );
});

// --- Tenant Show ---

it('renders tenant detail page', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();

    $response = $this->actingAs($user)->get(platformUrl("/admin/tenants/{$tenant->id}"));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Tenants/Show')
        ->has('tenant')
        ->has('usage')
        ->has('adminUsers')
        ->has('subscriptionHistory')
        ->has('plans')
    );
});

// --- Extend Trial ---

it('extends trial for a tenant', function () {
    $user = createSuperAdmin();
    $tenant = Tenant::factory()->withExpiredTrial()->create();

    $response = $this->actingAs($user)->post(platformUrl("/admin/tenants/{$tenant->id}/extend-trial"), [
        'days' => 30,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $tenant->refresh();
    expect($tenant->trial_ends_at)->not->toBeNull();
    expect($tenant->trial_ends_at->isFuture())->toBeTrue();
    expect($tenant->trial_expired_notified_at)->toBeNull();
});

it('assigns professional plan when extending trial for tenant with no plan', function () {
    $user = createSuperAdmin();
    $professionalPlan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->create(['plan_id' => null]);

    $response = $this->actingAs($user)->post(platformUrl("/admin/tenants/{$tenant->id}/extend-trial"), [
        'days' => 14,
    ]);

    $response->assertRedirect();
    $tenant->refresh();
    expect($tenant->plan_id)->toBe($professionalPlan->id);
});

it('validates trial days must be between 1 and 90', function () {
    $user = createSuperAdmin();
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($user)->post(platformUrl("/admin/tenants/{$tenant->id}/extend-trial"), [
        'days' => 100,
    ]);

    $response->assertSessionHasErrors('days');
});

it('clears trial_expired_notified_at when extending trial', function () {
    $user = createSuperAdmin();
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->subDay(),
        'trial_expired_notified_at' => now()->subHour(),
    ]);

    $this->actingAs($user)->post(platformUrl("/admin/tenants/{$tenant->id}/extend-trial"), [
        'days' => 14,
    ]);

    $tenant->refresh();
    expect($tenant->trial_expired_notified_at)->toBeNull();
});

// --- Assign Plan ---

it('assigns a plan to a tenant', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->professional()->create();
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($user)->post(platformUrl("/admin/tenants/{$tenant->id}/assign-plan"), [
        'plan_id' => $plan->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($tenant->fresh()->plan_id)->toBe($plan->id);
});

it('validates plan exists when assigning', function () {
    $user = createSuperAdmin();
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($user)->post(platformUrl("/admin/tenants/{$tenant->id}/assign-plan"), [
        'plan_id' => 99999,
    ]);

    $response->assertSessionHasErrors('plan_id');
});

// --- Cancel Subscription ---

it('cancels an active subscription', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->professional()->create();
    $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);
    $tenant = Tenant::factory()->withPlan($plan)->create();
    Subscription::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'plan_price_id' => $price->id,
        'name' => 'default',
    ]);

    $mock = Mockery::mock(PayMongoSubscriptionService::class);
    $mock->shouldReceive('cancel')->once();
    app()->instance(PayMongoSubscriptionService::class, $mock);

    $response = $this->actingAs($user)->post(platformUrl("/admin/tenants/{$tenant->id}/cancel-subscription"));

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

it('returns error when no active subscription to cancel', function () {
    $user = createSuperAdmin();
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($user)->post(platformUrl("/admin/tenants/{$tenant->id}/cancel-subscription"));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

// --- Plans ---

it('renders plans list with tenant counts', function () {
    $user = createSuperAdmin();
    Plan::factory()->starter()->create();
    Plan::factory()->professional()->create();

    $response = $this->actingAs($user)->get(platformUrl('/admin/plans'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Plans/Index')
        ->has('plans', 2)
    );
});

it('toggles plan active status', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->professional()->create(['is_active' => true]);

    $response = $this->actingAs($user)->post(platformUrl("/admin/plans/{$plan->id}/toggle"));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($plan->fresh()->is_active)->toBeFalse();
});

// --- Custom Plan CRUD ---

it('renders custom plan create form', function () {
    $user = createSuperAdmin();

    $response = $this->actingAs($user)->get(platformUrl('/admin/plans/custom/create'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Plans/Create')
        ->has('modules')
        ->has('tenants')
    );
});

it('creates a custom plan with modules and prices', function () {
    $user = createSuperAdmin();

    $response = $this->actingAs($user)->post(platformUrl('/admin/plans/custom'), [
        'name' => 'Custom Plan Alpha',
        'description' => 'A custom test plan',
        'modules' => [Module::HrManagement->value, Module::Payroll->value],
        'limits' => [
            'max_employees' => 100,
            'max_admin_users' => 10,
            'max_departments' => 20,
            'max_biometric_devices' => 10,
            'storage_gb' => 50,
            'api_access' => true,
        ],
        'prices' => [
            ['billing_interval' => 'monthly', 'price_per_unit' => 299],
        ],
    ]);

    $response->assertRedirect(route('admin.plans'));
    $response->assertSessionHas('success');

    $plan = Plan::where('name', 'Custom Plan Alpha')->first();
    expect($plan)->not->toBeNull();
    expect($plan->is_custom)->toBeTrue();
    expect($plan->modules)->toHaveCount(2);
    expect($plan->prices)->toHaveCount(1);
});

it('edits a custom plan', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->create([
        'is_custom' => true,
        'name' => 'Old Custom',
    ]);
    PlanModule::create(['plan_id' => $plan->id, 'module' => Module::HrManagement->value]);
    PlanPrice::factory()->create(['plan_id' => $plan->id]);

    $response = $this->actingAs($user)->get(platformUrl("/admin/plans/custom/{$plan->id}/edit"));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Plans/Edit')
        ->has('plan')
        ->has('modules')
    );
});

it('updates a custom plan', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->create([
        'is_custom' => true,
        'name' => 'Old Custom',
        'limits' => ['max_employees' => 50, 'max_admin_users' => 5, 'max_departments' => 10, 'max_biometric_devices' => 5, 'storage_gb' => 10, 'api_access' => false],
    ]);
    PlanModule::create(['plan_id' => $plan->id, 'module' => Module::HrManagement->value]);
    PlanPrice::factory()->create(['plan_id' => $plan->id]);

    $response = $this->actingAs($user)->put(platformUrl("/admin/plans/custom/{$plan->id}"), [
        'name' => 'Updated Custom',
        'modules' => [Module::HrManagement->value, Module::Payroll->value, Module::LeaveManagement->value],
        'limits' => [
            'max_employees' => 200,
            'max_admin_users' => 20,
            'max_departments' => 30,
            'max_biometric_devices' => 15,
            'storage_gb' => 100,
            'api_access' => true,
        ],
        'prices' => [
            ['billing_interval' => 'monthly', 'price_per_unit' => 499],
            ['billing_interval' => 'yearly', 'price_per_unit' => 399],
        ],
    ]);

    $response->assertRedirect(route('admin.plans'));
    $plan->refresh();
    expect($plan->name)->toBe('Updated Custom');
    expect($plan->modules()->count())->toBe(3);
    expect($plan->prices()->count())->toBe(2);
});

it('prevents editing non-custom plans', function () {
    $user = createSuperAdmin();
    $plan = Plan::factory()->professional()->create(['is_custom' => false]);

    $response = $this->actingAs($user)->get(platformUrl("/admin/plans/custom/{$plan->id}/edit"));

    $response->assertForbidden();
});

// --- Impersonate ---

it('creates redirect token and redirects to tenant subdomain', function () {
    $user = createSuperAdmin();
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($user)->get(platformUrl("/admin/tenants/{$tenant->id}/impersonate"));

    // Inertia::location returns 302 for non-Inertia requests, 409 for Inertia
    $response->assertRedirect();
    expect(TenantRedirectToken::where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists())->toBeTrue();
});
