<?php

use App\Enums\TenantUserRole;
use App\Events\ProfilePhotoUploaded;
use App\Jobs\SyncEmployeeToDeviceJob;
use App\Listeners\SyncProfilePhotoToDevices;
use App\Models\BiometricDevice;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForProfilePhotoSync(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForProfilePhotoSync(Tenant $tenant, TenantUserRole $role): User
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

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('ProfilePhotoUploaded Event', function () {
    it('is dispatched when profile photo is uploaded', function () {
        Event::fake([ProfilePhotoUploaded::class]);

        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create(['name' => 'Profile Photo']);
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        ProfilePhotoUploaded::dispatch($employee, $document);

        Event::assertDispatched(ProfilePhotoUploaded::class, function ($event) use ($employee, $document) {
            return $event->employee->id === $employee->id
                && $event->document->id === $document->id;
        });
    });

    it('contains employee and document references', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create(['name' => 'Profile Photo']);
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        $event = new ProfilePhotoUploaded($employee, $document);

        expect($event->employee)->toBe($employee);
        expect($event->document)->toBe($document);
    });
});

describe('SyncProfilePhotoToDevices Listener', function () {
    it('queues sync jobs for all devices at work location', function () {
        Queue::fake();

        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create multiple devices at the same work location
        $device1 = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device2 = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $category = DocumentCategory::factory()->create(['name' => 'Profile Photo']);
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        $event = new ProfilePhotoUploaded($employee, $document);
        $listener = new SyncProfilePhotoToDevices;

        $listener->handle($event);

        Queue::assertPushed(SyncEmployeeToDeviceJob::class, 2);
        Queue::assertPushed(SyncEmployeeToDeviceJob::class, function ($job) use ($employee, $device1, $tenant) {
            return $job->employeeId === $employee->id
                && $job->deviceId === $device1->id
                && $job->tenantId === $tenant->id;
        });
        Queue::assertPushed(SyncEmployeeToDeviceJob::class, function ($job) use ($employee, $device2, $tenant) {
            return $job->employeeId === $employee->id
                && $job->deviceId === $device2->id
                && $job->tenantId === $tenant->id;
        });
    });

    it('does not queue jobs when no devices exist', function () {
        Queue::fake();

        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // No devices at this work location

        $category = DocumentCategory::factory()->create(['name' => 'Profile Photo']);
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        $event = new ProfilePhotoUploaded($employee, $document);
        $listener = new SyncProfilePhotoToDevices;

        $listener->handle($event);

        Queue::assertNotPushed(SyncEmployeeToDeviceJob::class);
    });

    it('does not queue jobs for inactive devices', function () {
        Queue::fake();

        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create one active and one inactive device
        BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        BiometricDevice::factory()->inactive()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $category = DocumentCategory::factory()->create(['name' => 'Profile Photo']);
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        $event = new ProfilePhotoUploaded($employee, $document);
        $listener = new SyncProfilePhotoToDevices;

        $listener->handle($event);

        // Only the active device should have a job queued
        Queue::assertPushed(SyncEmployeeToDeviceJob::class, 1);
    });

    it('does not queue jobs when employee has no work location', function () {
        Queue::fake();

        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        // Employee without work location
        $employee = Employee::factory()->active()->create([
            'work_location_id' => null,
        ]);

        $category = DocumentCategory::factory()->create(['name' => 'Profile Photo']);
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        $event = new ProfilePhotoUploaded($employee, $document);
        $listener = new SyncProfilePhotoToDevices;

        $listener->handle($event);

        Queue::assertNotPushed(SyncEmployeeToDeviceJob::class);
    });
});

describe('Non-Profile Photo Uploads', function () {
    it('does not trigger sync event for non-profile-photo documents', function () {
        Event::fake([ProfilePhotoUploaded::class]);

        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $employee = Employee::factory()->create();
        // Create a different category (not Profile Photo)
        $category = DocumentCategory::factory()->create(['name' => 'Employment Contract']);
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        // Verify that the event is NOT dispatched automatically
        // (The EmployeeDocumentController should check the category before dispatching)
        Event::assertNotDispatched(ProfilePhotoUploaded::class);
    });
});

describe('Employee getProfilePhoto Method', function () {
    it('returns the latest profile photo document', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create(['name' => 'Profile Photo']);

        // Create older photo
        $olderPhoto = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'created_at' => now()->subDays(5),
        ]);

        // Create newer photo
        $newerPhoto = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'created_at' => now(),
        ]);

        $result = $employee->getProfilePhoto();

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($newerPhoto->id);
    });

    it('returns null when no profile photo exists', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $employee = Employee::factory()->create();
        // Create a non-profile-photo document
        $category = DocumentCategory::factory()->create(['name' => 'Employment Contract']);
        Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        $result = $employee->getProfilePhoto();

        expect($result)->toBeNull();
    });
});

describe('Employee getDevicesToSyncTo Method', function () {
    it('returns active devices at employee work location', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $activeDevice = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $inactiveDevice = BiometricDevice::factory()->inactive()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Device at different location
        $otherLocation = WorkLocation::factory()->create();
        BiometricDevice::factory()->active()->create([
            'work_location_id' => $otherLocation->id,
        ]);

        $devices = $employee->getDevicesToSyncTo();

        expect($devices)->toHaveCount(1);
        expect($devices->first()->id)->toBe($activeDevice->id);
    });

    it('returns empty collection when employee has no work location', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForProfilePhotoSync($tenant);

        $employee = Employee::factory()->active()->create([
            'work_location_id' => null,
        ]);

        $devices = $employee->getDevicesToSyncTo();

        expect($devices)->toBeEmpty();
    });
});
