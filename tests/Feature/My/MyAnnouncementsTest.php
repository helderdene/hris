<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\My\AnnouncementController as MyAnnouncementController;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function bindMyAnnouncementTestTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createMyAnnouncementTestUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('shows only published announcements to employees', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindMyAnnouncementTestTenantContext($tenant);
    $user = createMyAnnouncementTestUser($tenant, TenantUserRole::Employee);

    // Published
    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'published_at' => now()->subDay(),
        'created_by' => $user->id,
    ]);

    // Unpublished (draft)
    Announcement::factory()->unpublished()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
    ]);

    // Expired
    Announcement::factory()->expired()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    $controller = new MyAnnouncementController;
    $response = $controller();

    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);

    expect($props['announcements']->count())->toBe(1);
});

it('employees cannot access admin announcement endpoints', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindMyAnnouncementTestTenantContext($tenant);
    $user = createMyAnnouncementTestUser($tenant, TenantUserRole::Employee);
    Gate::define('can-manage-organization', fn () => false);

    $this->actingAs($user);

    $controller = new AnnouncementController;
    $request = Request::create('/api/announcements', 'GET');
    $request->setUserResolver(fn () => $user);

    expect(fn () => $controller->index($request))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});
