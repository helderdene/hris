<?php

/**
 * Tests for the Audit Log API
 *
 * These tests verify the audit log API endpoints including
 * authorization, filtering, and response format.
 */

use App\Enums\AuditAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Settings\AuditLogPageController;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForAuditLog(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForAuditLog(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to extract Inertia response data without triggering full HTTP response.
 */
function getInertiaResponseDataForAuditLog(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

/**
 * Helper to get the Inertia component name.
 */
function getInertiaComponentForAuditLog(\Inertia\Response $response): string
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('component');
    $property->setAccessible(true);

    return $property->getValue($response);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Audit Log Page Authorization', function () {
    it('allows admin to access audit logs page', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $admin = createTenantUserForAuditLog($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        expect(Gate::allows('can-view-audit-logs'))->toBeTrue();

        $controller = new AuditLogPageController;
        $request = Request::create('/settings/audit-logs', 'GET');
        app()->instance('request', $request);

        $response = $controller($request);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getInertiaComponentForAuditLog($response))->toBe('settings/AuditLogs/Index');
    });

    it('denies hr_manager access to audit logs page', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $hrManager = createTenantUserForAuditLog($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        expect(Gate::allows('can-view-audit-logs'))->toBeFalse();
    });

    it('denies hr_staff access to audit logs page', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $hrStaff = createTenantUserForAuditLog($tenant, TenantUserRole::HrStaff);
        $this->actingAs($hrStaff);

        expect(Gate::allows('can-view-audit-logs'))->toBeFalse();
    });

    it('denies employee access to audit logs page', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $employee = createTenantUserForAuditLog($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        expect(Gate::allows('can-view-audit-logs'))->toBeFalse();
    });

    it('allows super admin to access audit logs page', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        expect(Gate::allows('can-view-audit-logs'))->toBeTrue();
    });
});

describe('Audit Log Page Content', function () {
    it('renders audit logs page with log data', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $admin = createTenantUserForAuditLog($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        AuditLog::factory()->count(5)->create();

        $controller = new AuditLogPageController;
        $request = Request::create('/settings/audit-logs', 'GET');
        app()->instance('request', $request);

        $response = $controller($request);

        $data = getInertiaResponseDataForAuditLog($response);
        expect($data)->toHaveKey('logs');
        expect($data)->toHaveKey('filters');
        expect($data)->toHaveKey('modelTypes');
        expect($data)->toHaveKey('actions');
        expect($data)->toHaveKey('users');
    });

    it('filters logs by model type', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $admin = createTenantUserForAuditLog($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        AuditLog::factory()->count(3)->create([
            'auditable_type' => Employee::class,
        ]);
        AuditLog::factory()->count(2)->create([
            'auditable_type' => 'App\\Models\\Department',
        ]);

        $controller = new AuditLogPageController;
        $request = Request::create('/settings/audit-logs', 'GET', [
            'model_type' => Employee::class,
        ]);
        app()->instance('request', $request);

        $response = $controller($request);

        $data = getInertiaResponseDataForAuditLog($response);
        expect(count($data['logs']->items()))->toBe(3);
        expect($data['filters']['model_type'])->toBe(Employee::class);
    });

    it('filters logs by action', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $admin = createTenantUserForAuditLog($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        AuditLog::factory()->created()->count(3)->create();
        AuditLog::factory()->updated()->count(2)->create();
        AuditLog::factory()->deleted()->count(1)->create();

        $controller = new AuditLogPageController;
        $request = Request::create('/settings/audit-logs', 'GET', [
            'action' => 'created',
        ]);
        app()->instance('request', $request);

        $response = $controller($request);

        $data = getInertiaResponseDataForAuditLog($response);
        expect(count($data['logs']->items()))->toBe(3);
        expect($data['filters']['action'])->toBe('created');
    });

    it('filters logs by user', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $admin = createTenantUserForAuditLog($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AuditLog::factory()->byUser($user1)->count(3)->create();
        AuditLog::factory()->byUser($user2)->count(2)->create();

        $controller = new AuditLogPageController;
        $request = Request::create('/settings/audit-logs', 'GET', [
            'user_id' => (string) $user1->id,
        ]);
        app()->instance('request', $request);

        $response = $controller($request);

        $data = getInertiaResponseDataForAuditLog($response);
        expect(count($data['logs']->items()))->toBe(3);
        expect($data['filters']['user_id'])->toBe((string) $user1->id);
    });

    it('filters logs by date range', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $admin = createTenantUserForAuditLog($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        AuditLog::factory()->count(2)->create([
            'created_at' => now(),
        ]);
        AuditLog::factory()->count(3)->create([
            'created_at' => now()->subDays(5),
        ]);

        $controller = new AuditLogPageController;
        $request = Request::create('/settings/audit-logs', 'GET', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);
        app()->instance('request', $request);

        $response = $controller($request);

        $data = getInertiaResponseDataForAuditLog($response);
        expect(count($data['logs']->items()))->toBe(2);
    });

    it('provides filter options with correct data', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $admin = createTenantUserForAuditLog($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        AuditLog::factory()->byUser($user1)->create([
            'auditable_type' => Employee::class,
        ]);
        AuditLog::factory()->byUser($user2)->create([
            'auditable_type' => 'App\\Models\\Department',
        ]);

        $controller = new AuditLogPageController;
        $request = Request::create('/settings/audit-logs', 'GET');
        app()->instance('request', $request);

        $response = $controller($request);

        $data = getInertiaResponseDataForAuditLog($response);

        // Check model types
        expect($data['modelTypes'])->toHaveCount(2);

        // Check actions
        expect($data['actions'])->toHaveCount(3); // Created, Updated, Deleted

        // Check users
        expect($data['users'])->toHaveCount(2);
    });

    it('paginates results', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $admin = createTenantUserForAuditLog($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create more than 25 logs (default pagination)
        AuditLog::factory()->count(30)->create();

        $controller = new AuditLogPageController;
        $request = Request::create('/settings/audit-logs', 'GET');
        app()->instance('request', $request);

        $response = $controller($request);

        $data = getInertiaResponseDataForAuditLog($response);
        // The logs are wrapped in a Resource collection with pagination
        expect($data['logs'])->toBeInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class);
        expect($data['logs']->resource->perPage())->toBe(25);
        expect($data['logs']->resource->total())->toBe(30);
    });
});

describe('Audit Log Resource', function () {
    it('transforms audit log correctly', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $user = User::factory()->create(['name' => 'Test User']);

        $auditLog = AuditLog::factory()->create([
            'auditable_type' => Employee::class,
            'auditable_id' => 123,
            'action' => AuditAction::Created,
            'user_id' => $user->id,
            'new_values' => ['name' => 'John Doe'],
            'ip_address' => '192.168.1.1',
        ]);

        $resource = new \App\Http\Resources\AuditLogResource($auditLog);
        $array = $resource->toArray(request());

        expect($array)->toHaveKeys([
            'id',
            'auditable_type',
            'auditable_id',
            'model_name',
            'action',
            'action_label',
            'action_color',
            'user_id',
            'user_name',
            'old_values',
            'new_values',
            'ip_address',
            'created_at',
            'formatted_created_at',
        ]);

        expect($array['model_name'])->toBe('Employee');
        expect($array['action'])->toBe('created');
        expect($array['action_label'])->toBe('Created');
        expect($array['action_color'])->toBe('green');
        expect($array['user_name'])->toBe('Test User');
    });

    it('shows System for anonymous audit logs', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForAuditLog($tenant);

        $auditLog = AuditLog::factory()->anonymous()->create();

        $resource = new \App\Http\Resources\AuditLogResource($auditLog);
        $array = $resource->toArray(request());

        expect($array['user_name'])->toBe('System');
    });
});
