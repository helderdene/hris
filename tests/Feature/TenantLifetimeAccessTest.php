<?php

use App\Enums\TenantUserRole;
use App\Http\Middleware\EnsureModuleAccess;
use App\Jobs\UpdateBillingQuantityJob;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TrialExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    $this->withoutVite();
});

function lifetimeAdminUrl(string $path): string
{
    return 'http://kasamahr.test'.$path;
}

function attachTenantAdmin(Tenant $tenant): User
{
    $admin = User::factory()->create();
    $admin->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $admin;
}

it('gives a lifetime tenant access with no trial and no subscription', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => null, 'is_lifetime' => true]);

    expect($tenant->hasActiveAccess())->toBeTrue()
        ->and($tenant->onTrial())->toBeFalse()
        ->and($tenant->trialExpired())->toBeFalse();
});

it('does not treat a lifetime tenant with an old trial date as on trial or expired', function (string $state) {
    $tenant = Tenant::factory()->{$state}()->create(['is_lifetime' => true]);

    expect($tenant->hasActiveAccess())->toBeTrue()
        ->and($tenant->onTrial())->toBeFalse()
        ->and($tenant->trialExpired())->toBeFalse();
})->with(['withTrial', 'withExpiredTrial']);

it('leaves tenants without the flag on their trial rules', function () {
    expect(Tenant::factory()->withTrial()->create()->onTrial())->toBeTrue()
        ->and(Tenant::factory()->withExpiredTrial()->create()->hasActiveAccess())->toBeFalse();
});

it('limits a lifetime tenant to the modules in its plan', function () {
    $plan = Plan::factory()->starter()->create();
    $tenant = Tenant::factory()->withPlan($plan)->withTrial()->create(['is_lifetime' => true]);
    $user = attachTenantAdmin($tenant);
    app()->instance('tenant', $tenant);

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = (new EnsureModuleAccess)->handle($request, fn () => new Response('OK', 200), 'recruitment');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('/billing/upgrade');
});

it('does not send trial-expired notifications to a lifetime tenant', function () {
    Notification::fake();
    $plan = Plan::factory()->starter()->create();
    $lifetime = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create(['is_lifetime' => true]);
    $expired = Tenant::factory()->withPlan($plan)->withExpiredTrial()->create();
    $lifetimeAdmin = attachTenantAdmin($lifetime);
    $expiredAdmin = attachTenantAdmin($expired);

    $this->artisan('billing:check-expired-trials')->assertSuccessful();

    Notification::assertNotSentTo($lifetimeAdmin, TrialExpiredNotification::class);
    Notification::assertSentTo($expiredAdmin, TrialExpiredNotification::class);
});

it('keeps a lifetime tenant with no trial date in the billing quantity sync', function () {
    Bus::fake();
    Tenant::factory()->create(['trial_ends_at' => null, 'is_lifetime' => true]);
    Tenant::factory()->create(['trial_ends_at' => null]);

    $this->artisan('billing:sync-quantities')->assertSuccessful();

    Bus::assertDispatched(UpdateBillingQuantityJob::class, 1);
});

it('lets a super admin grant and revoke lifetime access without touching the trial date', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $tenant = Tenant::factory()->withTrial()->create();
    $trialEndsAt = $tenant->trial_ends_at->toDateTimeString();

    $this->actingAs($admin)
        ->post(lifetimeAdminUrl("/admin/tenants/{$tenant->id}/toggle-lifetime"))
        ->assertRedirect()
        ->assertSessionHas('success', 'Lifetime access granted.');

    expect($tenant->refresh()->is_lifetime)->toBeTrue()
        ->and($tenant->trial_ends_at->toDateTimeString())->toBe($trialEndsAt);

    $this->actingAs($admin)
        ->post(lifetimeAdminUrl("/admin/tenants/{$tenant->id}/toggle-lifetime"))
        ->assertSessionHas('success', 'Lifetime access revoked.');

    expect($tenant->refresh()->is_lifetime)->toBeFalse()
        ->and($tenant->onTrial())->toBeTrue();
});

it('forbids a regular user from toggling lifetime access', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->post(lifetimeAdminUrl("/admin/tenants/{$tenant->id}/toggle-lifetime"))
        ->assertForbidden();

    expect($tenant->refresh()->is_lifetime)->toBeFalse();
});

it('shows lifetime status in the admin pages and separates it from trials', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $lifetime = Tenant::factory()->withTrial()->create(['is_lifetime' => true]);
    $trial = Tenant::factory()->withTrial()->create();

    $this->actingAs($admin)
        ->get(lifetimeAdminUrl("/admin/tenants/{$lifetime->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tenant.status', 'lifetime')
            ->where('tenant.is_lifetime', true)
            ->where('tenant.is_on_trial', false));

    $this->actingAs($admin)
        ->get(lifetimeAdminUrl('/admin/tenants?status=lifetime'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.data.0.id', $lifetime->id));

    $this->actingAs($admin)
        ->get(lifetimeAdminUrl('/admin/tenants?status=trial'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.data.0.id', $trial->id));

    $this->actingAs($admin)
        ->get(lifetimeAdminUrl('/admin'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.active_trials', 1));
});
