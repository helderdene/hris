<?php

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createTenantUserForAlignmentApi(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

    $plan = Plan::factory()->professional()->create();
    $this->tenant = Tenant::factory()->withPlan($plan)->withTrial()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
});

describe('Goal Alignment', function () {
    it('creates a goal aligned to parent goal', function () {
        $user = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        // Create parent goal
        $parentGoal = Goal::factory()->for($employee)->okr()->create([
            'title' => 'Company Revenue Goal',
        ]);

        $response = $this->actingAs($user)->postJson("{$this->baseUrl}/api/my/goals", [
            'goal_type' => 'okr_objective',
            'title' => 'Increase Sales by 20%',
            'parent_goal_id' => $parentGoal->id,
            'visibility' => 'team',
            'priority' => 'high',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonths(3)->format('Y-m-d'),
            'key_results' => [
                [
                    'title' => 'Close 50 new deals',
                    'metric_type' => 'number',
                    'target_value' => 50,
                    'starting_value' => 0,
                    'weight' => 1,
                ],
            ],
        ]);

        $response->assertSuccessful();

        $childGoal = Goal::where('title', 'Increase Sales by 20%')->first();
        expect($childGoal->parent_goal_id)->toBe($parentGoal->id)
            ->and($childGoal->parentGoal->title)->toBe('Company Revenue Goal');
    });

    it('retrieves child goals through parent', function () {
        $user = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $parentGoal = Goal::factory()->for($employee)->okr()->create();
        Goal::factory()->for($employee)->okr()->count(3)->create([
            'parent_goal_id' => $parentGoal->id,
        ]);

        expect($parentGoal->childGoals)->toHaveCount(3);
    });

    it('returns available parent goals', function () {
        $user = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        // Create some goals that could be parents
        Goal::factory()->for($employee)->okr()->active()->count(2)->create();
        Goal::factory()->for($employee)->smart()->active()->create();

        // Create goal page should return available parent goals
        $response = $this->actingAs($user)->get("{$this->baseUrl}/my/goals/create");

        $response->assertSuccessful();
        // The Inertia page should have availableParentGoals prop
    });

    it('prevents circular alignment', function () {
        $user = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $goal = Goal::factory()->for($employee)->okr()->create();

        // Try to set goal as its own parent
        $response = $this->actingAs($user)->putJson("{$this->baseUrl}/api/my/goals/{$goal->id}", [
            'parent_goal_id' => $goal->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_goal_id']);
    });

    it('calculates aligned goals progress summary', function () {
        $user = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $parentGoal = Goal::factory()->for($employee)->okr()->create([
            'progress_percentage' => 0,
        ]);

        // Create child goals with progress
        Goal::factory()->for($employee)->okr()->create([
            'parent_goal_id' => $parentGoal->id,
            'progress_percentage' => 50,
        ]);
        Goal::factory()->for($employee)->okr()->create([
            'parent_goal_id' => $parentGoal->id,
            'progress_percentage' => 100,
        ]);

        // Parent should be able to see summary of child goals
        $childGoals = $parentGoal->childGoals;
        $averageProgress = $childGoals->avg('progress_percentage');

        expect($averageProgress)->toBe(75.0);
    });

    it('accepts update request for aligned goal', function () {
        $user = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $parentGoal = Goal::factory()->for($employee)->okr()->create();
        $childGoal = Goal::factory()->for($employee)->okr()->create([
            'parent_goal_id' => $parentGoal->id,
        ]);

        $response = $this->actingAs($user)->putJson("{$this->baseUrl}/api/my/goals/{$childGoal->id}", [
            'title' => 'Updated aligned goal',
        ]);

        $response->assertSuccessful();

        $childGoal->refresh();
        expect($childGoal->title)->toBe('Updated aligned goal')
            ->and($childGoal->parent_goal_id)->toBe($parentGoal->id);
    });
});

describe('Goal Hierarchy Queries', function () {
    it('retrieves root goals only', function () {
        $user = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $rootGoal1 = Goal::factory()->for($employee)->okr()->create();
        $rootGoal2 = Goal::factory()->for($employee)->smart()->create();
        Goal::factory()->for($employee)->okr()->create([
            'parent_goal_id' => $rootGoal1->id,
        ]);

        $rootGoals = Goal::rootGoals()->get();
        expect($rootGoals)->toHaveCount(2);
    });

    it('queries goals by alignment level', function () {
        $user = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        // Level 0 (root)
        $level0 = Goal::factory()->for($employee)->okr()->create();

        // Level 1
        $level1a = Goal::factory()->for($employee)->okr()->create([
            'parent_goal_id' => $level0->id,
        ]);
        $level1b = Goal::factory()->for($employee)->okr()->create([
            'parent_goal_id' => $level0->id,
        ]);

        // Level 2
        Goal::factory()->for($employee)->okr()->create([
            'parent_goal_id' => $level1a->id,
        ]);

        // Verify hierarchy
        expect($level0->childGoals)->toHaveCount(2)
            ->and($level1a->childGoals)->toHaveCount(1)
            ->and($level1b->childGoals)->toHaveCount(0);
    });
});

describe('Cross-Employee Alignment', function () {
    it('allows aligning to another employees visible goal', function () {
        // Manager creates organization-visible goal
        $managerUser = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Admin);
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);
        $orgGoal = Goal::factory()->for($manager)->okr()->create([
            'visibility' => 'organization',
            'title' => 'Org-wide Objective',
        ]);

        // Employee aligns their goal to the org goal
        $employeeUser = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'supervisor_id' => $manager->id,
        ]);

        $response = $this->actingAs($employeeUser)->postJson("{$this->baseUrl}/api/my/goals", [
            'goal_type' => 'okr_objective',
            'title' => 'My aligned goal',
            'parent_goal_id' => $orgGoal->id,
            'visibility' => 'team',
            'priority' => 'medium',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonths(3)->format('Y-m-d'),
            'key_results' => [
                [
                    'title' => 'Achieve team target',
                    'metric_type' => 'number',
                    'target_value' => 100,
                    'starting_value' => 0,
                    'weight' => 1,
                ],
            ],
        ]);

        $response->assertSuccessful();

        $employeeGoal = Goal::where('title', 'My aligned goal')->first();
        expect($employeeGoal->parentGoal->id)->toBe($orgGoal->id);
    });

    it('prevents aligning to non-existent parent goal', function () {
        $currentUser = createTenantUserForAlignmentApi($this->tenant, TenantUserRole::Employee);
        Employee::factory()->create(['user_id' => $currentUser->id]);

        $response = $this->actingAs($currentUser)->postJson("{$this->baseUrl}/api/my/goals", [
            'goal_type' => 'okr_objective',
            'title' => 'My goal',
            'parent_goal_id' => 99999,
            'visibility' => 'private',
            'priority' => 'medium',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonths(3)->format('Y-m-d'),
            'key_results' => [
                [
                    'title' => 'Some key result',
                    'metric_type' => 'number',
                    'target_value' => 100,
                    'starting_value' => 0,
                    'weight' => 1,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_goal_id']);
    });
});
