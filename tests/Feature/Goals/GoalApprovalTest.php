<?php

use App\Enums\GoalApprovalStatus;
use App\Enums\GoalStatus;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForGoalApproval(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForGoalApprovalApi(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
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

describe('Goal Approval Workflow', function () {
    it('submits goal for approval', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoalApproval($tenant);

        $user = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->draft()->create([
            'approval_status' => GoalApprovalStatus::NotRequired,
        ]);

        $response = $this->actingAs($user)->postJson("/api/performance/goals/{$goal->id}/submit-approval");

        $response->assertSuccessful();

        $goal->refresh();
        expect($goal->status)->toBe(GoalStatus::PendingApproval)
            ->and($goal->approval_status)->toBe(GoalApprovalStatus::Pending);
    });

    it('manager approves goal', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoalApproval($tenant);

        // Create manager
        $managerUser = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Admin);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);

        // Create employee with supervisor
        $employeeUser = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'supervisor_id' => $manager->id,
        ]);

        $goal = Goal::factory()->for($employee)->pendingApproval()->create();

        $response = $this->actingAs($managerUser)->postJson("/api/performance/goals/{$goal->id}/approve", [
            'feedback' => 'Looks good, approved!',
        ]);

        $response->assertSuccessful();

        $goal->refresh();
        expect($goal->status)->toBe(GoalStatus::Active)
            ->and($goal->approval_status)->toBe(GoalApprovalStatus::Approved)
            ->and($goal->manager_feedback)->toBe('Looks good, approved!')
            ->and($goal->approved_by)->toBe($managerUser->id);
    });

    it('manager rejects goal with feedback', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoalApproval($tenant);

        // Create manager
        $managerUser = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Admin);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);

        // Create employee with supervisor
        $employeeUser = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'supervisor_id' => $manager->id,
        ]);

        $goal = Goal::factory()->for($employee)->pendingApproval()->create();

        $response = $this->actingAs($managerUser)->postJson("/api/performance/goals/{$goal->id}/reject", [
            'feedback' => 'Please add more specific key results',
        ]);

        $response->assertSuccessful();

        $goal->refresh();
        expect($goal->status)->toBe(GoalStatus::Draft)
            ->and($goal->approval_status)->toBe(GoalApprovalStatus::Rejected)
            ->and($goal->manager_feedback)->toBe('Please add more specific key results');
    });

    it('requires feedback when rejecting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoalApproval($tenant);

        $managerUser = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Admin);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);

        $employeeUser = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'supervisor_id' => $manager->id,
        ]);

        $goal = Goal::factory()->for($employee)->pendingApproval()->create();

        $response = $this->actingAs($managerUser)->postJson("/api/performance/goals/{$goal->id}/reject", [
            'feedback' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['feedback']);
    });

    it('prevents non-managers from approving goals', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoalApproval($tenant);

        $user = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        // Create another employee's goal
        $otherEmployee = Employee::factory()->create();
        $goal = Goal::factory()->for($otherEmployee)->pendingApproval()->create();

        $response = $this->actingAs($user)->postJson("/api/performance/goals/{$goal->id}/approve", [
            'feedback' => 'Approved',
        ]);

        $response->assertForbidden();
    });

    it('cannot submit already approved goal', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoalApproval($tenant);

        $user = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->active()->create([
            'approval_status' => GoalApprovalStatus::Approved,
        ]);

        $response = $this->actingAs($user)->postJson("/api/performance/goals/{$goal->id}/submit-approval");

        $response->assertUnprocessable();
    });
});

describe('Manager Approval Queue', function () {
    it('lists pending approvals for manager', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoalApproval($tenant);

        $managerUser = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Admin);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);

        // Create direct reports with pending goals
        $subordinate1 = Employee::factory()->create(['supervisor_id' => $manager->id]);
        $subordinate2 = Employee::factory()->create(['supervisor_id' => $manager->id]);

        Goal::factory()->for($subordinate1)->pendingApproval()->count(2)->create();
        Goal::factory()->for($subordinate2)->pendingApproval()->create();
        Goal::factory()->for($subordinate1)->active()->create(); // Should not appear

        $response = $this->actingAs($managerUser)->getJson('/api/manager/team-goals/pending-approvals');

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(3);
    });

    it('returns team goal summary statistics', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoalApproval($tenant);

        $managerUser = createTenantUserForGoalApprovalApi($tenant, TenantUserRole::Admin);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);

        $subordinate = Employee::factory()->create(['supervisor_id' => $manager->id]);

        Goal::factory()->for($subordinate)->active()->count(3)->create();
        Goal::factory()->for($subordinate)->completed()->count(2)->create();
        Goal::factory()->for($subordinate)->pendingApproval()->create();

        $response = $this->actingAs($managerUser)->getJson('/api/manager/team-goals/summary');

        $response->assertSuccessful();

        $summary = $response->json('data');
        expect($summary['total_goals'])->toBe(6)
            ->and($summary['active_goals'])->toBe(3)
            ->and($summary['completed_goals'])->toBe(2)
            ->and($summary['pending_approvals'])->toBe(1);
    });
});
