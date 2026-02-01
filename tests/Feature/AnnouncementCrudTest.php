<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\AnnouncementPageController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function bindAnnouncementTestTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createAnnouncementTestUser(Tenant $tenant, TenantUserRole $role): User
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

it('lists announcements for authorized users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindAnnouncementTestTenantContext($tenant);
    $user = createAnnouncementTestUser($tenant, TenantUserRole::Admin);
    Gate::define('can-manage-organization', fn () => true);

    Announcement::factory()->count(3)->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    $this->actingAs($user);

    $controller = new AnnouncementController;
    $request = Request::create('/api/announcements', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    expect($response->count())->toBe(3);
});

it('searches announcements by title', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindAnnouncementTestTenantContext($tenant);
    $user = createAnnouncementTestUser($tenant, TenantUserRole::Admin);
    Gate::define('can-manage-organization', fn () => true);

    Announcement::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Important Update', 'created_by' => $user->id]);
    Announcement::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Other News', 'created_by' => $user->id]);

    $this->actingAs($user);

    $controller = new AnnouncementController;
    $request = Request::create('/api/announcements', 'GET', ['search' => 'Important']);
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    expect($response->count())->toBe(1);
});

it('creates an announcement', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindAnnouncementTestTenantContext($tenant);
    $user = createAnnouncementTestUser($tenant, TenantUserRole::Admin);
    Gate::define('can-manage-organization', fn () => true);

    $this->actingAs($user);

    $data = [
        'title' => 'New Announcement',
        'body' => 'This is the body.',
        'published_at' => now()->toDateTimeString(),
        'is_pinned' => true,
    ];

    $request = StoreAnnouncementRequest::create('/api/announcements', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->validateResolved();

    $controller = new AnnouncementController;
    $response = $controller->store($request);

    expect($response->getStatusCode())->toBe(201);

    $this->assertDatabaseHas('announcements', [
        'title' => 'New Announcement',
        'created_by' => $user->id,
        'tenant_id' => $tenant->id,
    ]);
});

it('validates required fields on create', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindAnnouncementTestTenantContext($tenant);
    $user = createAnnouncementTestUser($tenant, TenantUserRole::Admin);
    Gate::define('can-manage-organization', fn () => true);

    $this->actingAs($user);

    $request = StoreAnnouncementRequest::create('/api/announcements', 'POST', []);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = validator($request->all(), (new StoreAnnouncementRequest)->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('title'))->toBeTrue();
    expect($validator->errors()->has('body'))->toBeTrue();
});

it('updates an announcement', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindAnnouncementTestTenantContext($tenant);
    $user = createAnnouncementTestUser($tenant, TenantUserRole::Admin);
    Gate::define('can-manage-organization', fn () => true);

    $announcement = Announcement::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    $this->actingAs($user);

    $data = [
        'title' => 'Updated Title',
        'body' => 'Updated body.',
    ];

    $request = UpdateAnnouncementRequest::create("/api/announcements/{$announcement->id}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->validateResolved();

    $controller = new AnnouncementController;
    $response = $controller->update($request, 'acme', $announcement);

    expect($response->resource->title)->toBe('Updated Title');
});

it('deletes an announcement', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindAnnouncementTestTenantContext($tenant);
    $user = createAnnouncementTestUser($tenant, TenantUserRole::Admin);
    Gate::define('can-manage-organization', fn () => true);

    $announcement = Announcement::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    $this->actingAs($user);

    $controller = new AnnouncementController;
    $controller->destroy('acme', $announcement);

    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});

it('denies access to unauthorized users for listing', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindAnnouncementTestTenantContext($tenant);
    $user = createAnnouncementTestUser($tenant, TenantUserRole::Employee);
    Gate::define('can-manage-organization', fn () => false);

    $this->actingAs($user);

    $controller = new AnnouncementController;
    $request = Request::create('/api/announcements', 'GET');
    $request->setUserResolver(fn () => $user);

    expect(fn () => $controller->index($request))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('renders the admin announcements page', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindAnnouncementTestTenantContext($tenant);
    $user = createAnnouncementTestUser($tenant, TenantUserRole::Admin);
    Gate::define('can-manage-organization', fn () => true);

    $this->actingAs($user);

    $controller = new AnnouncementPageController;
    $response = $controller(request());

    expect($response)->toBeInstanceOf(\Inertia\Response::class);
});
