<?php

use App\Enums\SyncStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\BiometricSyncController;
use App\Http\Requests\SyncAllToDeviceRequest;
use App\Http\Requests\SyncEmployeeRequest;
use App\Jobs\BulkSyncEmployeesToDeviceJob;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\EmployeeDeviceSync;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Biometric\DeviceCommandService;
use App\Services\Biometric\EmployeeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForBiometricSyncApi(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForBiometricSyncApi(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated SyncAllToDeviceRequest.
 */
function createSyncAllToDeviceRequest(array $data, User $user): SyncAllToDeviceRequest
{
    $request = SyncAllToDeviceRequest::create('/api/organization/devices/1/sync-all', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new SyncAllToDeviceRequest)->rules());

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated SyncEmployeeRequest.
 *
 * Uses default connection for exists rules since tests run tenant tables on the default connection.
 */
function createSyncEmployeeRequest(array $data, User $user): SyncEmployeeRequest
{
    $request = SyncEmployeeRequest::create('/api/employees/1/sync-to-devices', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    // Override tenant-prefixed exists rules to use default connection for testing
    $rules = (new SyncEmployeeRequest)->rules();
    $rules['device_ids.*'] = ['integer', 'exists:biometric_devices,id'];

    $validator = Validator::make($data, $rules);

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Device Sync Status Endpoint', function () {
    it('returns sync status for all employees on a device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $admin = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create employees at the same work location
        $employee1 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee2 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create sync records
        EmployeeDeviceSync::factory()->synced()->create([
            'employee_id' => $employee1->id,
            'biometric_device_id' => $device->id,
        ]);
        EmployeeDeviceSync::factory()->pending()->create([
            'employee_id' => $employee2->id,
            'biometric_device_id' => $device->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $response = $controller->deviceSyncStatus($device);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data)->toHaveKey('data');
        expect($data)->toHaveKey('meta');
        expect($data['meta']['device_id'])->toBe($device->id);
        expect($data['meta']['device_name'])->toBe($device->name);
        expect($data['meta']['synced_count'])->toBe(1);
        expect($data['meta']['pending_count'])->toBe(1);
    });

    it('denies access to employees for device sync status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricSyncApi($tenant);

        $employee = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        expect(Gate::allows('view-biometric-devices'))->toBeFalse();
    });

    it('allows HR Manager to view device sync status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricSyncApi($tenant);

        $hrManager = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        expect(Gate::allows('view-biometric-devices'))->toBeTrue();
    });
});

describe('Sync All to Device Endpoint', function () {
    it('queues bulk sync job when immediate is false', function () {
        Queue::fake();

        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $admin = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $request = createSyncAllToDeviceRequest([
            'immediate' => false,
        ], $admin);

        $response = $controller->syncAllToDevice($request, 'test-tenant', $device);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(202);
        expect($data['message'])->toBe('Sync job queued');
        expect($data['data']['queued'])->toBeTrue();
        expect($data['data']['device_id'])->toBe($device->id);

        Queue::assertPushed(BulkSyncEmployeesToDeviceJob::class);
    });

    it('only HR Manager and Admin can manage devices', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricSyncApi($tenant);

        $admin = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::Admin);
        $hrManager = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrManager);
        $hrStaff = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrStaff);
        $employee = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::Employee);

        $this->actingAs($admin);
        expect(Gate::allows('manage-biometric-devices'))->toBeTrue();

        $this->actingAs($hrManager);
        expect(Gate::allows('manage-biometric-devices'))->toBeTrue();

        $this->actingAs($hrStaff);
        expect(Gate::allows('manage-biometric-devices'))->toBeFalse();

        $this->actingAs($employee);
        expect(Gate::allows('manage-biometric-devices'))->toBeFalse();
    });

    it('validates employee_ids as array of integers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricSyncApi($tenant);

        $workLocation = WorkLocation::factory()->create();
        $employee1 = Employee::factory()->active()->create(['work_location_id' => $workLocation->id]);
        $employee2 = Employee::factory()->active()->create(['work_location_id' => $workLocation->id]);

        $rules = (new SyncAllToDeviceRequest)->rules();

        $validData = ['employee_ids' => [$employee1->id, $employee2->id], 'immediate' => true];
        $validator = Validator::make($validData, $rules);
        expect($validator->passes())->toBeTrue();

        $invalidData = ['employee_ids' => 'not-an-array'];
        $validator = Validator::make($invalidData, $rules);
        expect($validator->fails())->toBeTrue();
    });
});

describe('Employee Sync Status Endpoint', function () {
    it('returns sync status for an employee across all devices', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $hrManager = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $device1 = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device2 = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeDeviceSync::factory()->synced()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device1->id,
        ]);
        EmployeeDeviceSync::factory()->failed()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device2->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $response = $controller->employeeSyncStatus($employee);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data)->toHaveKey('data');
        expect($data)->toHaveKey('meta');
        expect($data['meta']['employee_id'])->toBe($employee->id);
        expect($data['meta']['synced_count'])->toBe(1);
        expect($data['meta']['failed_count'])->toBe(1);
    });
});

describe('Verify Employee Devices Endpoint', function () {
    it('marks employee as synced when found on device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $hrManager = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeDeviceSync::factory()->pending()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('searchPerson')
            ->once()
            ->andReturn(['exists' => true, 'data' => ['customId' => $employee->employee_number]]);

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $response = $controller->verifyEmployeeDevices($employee);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['data'])->toHaveCount(1);
        expect($data['data'][0]['status'])->toBe('synced');
    });

    it('marks synced record as pending when not found on device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $hrManager = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeDeviceSync::factory()->synced()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('searchPerson')
            ->once()
            ->andReturn(['exists' => false, 'data' => null]);

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $response = $controller->verifyEmployeeDevices($employee);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['data'])->toHaveCount(1);
        expect($data['data'][0]['status'])->toBe('pending');
    });

    it('leaves failed status unchanged when not found on device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $hrManager = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeDeviceSync::factory()->failed()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('searchPerson')
            ->once()
            ->andReturn(['exists' => false, 'data' => null]);

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $response = $controller->verifyEmployeeDevices($employee);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['data'][0]['status'])->toBe('failed');
    });

    it('leaves status unchanged when device query fails', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $hrManager = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeDeviceSync::factory()->synced()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('searchPerson')
            ->once()
            ->andThrow(new \RuntimeException('MQTT connection failed'));

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $response = $controller->verifyEmployeeDevices($employee);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['data'][0]['status'])->toBe('synced');
    });

    it('returns empty data when employee has no devices to sync to', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $hrManager = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Employee at a work location with no devices
        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldNotReceive('searchPerson');

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $response = $controller->verifyEmployeeDevices($employee);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['data'])->toBeEmpty();
    });
});

describe('Sync Employee to Devices Endpoint', function () {
    it('syncs employee to specific devices', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $admin = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $device = BiometricDevice::factory()->online()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('editPerson')
            ->andReturn((object) ['message_id' => 'test-message-id']);

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $request = createSyncEmployeeRequest([
            'device_ids' => [$device->id],
            'immediate' => true,
        ], $admin);

        $response = $controller->syncEmployeeToDevices($request, 'test-tenant', $employee);

        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['message'])->toBe('Sync completed');
    });

    it('validates device_ids as array of integers', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricSyncApi($tenant);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $device1 = BiometricDevice::factory()->create(['work_location_id' => $workLocation->id]);
        $device2 = BiometricDevice::factory()->create(['work_location_id' => $workLocation->id]);

        $rules = (new SyncEmployeeRequest)->rules();
        // Override tenant-prefixed exists rules to use default connection for testing
        $rules['device_ids.*'] = ['integer', 'exists:biometric_devices,id'];

        $validData = ['device_ids' => [$device1->id, $device2->id], 'immediate' => true];
        $validator = Validator::make($validData, $rules);
        expect($validator->passes())->toBeTrue();

        $invalidData = ['device_ids' => ['not', 'integers']];
        $validator = Validator::make($invalidData, $rules);
        expect($validator->fails())->toBeTrue();
    });

    it('returns 202 when immediate is false', function () {
        Queue::fake();

        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForBiometricSyncApi($tenant);

        $admin = createTenantUserForBiometricSyncApi($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $request = createSyncEmployeeRequest([
            'device_ids' => [$device->id],
            'immediate' => false,
        ], $admin);

        $response = $controller->syncEmployeeToDevices($request, 'test-tenant', $employee);

        expect($response->getStatusCode())->toBe(202);
    });
});

describe('EmployeeDeviceSync Model', function () {
    it('has correct scopes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricSyncApi($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee = Employee::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create records with different statuses
        EmployeeDeviceSync::factory()->pending()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
        ]);

        $employee2 = Employee::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        EmployeeDeviceSync::factory()->synced()->create([
            'employee_id' => $employee2->id,
            'biometric_device_id' => $device->id,
        ]);

        $employee3 = Employee::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        EmployeeDeviceSync::factory()->failed()->create([
            'employee_id' => $employee3->id,
            'biometric_device_id' => $device->id,
        ]);

        expect(EmployeeDeviceSync::pending()->count())->toBe(1);
        expect(EmployeeDeviceSync::failed()->count())->toBe(1);
        expect(EmployeeDeviceSync::needsSync()->count())->toBe(2);
        expect(EmployeeDeviceSync::forDevice($device->id)->count())->toBe(3);
    });

    it('can mark status transitions correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForBiometricSyncApi($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee = Employee::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $syncRecord = EmployeeDeviceSync::factory()->pending()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
        ]);

        // Test markSyncing
        $syncRecord->markSyncing('test-message-id');
        $syncRecord->refresh();
        expect($syncRecord->status)->toBe(SyncStatus::Syncing);
        expect($syncRecord->last_message_id)->toBe('test-message-id');
        expect($syncRecord->last_attempted_at)->not->toBeNull();

        // Test markSynced
        $syncRecord->markSynced();
        $syncRecord->refresh();
        expect($syncRecord->status)->toBe(SyncStatus::Synced);
        expect($syncRecord->last_synced_at)->not->toBeNull();
        expect($syncRecord->last_error)->toBeNull();

        // Test markFailed
        $syncRecord->markFailed('Connection timeout');
        $syncRecord->refresh();
        expect($syncRecord->status)->toBe(SyncStatus::Failed);
        expect($syncRecord->last_error)->toBe('Connection timeout');
        expect($syncRecord->retry_count)->toBe(1);
    });
});
