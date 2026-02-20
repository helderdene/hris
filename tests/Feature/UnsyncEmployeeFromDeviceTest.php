<?php

use App\Enums\SyncStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\BiometricSyncController;
use App\Http\Requests\UnsyncEmployeeRequest;
use App\Models\BiometricDevice;
use App\Models\DeviceSyncLog;
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
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function bindTenantContextForUnsync(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForUnsync(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function createUnsyncRequest(array $data, User $user): UnsyncEmployeeRequest
{
    $request = UnsyncEmployeeRequest::create('/api/employees/1/unsync-from-device', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = (new UnsyncEmployeeRequest)->rules();
    $rules['device_id'] = ['required', 'integer', 'exists:biometric_devices,id'];

    $validator = Validator::make($data, $rules);

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
});

describe('Unsync Employee from Device', function () {
    it('successfully unsyncs an employee from a device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForUnsync($tenant);

        $admin = createTenantUserForUnsync($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

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

        $mockSyncLog = new DeviceSyncLog([
            'status' => DeviceSyncLog::STATUS_ACKNOWLEDGED,
            'message_id' => 'test-del-msg',
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('deletePerson')
            ->once()
            ->andReturn($mockSyncLog);

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $request = createUnsyncRequest(['device_id' => $device->id], $admin);

        $response = $controller->unsyncEmployeeFromDevice($request, $employee);
        $data = json_decode($response->getContent(), true);

        expect($response->getStatusCode())->toBe(200);
        expect($data['message'])->toBe('Unsync completed');

        $syncRecord = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device->id)
            ->first();

        expect($syncRecord->status)->toBe(SyncStatus::Pending);
        expect($syncRecord->last_synced_at)->toBeNull();
        expect($syncRecord->last_error)->toBeNull();
    });

    it('marks sync record as failed when device rejects unsync', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForUnsync($tenant);

        $admin = createTenantUserForUnsync($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

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

        $mockSyncLog = new DeviceSyncLog([
            'status' => DeviceSyncLog::STATUS_FAILED,
            'message_id' => 'test-del-msg',
            'response_payload' => ['info' => ['result' => 'person not found']],
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('deletePerson')
            ->once()
            ->andReturn($mockSyncLog);

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $request = createUnsyncRequest(['device_id' => $device->id], $admin);

        $response = $controller->unsyncEmployeeFromDevice($request, $employee);

        expect($response->getStatusCode())->toBe(200);

        $syncRecord = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device->id)
            ->first();

        expect($syncRecord->status)->toBe(SyncStatus::Failed);
        expect($syncRecord->last_error)->toBe('person not found');
    });

    it('marks sync record as failed on ack timeout', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForUnsync($tenant);

        $admin = createTenantUserForUnsync($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

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

        // STATUS_SENT means no ack was received (timeout)
        $mockSyncLog = new DeviceSyncLog([
            'status' => DeviceSyncLog::STATUS_SENT,
            'message_id' => 'test-del-msg',
        ]);

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldReceive('deletePerson')
            ->once()
            ->andReturn($mockSyncLog);

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $request = createUnsyncRequest(['device_id' => $device->id], $admin);

        $response = $controller->unsyncEmployeeFromDevice($request, $employee);

        expect($response->getStatusCode())->toBe(200);

        $syncRecord = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device->id)
            ->first();

        expect($syncRecord->status)->toBe(SyncStatus::Failed);
        expect($syncRecord->last_error)->toContain('timeout');
    });

    it('validates device_id is required', function () {
        $rules = (new UnsyncEmployeeRequest)->rules();
        $rules['device_id'] = ['required', 'integer', 'exists:biometric_devices,id'];

        $validator = Validator::make([], $rules);
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('device_id'))->toBeTrue();
    });

    it('validates device_id must be an integer', function () {
        $rules = (new UnsyncEmployeeRequest)->rules();
        $rules['device_id'] = ['required', 'integer', 'exists:biometric_devices,id'];

        $validator = Validator::make(['device_id' => 'not-an-integer'], $rules);
        expect($validator->fails())->toBeTrue();
    });

    it('validates device_id must exist in biometric_devices', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForUnsync($tenant);

        $rules = (new UnsyncEmployeeRequest)->rules();
        $rules['device_id'] = ['required', 'integer', 'exists:biometric_devices,id'];

        $validator = Validator::make(['device_id' => 99999], $rules);
        expect($validator->fails())->toBeTrue();
    });

    it('only allows authorized roles to unsync employees', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForUnsync($tenant);

        $employee = Employee::factory()->create();

        $admin = createTenantUserForUnsync($tenant, TenantUserRole::Admin);
        $hrManager = createTenantUserForUnsync($tenant, TenantUserRole::HrManager);
        $hrStaff = createTenantUserForUnsync($tenant, TenantUserRole::HrStaff);
        $regularEmployee = createTenantUserForUnsync($tenant, TenantUserRole::Employee);

        $this->actingAs($admin);
        expect(Gate::allows('can-manage-employee-documents', $employee))->toBeTrue();

        $this->actingAs($hrManager);
        expect(Gate::allows('can-manage-employee-documents', $employee))->toBeTrue();

        $this->actingAs($hrStaff);
        expect(Gate::allows('can-manage-employee-documents', $employee))->toBeTrue();

        $this->actingAs($regularEmployee);
        expect(Gate::allows('can-manage-employee-documents', $employee))->toBeFalse();
    });

    it('throws 404 when employee has no sync record for the device', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindTenantContextForUnsync($tenant);

        $admin = createTenantUserForUnsync($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->active()->create([
            'work_location_id' => $workLocation->id,
        ]);
        $device = BiometricDevice::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        // No sync record created

        $mockDeviceCommandService = mock(DeviceCommandService::class);
        $mockDeviceCommandService->shouldNotReceive('deletePerson');

        $syncService = new EmployeeSyncService($mockDeviceCommandService);

        $controller = new BiometricSyncController($syncService);
        $request = createUnsyncRequest(['device_id' => $device->id], $admin);

        $controller->unsyncEmployeeFromDevice($request, $employee);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
