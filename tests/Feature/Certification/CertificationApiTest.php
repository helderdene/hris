<?php

/**
 * Tests for HR Certification API via Controller (Admin/HR view)
 */

use App\Enums\CertificationStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\CertificationController;
use App\Models\Certification;
use App\Models\CertificationFile;
use App\Models\CertificationType;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\CertificationApproved;
use App\Notifications\CertificationRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function bindTenantContextForCertApi(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForCertApi(Tenant $tenant, TenantUserRole $role): User
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

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    bindTenantContextForCertApi($this->tenant);
});

describe('Certification Listing and Filtering', function () {
    it('lists certifications with employee and type data', function () {
        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $type = CertificationType::factory()->create(['name' => 'CPR']);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'certification_type_id' => $type->id,
        ]);

        $controller = new CertificationController;
        $response = $controller->index(request());

        expect($response->count())->toBe(1);
    });

    it('filters certifications by status', function () {
        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create();

        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
        ]);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::PendingApproval,
        ]);

        $request = request()->merge(['status' => 'active']);
        $controller = new CertificationController;
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });

    it('filters certifications by employee', function () {
        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        Certification::factory()->create(['employee_id' => $employee1->id]);
        Certification::factory()->create(['employee_id' => $employee2->id]);

        $request = request()->merge(['employee_id' => $employee1->id]);
        $controller = new CertificationController;
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });
});

describe('Certification Statistics', function () {
    it('returns certification statistics', function () {
        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create();

        Certification::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
        ]);
        Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::PendingApproval,
        ]);

        $controller = new CertificationController;
        $response = $controller->statistics();
        $data = json_decode($response->getContent(), true);

        expect($data)->toHaveKeys(['total_active', 'pending_approval', 'expired']);
        expect($data['total_active'])->toBe(2);
        expect($data['pending_approval'])->toBe(1);
        expect($data['expired'])->toBe(0);
    });
});

describe('Certification Approval Workflow', function () {
    it('approves pending certification', function () {
        Notification::fake();

        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::PendingApproval,
        ]);

        $controller = new CertificationController;
        $response = $controller->approve($certification);

        expect($response->getStatusCode())->toBe(200);
        expect($certification->fresh()->status)->toBe(CertificationStatus::Active);
        expect($certification->fresh()->approved_at)->not->toBeNull();

        Notification::assertSentTo($user, CertificationApproved::class);
    });

    it('rejects pending certification with reason', function () {
        Notification::fake();

        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::PendingApproval,
        ]);

        $request = request()->merge(['reason' => 'Document is illegible']);
        $controller = new CertificationController;
        $response = $controller->reject($request, $certification);

        expect($response->getStatusCode())->toBe(200);
        expect($certification->fresh()->status)->toBe(CertificationStatus::Draft);
        expect($certification->fresh()->rejected_at)->not->toBeNull();

        Notification::assertSentTo($user, CertificationRejected::class);
    });

    it('revokes active certification with reason', function () {
        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Active,
        ]);

        $request = request()->merge(['reason' => 'Employee terminated']);
        $controller = new CertificationController;
        $response = $controller->revoke($request, $certification);

        expect($response->getStatusCode())->toBe(200);
        expect($certification->fresh()->status)->toBe(CertificationStatus::Revoked);
    });

    it('prevents approving non-pending certification', function () {
        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create([
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
        ]);

        $controller = new CertificationController;
        $response = $controller->approve($certification);

        expect($response->getStatusCode())->toBe(422);
    });
});

describe('Certification Files', function () {
    it('includes files in certification details', function () {
        $admin = createTenantUserForCertApi($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $employee = Employee::factory()->create();
        $certification = Certification::factory()->create(['employee_id' => $employee->id]);

        CertificationFile::factory()->count(2)->create(['certification_id' => $certification->id]);

        $controller = new CertificationController;
        $response = $controller->show($certification);
        $data = $response->toArray(request());

        expect($data['files'])->toHaveCount(2);
    });
});
