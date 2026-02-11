<?php

use App\Enums\AssignmentType;
use App\Enums\SyncStatus;
use App\Enums\TenantUserRole;
use App\Events\EmployeeCreated;
use App\Http\Controllers\Api\BiometricDeviceController;
use App\Http\Requests\StoreBiometricDeviceRequest;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\EmployeeDeviceSync;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\AssignmentService;
use App\Services\Biometric\EmployeeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant context for biometric sync init tests.
 */
function bindTenantForSyncInit(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a tenant user for biometric sync init tests.
 */
function createUserForSyncInit(Tenant $tenant, TenantUserRole $role): User
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
});

describe('Trigger 1: Employee created with a work location', function () {
    it('dispatches EmployeeCreated event when employee is stored', function () {
        Event::fake([EmployeeCreated::class]);

        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $admin = createUserForSyncInit($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create();

        $this->post(route('employees.store', ['tenant' => $tenant->slug]), [
            'employee_number' => 'EMP-SYNC-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.sync@example.com',
            'hire_date' => '2025-01-15',
            'work_location_id' => $workLocation->id,
        ]);

        Event::assertDispatched(EmployeeCreated::class, function (EmployeeCreated $event) {
            return $event->employee->employee_number === 'EMP-SYNC-001';
        });
    });

    it('creates pending sync records when employee is created with a work location that has devices', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device1 = BiometricDevice::factory()->active()->forWorkLocation($workLocation)->create();
        $device2 = BiometricDevice::factory()->active()->forWorkLocation($workLocation)->create();

        $employee = Employee::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeCreated::dispatch($employee);

        expect(EmployeeDeviceSync::where('employee_id', $employee->id)->count())->toBe(2);

        $sync1 = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device1->id)
            ->first();
        $sync2 = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device2->id)
            ->first();

        expect($sync1)->not->toBeNull();
        expect($sync1->status)->toBe(SyncStatus::Pending);
        expect($sync2)->not->toBeNull();
        expect($sync2->status)->toBe(SyncStatus::Pending);
    });

    it('does not create sync records when employee has no work location', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $employee = Employee::factory()->create([
            'work_location_id' => null,
        ]);

        EmployeeCreated::dispatch($employee);

        expect(EmployeeDeviceSync::where('employee_id', $employee->id)->count())->toBe(0);
    });

    it('does not create sync records when work location has no active devices', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $workLocation = WorkLocation::factory()->create();
        BiometricDevice::factory()->inactive()->forWorkLocation($workLocation)->create();

        $employee = Employee::factory()->create([
            'work_location_id' => $workLocation->id,
        ]);

        EmployeeCreated::dispatch($employee);

        expect(EmployeeDeviceSync::where('employee_id', $employee->id)->count())->toBe(0);
    });
});

describe('Trigger 2: Employee work location changes via assignment', function () {
    it('creates pending sync records when employee location is changed', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $oldLocation = WorkLocation::factory()->create();
        $newLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->active()->forWorkLocation($newLocation)->create();

        $employee = Employee::factory()->create([
            'work_location_id' => $oldLocation->id,
        ]);

        $service = app(AssignmentService::class);
        $service->createAssignment($employee, [
            'assignment_type' => AssignmentType::Location->value,
            'new_value_id' => $newLocation->id,
            'effective_date' => now()->toDateString(),
            'remarks' => 'Transfer',
        ]);

        $syncRecord = EmployeeDeviceSync::where('employee_id', $employee->id)
            ->where('biometric_device_id', $device->id)
            ->first();

        expect($syncRecord)->not->toBeNull();
        expect($syncRecord->status)->toBe(SyncStatus::Pending);
    });

    it('does not create sync records for non-location assignment changes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->active()->forWorkLocation($workLocation)->create();

        $department1 = \App\Models\Department::factory()->create();
        $department2 = \App\Models\Department::factory()->create();

        $employee = Employee::factory()->create([
            'work_location_id' => $workLocation->id,
            'department_id' => $department1->id,
        ]);

        $service = app(AssignmentService::class);
        $service->createAssignment($employee, [
            'assignment_type' => AssignmentType::Department->value,
            'new_value_id' => $department2->id,
            'effective_date' => now()->toDateString(),
        ]);

        // No sync records should be created from a department change
        expect(EmployeeDeviceSync::where('employee_id', $employee->id)->count())->toBe(0);
    });
});

describe('Trigger 3: New biometric device created', function () {
    it('creates pending sync records for existing active employees at the device work location', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $admin = createUserForSyncInit($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $workLocation = WorkLocation::factory()->create(['status' => 'active']);

        $employee1 = Employee::factory()->active()->create(['work_location_id' => $workLocation->id]);
        $employee2 = Employee::factory()->active()->create(['work_location_id' => $workLocation->id]);

        $controller = new BiometricDeviceController;
        $request = StoreBiometricDeviceRequest::create('/api/organization/devices', 'POST', [
            'name' => 'New Device',
            'device_identifier' => 'DEV-SYNC-INIT-001',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ]);
        $request->setUserResolver(fn () => $admin);
        $request->setContainer(app());

        $validator = Validator::make($request->all(), (new StoreBiometricDeviceRequest)->rules());
        $validator->validate();

        $reflection = new ReflectionClass($request);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($request, $validator);

        $response = $controller->store($request, app(EmployeeSyncService::class));

        $device = BiometricDevice::where('device_identifier', 'DEV-SYNC-INIT-001')->first();

        expect(EmployeeDeviceSync::where('biometric_device_id', $device->id)->count())->toBe(2);

        $sync1 = EmployeeDeviceSync::where('employee_id', $employee1->id)
            ->where('biometric_device_id', $device->id)
            ->first();
        $sync2 = EmployeeDeviceSync::where('employee_id', $employee2->id)
            ->where('biometric_device_id', $device->id)
            ->first();

        expect($sync1)->not->toBeNull();
        expect($sync1->status)->toBe(SyncStatus::Pending);
        expect($sync2)->not->toBeNull();
        expect($sync2->status)->toBe(SyncStatus::Pending);
    });

    it('does not create sync records for inactive employees at the location', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $workLocation = WorkLocation::factory()->create();

        $activeEmployee = Employee::factory()->active()->create(['work_location_id' => $workLocation->id]);
        $terminatedEmployee = Employee::factory()->terminated()->create(['work_location_id' => $workLocation->id]);

        $device = BiometricDevice::factory()->active()->forWorkLocation($workLocation)->create();
        app(EmployeeSyncService::class)->initializeSyncRecordsForDevice($device);

        // Only the active employee should have a sync record
        expect(EmployeeDeviceSync::where('biometric_device_id', $device->id)->count())->toBe(1);
        expect(EmployeeDeviceSync::where('employee_id', $activeEmployee->id)->exists())->toBeTrue();
        expect(EmployeeDeviceSync::where('employee_id', $terminatedEmployee->id)->exists())->toBeFalse();
    });

    it('does not create sync records for employees at other locations', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $location1 = WorkLocation::factory()->create();
        $location2 = WorkLocation::factory()->create();

        Employee::factory()->active()->create(['work_location_id' => $location1->id]);
        Employee::factory()->active()->create(['work_location_id' => $location2->id]);

        $device = BiometricDevice::factory()->active()->forWorkLocation($location1)->create();
        app(EmployeeSyncService::class)->initializeSyncRecordsForDevice($device);

        // Only 1 employee at location1 should have a sync record
        expect(EmployeeDeviceSync::where('biometric_device_id', $device->id)->count())->toBe(1);
    });
});

describe('initializeSyncRecordsForDevice service method', function () {
    it('does not duplicate sync records when called multiple times', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForSyncInit($tenant);

        $workLocation = WorkLocation::factory()->create();
        $employee = Employee::factory()->active()->create(['work_location_id' => $workLocation->id]);
        $device = BiometricDevice::factory()->active()->forWorkLocation($workLocation)->create();

        $service = app(EmployeeSyncService::class);
        $service->initializeSyncRecordsForDevice($device);
        $service->initializeSyncRecordsForDevice($device);

        expect(EmployeeDeviceSync::where('biometric_device_id', $device->id)->count())->toBe(1);
    });
});
