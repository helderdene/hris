<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\KioskController;
use App\Http\Requests\StoreKioskRequest;
use App\Http\Requests\UpdateKioskRequest;
use App\Models\Kiosk;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantForKioskCrud(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createKioskCrudUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to create a validated store kiosk request.
 */
function createStoreKioskRequestHelper(array $data, User $user): StoreKioskRequest
{
    $request = StoreKioskRequest::create('/api/organization/kiosks', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreKioskRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update kiosk request.
 */
function createUpdateKioskRequestHelper(array $data, User $user, int $kioskId): UpdateKioskRequest
{
    $request = UpdateKioskRequest::create("/api/organization/kiosks/{$kioskId}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new UpdateKioskRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    config(['database.connections.tenant' => config('database.connections.sqlite')]);
    DB::connection('tenant')->setPdo(DB::connection()->getPdo());
    DB::connection('tenant')->setReadPdo(DB::connection()->getReadPdo());
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Kiosk CRUD API', function () {
    it('index returns kiosks with work location', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForKioskCrud($tenant);
        $admin = createKioskCrudUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $location = WorkLocation::factory()->create(['name' => 'HQ']);
        Kiosk::factory()->count(3)->forWorkLocation($location)->create();

        $controller = new KioskController;
        $request = Illuminate\Http\Request::create('/api/organization/kiosks', 'GET');
        $response = $controller->index($request);

        expect($response->count())->toBe(3);
    });

    it('store creates kiosk with token', function () {
        $plan = Plan::factory()->professional()->create();
        $tenant = Tenant::factory()->withPlan($plan)->withTrial(14)->create();
        bindTenantForKioskCrud($tenant);
        $admin = createKioskCrudUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $location = WorkLocation::factory()->create(['status' => 'active']);

        $controller = new KioskController;
        $storeRequest = createStoreKioskRequestHelper([
            'name' => 'Lobby Kiosk',
            'work_location_id' => $location->id,
            'location' => 'Ground floor',
            'is_active' => true,
            'settings' => ['cooldown_minutes' => 5],
        ], $admin);

        $response = $controller->store($storeRequest);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(201);
        expect($data['name'])->toBe('Lobby Kiosk');

        $this->assertDatabaseHas('kiosks', ['name' => 'Lobby Kiosk']);

        $kiosk = Kiosk::where('name', 'Lobby Kiosk')->first();
        expect($kiosk->token)->toHaveLength(64);
    });

    it('update modifies kiosk', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForKioskCrud($tenant);
        $admin = createKioskCrudUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $location = WorkLocation::factory()->create(['status' => 'active']);
        $kiosk = Kiosk::factory()->create([
            'name' => 'Old Name',
            'work_location_id' => $location->id,
        ]);

        $controller = new KioskController;
        $updateRequest = createUpdateKioskRequestHelper([
            'name' => 'New Name',
            'work_location_id' => $location->id,
            'is_active' => false,
            'settings' => ['cooldown_minutes' => 10],
        ], $admin, $kiosk->id);

        $response = $controller->update($updateRequest, $kiosk);
        $data = $response->toArray(request());

        expect($data['name'])->toBe('New Name');
        expect($data['is_active'])->toBeFalse();

        $kiosk->refresh();
        expect($kiosk->name)->toBe('New Name');
        expect($kiosk->is_active)->toBeFalse();
    });

    it('destroy removes kiosk', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForKioskCrud($tenant);
        $admin = createKioskCrudUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $kiosk = Kiosk::factory()->create(['name' => 'Kiosk To Delete']);
        $kioskId = $kiosk->id;

        $controller = new KioskController;
        $response = $controller->destroy($kiosk);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['message'])->toContain('deleted');

        $this->assertDatabaseMissing('kiosks', ['id' => $kioskId]);
    });

    it('regenerate-token generates new token', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForKioskCrud($tenant);
        $admin = createKioskCrudUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $kiosk = Kiosk::factory()->create();
        $oldToken = $kiosk->token;

        $controller = new KioskController;
        $response = $controller->regenerateToken($kiosk);
        $data = $response->toArray(request());

        expect($data['token'])->not->toBe($oldToken);
        expect($kiosk->fresh()->token)->not->toBe($oldToken);
        expect($kiosk->fresh()->token)->toHaveLength(64);
    });

    it('only authorized users can access kiosk endpoints', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForKioskCrud($tenant);

        $admin = createKioskCrudUser($tenant, TenantUserRole::Admin);
        $hrManager = createKioskCrudUser($tenant, TenantUserRole::HrManager);
        $employee = createKioskCrudUser($tenant, TenantUserRole::Employee);
        $hrStaff = createKioskCrudUser($tenant, TenantUserRole::HrStaff);

        expect(Gate::forUser($admin)->allows('can-manage-organization'))->toBeTrue();
        expect(Gate::forUser($hrManager)->allows('can-manage-organization'))->toBeTrue();
        expect(Gate::forUser($employee)->allows('can-manage-organization'))->toBeFalse();
        expect(Gate::forUser($hrStaff)->allows('can-manage-organization'))->toBeFalse();
    });
});
