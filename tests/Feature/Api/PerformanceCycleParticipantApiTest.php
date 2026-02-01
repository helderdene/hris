<?php

use App\Enums\EmploymentStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PerformanceCycleParticipantController;
use App\Http\Requests\AssignPerformanceCycleParticipantsRequest;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PerformanceCycleInstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForPerformanceCycleParticipant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPerformanceCycleParticipant(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated assign participants request.
 */
function createAssignPerformanceCycleParticipantsRequest(array $data, User $user): AssignPerformanceCycleParticipantsRequest
{
    $request = AssignPerformanceCycleParticipantsRequest::create('/api/organization/performance-cycle-instances/1/participants/assign', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = [
        'excluded_employee_ids' => ['nullable', 'array'],
        'excluded_employee_ids.*' => ['integer'],
    ];

    $validator = Validator::make($data, $rules);
    $validator->validate();

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

describe('PerformanceCycleParticipant API', function () {
    it('lists participants for an instance', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->active()
            ->create();

        $employee1 = Employee::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $employee2 = Employee::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
        $manager = Employee::factory()->create(['first_name' => 'Manager', 'last_name' => 'One']);

        PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->create([
                'employee_id' => $employee1->id,
                'manager_id' => $manager->id,
                'is_excluded' => false,
            ]);

        PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->excluded()
            ->create([
                'employee_id' => $employee2->id,
                'manager_id' => $manager->id,
            ]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        $request = Request::create("/api/organization/performance-cycle-instances/{$instance->id}/participants", 'GET');
        $response = $controller->index($request, $tenant->slug, $instance);

        expect($response->count())->toBe(2);
    });

    it('filters participants by status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->active()
            ->create();

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->create([
                'employee_id' => $employee1->id,
                'is_excluded' => false,
                'status' => 'pending',
            ]);

        PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->completed()
            ->create([
                'employee_id' => $employee2->id,
            ]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        // Filter by pending status
        $request = Request::create("/api/organization/performance-cycle-instances/{$instance->id}/participants", 'GET', ['status' => 'pending']);
        $response = $controller->index($request, $tenant->slug, $instance);

        expect($response->count())->toBe(1);

        // Filter by excluded
        $excludedRequest = Request::create("/api/organization/performance-cycle-instances/{$instance->id}/participants", 'GET', ['excluded' => 'true']);
        $excludedResponse = $controller->index($excludedRequest, $tenant->slug, $instance);

        expect($excludedResponse->count())->toBe(0);
    });

    it('assigns all active employees with their supervisors', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create();

        // Create supervisor and employees
        $supervisor = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $employee1 = Employee::factory()->create([
            'employment_status' => 'active',
            'supervisor_id' => $supervisor->id,
        ]);
        $employee2 = Employee::factory()->create([
            'employment_status' => 'active',
            'supervisor_id' => $supervisor->id,
        ]);
        // Create inactive employee that should not be assigned
        Employee::factory()->create(['employment_status' => EmploymentStatus::Resigned]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        $assignRequest = createAssignPerformanceCycleParticipantsRequest(['excluded_employee_ids' => []], $admin);
        $response = $controller->assign($assignRequest, $tenant->slug, $instance);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['assigned_count'])->toBe(3); // supervisor + 2 employees

        // Verify participants were created
        expect(PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)->count())->toBe(3);

        // Verify manager assignment
        $employeeParticipant = PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)
            ->where('employee_id', $employee1->id)
            ->first();

        expect($employeeParticipant->manager_id)->toBe($supervisor->id);
    });

    it('excludes specified employees during assignment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create();

        $employee1 = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $employee2 = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
        $excludedEmployee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        $assignRequest = createAssignPerformanceCycleParticipantsRequest([
            'excluded_employee_ids' => [$excludedEmployee->id],
        ], $admin);
        $response = $controller->assign($assignRequest, $tenant->slug, $instance);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['assigned_count'])->toBe(2);
        expect($data['excluded_count'])->toBe(1);

        // Verify excluded participant
        $excludedParticipant = PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)
            ->where('employee_id', $excludedEmployee->id)
            ->first();

        expect($excludedParticipant->is_excluded)->toBeTrue();
    });

    it('updates employee count on instance after assignment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create(['employee_count' => 0]);

        Employee::factory()->count(5)->create(['employment_status' => EmploymentStatus::Active]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        $assignRequest = createAssignPerformanceCycleParticipantsRequest(['excluded_employee_ids' => []], $admin);
        $controller->assign($assignRequest, $tenant->slug, $instance);

        $instance->refresh();
        expect($instance->employee_count)->toBe(5);
    });

    it('updates a participant record', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->active()
            ->create();

        $employee = Employee::factory()->create();
        $originalManager = Employee::factory()->create();
        $newManager = Employee::factory()->create();

        $participant = PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->create([
                'employee_id' => $employee->id,
                'manager_id' => $originalManager->id,
            ]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        $updateData = ['manager_id' => $newManager->id];
        $request = Request::create(
            "/api/organization/performance-cycle-instances/{$instance->id}/participants/{$participant->id}",
            'PUT',
            $updateData
        );
        $request->setUserResolver(fn () => $admin);

        $response = $controller->update($request, $tenant->slug, $instance, $participant);

        // Resource was returned successfully
        expect($response)->toBeInstanceOf(\App\Http\Resources\PerformanceCycleParticipantResource::class);

        $participant->refresh();
        expect($participant->manager_id)->toBe($newManager->id);
    });

    it('deletes a participant record', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->draft()
            ->create(['employee_count' => 2]);

        // Create multiple participants to test count update
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->create([
                'employee_id' => $employee1->id,
                'is_excluded' => false,
            ]);

        $participantToDelete = PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->create([
                'employee_id' => $employee2->id,
                'is_excluded' => false,
            ]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        $response = $controller->destroy($tenant->slug, $instance, $participantToDelete);

        expect($response->getStatusCode())->toBe(204);

        // Verify participant was deleted
        expect(PerformanceCycleParticipant::find($participantToDelete->id))->toBeNull();

        // Verify employee count was updated (2 -> 1)
        $instance->refresh();
        expect($instance->employee_count)->toBe(1);
    });

    it('marks participant as excluded', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->active()
            ->create(['employee_count' => 2]);

        // Create multiple participants
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->create([
                'employee_id' => $employee1->id,
                'is_excluded' => false,
            ]);

        $participantToExclude = PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->create([
                'employee_id' => $employee2->id,
                'is_excluded' => false,
            ]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        $updateData = ['is_excluded' => true];
        $request = Request::create(
            "/api/organization/performance-cycle-instances/{$instance->id}/participants/{$participantToExclude->id}",
            'PUT',
            $updateData
        );
        $request->setUserResolver(fn () => $admin);

        $response = $controller->update($request, $tenant->slug, $instance, $participantToExclude);

        // Resource was returned successfully
        expect($response)->toBeInstanceOf(\App\Http\Resources\PerformanceCycleParticipantResource::class);

        $participantToExclude->refresh();
        expect($participantToExclude->is_excluded)->toBeTrue();

        // Verify employee count was decremented (2 -> 1)
        $instance->refresh();
        expect($instance->employee_count)->toBe(1);
    });

    it('marks participant as completed', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForPerformanceCycleParticipant($tenant);

        $admin = createTenantUserForPerformanceCycleParticipant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $cycle = PerformanceCycle::factory()->annual()->create();
        $instance = PerformanceCycleInstance::factory()
            ->for($cycle, 'performanceCycle')
            ->inEvaluation()
            ->create();

        $employee = Employee::factory()->create();
        $participant = PerformanceCycleParticipant::factory()
            ->for($instance, 'performanceCycleInstance')
            ->create([
                'employee_id' => $employee->id,
                'status' => 'pending',
            ]);

        $controller = new PerformanceCycleParticipantController(new PerformanceCycleInstanceService);

        $updateData = ['status' => 'completed'];
        $request = Request::create(
            "/api/organization/performance-cycle-instances/{$instance->id}/participants/{$participant->id}",
            'PUT',
            $updateData
        );
        $request->setUserResolver(fn () => $admin);

        $response = $controller->update($request, $tenant->slug, $instance, $participant);

        // Resource was returned successfully
        expect($response)->toBeInstanceOf(\App\Http\Resources\PerformanceCycleParticipantResource::class);

        $participant->refresh();
        expect($participant->status)->toBe('completed');
        expect($participant->completed_at)->not->toBeNull();
    });
});
