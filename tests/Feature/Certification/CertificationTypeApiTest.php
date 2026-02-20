<?php

/**
 * Tests for Certification Type API via Controller
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\CertificationTypeController;
use App\Http\Requests\StoreCertificationTypeRequest;
use App\Models\CertificationType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function bindTenantContextForCertType(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForCertTypeApi(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function createStoreCertTypeRequest(array $data, User $user): StoreCertificationTypeRequest
{
    $request = StoreCertificationTypeRequest::create('/api/certification-types', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreCertificationTypeRequest)->rules());
    if ($validator->fails()) {
        throw new \Illuminate\Validation\ValidationException($validator);
    }

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    bindTenantContextForCertType($this->tenant);
});

describe('Certification Type CRUD Operations', function () {
    it('lists all certification types', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        CertificationType::factory()->count(3)->create();

        $controller = new CertificationTypeController;
        $response = $controller->index(request());

        expect($response->count())->toBe(3);
    });

    it('shows single certification type', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $type = CertificationType::factory()->create(['name' => 'PRC License']);

        $controller = new CertificationTypeController;
        $response = $controller->show($type);
        $data = $response->toArray(request());

        expect($data['name'])->toBe('PRC License');
    });

    it('creates certification type with all fields', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $data = [
            'name' => 'First Aid Certification',
            'description' => 'Basic first aid training',
            'validity_period_months' => 12,
            'reminder_days_before_expiry' => [30, 7],
            'is_mandatory' => true,
            'is_active' => true,
        ];

        $request = createStoreCertTypeRequest($data, $admin);

        $controller = new CertificationTypeController;
        $response = $controller->store($request);

        expect($response->getStatusCode())->toBe(201);

        $this->assertDatabaseHas('certification_types', [
            'name' => 'First Aid Certification',
            'validity_period_months' => 12,
            'is_mandatory' => true,
        ]);
    });

    it('updates certification type', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $type = CertificationType::factory()->create(['name' => 'Old Name']);

        $type->update([
            'name' => 'Updated Name',
            'validity_period_months' => 36,
        ]);

        expect($type->fresh()->name)->toBe('Updated Name');
        expect($type->fresh()->validity_period_months)->toBe(36);
    });

    it('deletes certification type', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $type = CertificationType::factory()->create();

        $controller = new CertificationTypeController;
        $response = $controller->destroy($type);

        expect($response->getStatusCode())->toBe(200);
        expect(CertificationType::find($type->id))->toBeNull();
    });
});

describe('Certification Type Validation', function () {
    it('requires name when creating', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $data = [
            'description' => 'No name provided',
        ];

        expect(fn () => createStoreCertTypeRequest($data, $admin))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('validates validity period is positive integer', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $data = [
            'name' => 'Test Type',
            'validity_period_months' => -5,
        ];

        expect(fn () => createStoreCertTypeRequest($data, $admin))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('validates reminder days is array of integers', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $data = [
            'name' => 'Test Type',
            'reminder_days_before_expiry' => 'not an array',
        ];

        expect(fn () => createStoreCertTypeRequest($data, $admin))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('filters active certification types', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        CertificationType::factory()->create(['is_active' => true]);
        CertificationType::factory()->create(['is_active' => false]);

        expect(CertificationType::active()->count())->toBe(1);
    });

    it('filters mandatory certification types', function () {
        $admin = createTenantUserForCertTypeApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        CertificationType::factory()->create(['is_mandatory' => true]);
        CertificationType::factory()->create(['is_mandatory' => false]);

        expect(CertificationType::mandatory()->count())->toBe(1);
    });
});
