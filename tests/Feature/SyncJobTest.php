<?php

use App\Enums\SyncStatus;
use App\Jobs\BulkSyncEmployeesToDeviceJob;
use App\Jobs\ScheduledBatchSyncJob;
use App\Jobs\SyncEmployeeToDeviceJob;
use App\Models\BiometricDevice;
use App\Models\DeviceSyncLog;
use App\Models\Employee;
use App\Models\EmployeeDeviceSync;
use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Services\Biometric\DeviceCommandService;
use App\Services\Biometric\EmployeeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForSyncJob(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('SyncEmployeeToDeviceJob', function () {
    it('can be serialized and deserialized correctly', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $employee = Employee::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $job = new SyncEmployeeToDeviceJob(
            $employee->id,
            $device->id,
            $tenant->id
        );

        expect($job->employeeId)->toBe($employee->id);
        expect($job->deviceId)->toBe($device->id);
        expect($job->tenantId)->toBe($tenant->id);

        // Test serialization
        $serialized = serialize($job);
        $deserialized = unserialize($serialized);

        expect($deserialized->employeeId)->toBe($employee->id);
        expect($deserialized->deviceId)->toBe($device->id);
        expect($deserialized->tenantId)->toBe($tenant->id);
    });

    it('has correct retry configuration', function () {
        $job = new SyncEmployeeToDeviceJob(1, 1, 1);

        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe([60, 300, 900]);
    });

    it('processes sync and creates sync record', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device = BiometricDevice::factory()->online()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Mock the DeviceCommandService
        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockSyncLog = new DeviceSyncLog;
        $mockSyncLog->message_id = 'test-message-123';
        $mockDeviceCommandService->shouldReceive('editPerson')
            ->once()
            ->andReturn($mockSyncLog);

        // Bind the mock to the container
        app()->instance(DeviceCommandService::class, $mockDeviceCommandService);

        $job = new SyncEmployeeToDeviceJob(
            $employee->id,
            $device->id,
            $tenant->id
        );

        // Use app()->call() to properly inject dependencies
        app()->call([$job, 'handle']);

        // Verify sync record was created and marked as synced
        $syncRecord = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device->id)
            ->first();

        expect($syncRecord)->not->toBeNull();
        expect($syncRecord->status)->toBe(SyncStatus::Synced);
    });

    it('marks sync as failed on exception', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device = BiometricDevice::factory()->online()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create a pending sync record
        EmployeeDeviceSync::factory()->pending()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
        ]);

        // Mock the DeviceCommandService to throw an exception
        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('editPerson')
            ->once()
            ->andThrow(new Exception('Connection failed'));

        app()->instance(DeviceCommandService::class, $mockDeviceCommandService);

        $job = new SyncEmployeeToDeviceJob(
            $employee->id,
            $device->id,
            $tenant->id
        );

        // Use app()->call() to properly inject dependencies
        app()->call([$job, 'handle']);

        // Verify sync record was marked as failed
        $syncRecord = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device->id)
            ->first();

        expect($syncRecord->status)->toBe(SyncStatus::Failed);
        expect($syncRecord->last_error)->toContain('Connection failed');
    });
});

describe('BulkSyncEmployeesToDeviceJob', function () {
    it('syncs all employees at location to device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create multiple employees at the same work location
        $employee1 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee2 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        // Inactive employee should not be synced
        Employee::factory()->terminated()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Mock the DeviceCommandService
        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockSyncLog = new DeviceSyncLog;
        $mockSyncLog->message_id = 'test-message';
        $mockDeviceCommandService->shouldReceive('editPerson')
            ->times(2)  // Should be called for 2 active employees
            ->andReturn($mockSyncLog);
        app()->instance(DeviceCommandService::class, $mockDeviceCommandService);

        $job = new BulkSyncEmployeesToDeviceJob(
            $device->id,
            $tenant->id,
            null  // null means all employees at location
        );

        // Use app()->call() to properly inject dependencies
        app()->call([$job, 'handle']);

        // Should create sync records for active employees only
        expect(EmployeeDeviceSync::where('biometric_device_id', $device->id)->count())->toBe(2);
    });

    it('syncs only specified employees to device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $employee1 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee2 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee3 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Mock the DeviceCommandService
        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockSyncLog = new DeviceSyncLog;
        $mockSyncLog->message_id = 'test-message';
        $mockDeviceCommandService->shouldReceive('editPerson')
            ->times(2)  // Should be called for 2 specified employees
            ->andReturn($mockSyncLog);
        app()->instance(DeviceCommandService::class, $mockDeviceCommandService);

        $job = new BulkSyncEmployeesToDeviceJob(
            $device->id,
            $tenant->id,
            [$employee1->id, $employee2->id]  // Only sync these two
        );

        // Use app()->call() to properly inject dependencies
        app()->call([$job, 'handle']);

        // Should create sync records for specified employees only
        expect(EmployeeDeviceSync::where('biometric_device_id', $device->id)->count())->toBe(2);
    });

    it('can be serialized correctly', function () {
        $job = new BulkSyncEmployeesToDeviceJob(1, 2, [3, 4, 5]);

        expect($job->deviceId)->toBe(1);
        expect($job->tenantId)->toBe(2);
        expect($job->employeeIds)->toBe([3, 4, 5]);

        $serialized = serialize($job);
        $deserialized = unserialize($serialized);

        expect($deserialized->deviceId)->toBe(1);
        expect($deserialized->tenantId)->toBe(2);
        expect($deserialized->employeeIds)->toBe([3, 4, 5]);
    });
});

describe('ScheduledBatchSyncJob', function () {
    it('processes pending syncs for all tenants', function () {
        $tenant1 = Tenant::factory()->create(['slug' => 'tenant-1']);

        // Set up tenant 1
        bindTenantContextForSyncJob($tenant1);
        $workLocation1 = WorkLocation::factory()->create();
        $device1 = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation1->id,
        ]);
        $employee1 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation1->id,
        ]);
        $sync1 = EmployeeDeviceSync::factory()->pending()->create([
            'employee_id' => $employee1->id,
            'biometric_device_id' => $device1->id,
        ]);

        // Mock the DeviceCommandService
        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockSyncLog = new DeviceSyncLog;
        $mockSyncLog->message_id = 'test-message';
        $mockDeviceCommandService->shouldReceive('editPerson')
            ->andReturn($mockSyncLog);
        app()->instance(DeviceCommandService::class, $mockDeviceCommandService);

        $job = new ScheduledBatchSyncJob;
        // Use app()->call() to properly inject dependencies
        app()->call([$job, 'handle']);

        // Verify sync record was updated
        $sync1->refresh();
        expect($sync1->status)->toBe(SyncStatus::Synced);
    });

    it('processes failed syncs with retry backoff', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create a failed sync record that should be retried (attempted > 15 mins ago)
        $sync = EmployeeDeviceSync::factory()->failed()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'retry_count' => 1,
            'last_attempted_at' => now()->subMinutes(20),
        ]);

        // Mock the DeviceCommandService
        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockSyncLog = new DeviceSyncLog;
        $mockSyncLog->message_id = 'test-message';
        $mockDeviceCommandService->shouldReceive('editPerson')
            ->once()
            ->andReturn($mockSyncLog);
        app()->instance(DeviceCommandService::class, $mockDeviceCommandService);

        $job = new ScheduledBatchSyncJob;
        // Use app()->call() to properly inject dependencies
        app()->call([$job, 'handle']);

        // Verify sync record was retried and updated
        $sync->refresh();
        expect($sync->status)->toBe(SyncStatus::Synced);
    });

    it('skips failed syncs within backoff period', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create a failed sync record that was attempted recently (within 15-minute backoff)
        $sync = EmployeeDeviceSync::factory()->failed()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'retry_count' => 1,
            'last_attempted_at' => now()->subMinutes(5),  // Only 5 mins ago
        ]);

        // Mock the DeviceCommandService - should NOT be called
        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldNotReceive('editPerson');
        app()->instance(DeviceCommandService::class, $mockDeviceCommandService);

        $job = new ScheduledBatchSyncJob;
        // Use app()->call() to properly inject dependencies
        app()->call([$job, 'handle']);

        // Verify sync record was NOT retried (still failed)
        $sync->refresh();
        expect($sync->status)->toBe(SyncStatus::Failed);
    });
});

describe('EmployeeSyncService', function () {
    it('creates sync records when syncing to devices', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Mock the DeviceCommandService
        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockSyncLog = new DeviceSyncLog;
        $mockSyncLog->message_id = 'test-message-123';
        $mockDeviceCommandService->shouldReceive('editPerson')
            ->once()
            ->andReturn($mockSyncLog);

        $service = new EmployeeSyncService($mockDeviceCommandService);
        $syncRecord = $service->syncEmployeeToDevice($employee, $device, true);

        expect($syncRecord)->not->toBeNull();
        expect($syncRecord->employee_id)->toBe($employee->id);
        expect($syncRecord->biometric_device_id)->toBe($device->id);
        expect($syncRecord->status)->toBe(SyncStatus::Synced);
    });

    it('initializes sync records for new employees', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // Create multiple devices at the work location
        $device1 = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device2 = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $service = new EmployeeSyncService($mockDeviceCommandService);

        $syncRecords = $service->initializeSyncRecords($employee);

        expect($syncRecords)->toHaveCount(2);
        expect(EmployeeDeviceSync::where('employee_id', $employee->id)->count())->toBe(2);

        // All should be in pending status
        foreach ($syncRecords as $record) {
            expect($record->status)->toBe(SyncStatus::Pending);
        }
    });

    it('returns existing sync status for employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $device1 = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device2 = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeDeviceSync::factory()->synced()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device1->id,
        ]);
        EmployeeDeviceSync::factory()->pending()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device2->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $service = new EmployeeSyncService($mockDeviceCommandService);

        $statuses = $service->getEmployeeSyncStatus($employee);

        expect($statuses)->toHaveCount(2);
    });

    it('returns existing sync status for device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForSyncJob($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        $employee1 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $employee2 = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeDeviceSync::factory()->synced()->create([
            'employee_id' => $employee1->id,
            'biometric_device_id' => $device->id,
        ]);
        EmployeeDeviceSync::factory()->failed()->create([
            'employee_id' => $employee2->id,
            'biometric_device_id' => $device->id,
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $service = new EmployeeSyncService($mockDeviceCommandService);

        $statuses = $service->getDeviceSyncStatus($device);

        expect($statuses)->toHaveCount(2);
    });
});
