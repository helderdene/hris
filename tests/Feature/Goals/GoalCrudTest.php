<?php

use App\Enums\GoalPriority;
use App\Enums\GoalType;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\GoalKeyResult;
use App\Models\GoalMilestone;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForGoal(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForGoalApi(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

describe('Goal CRUD Operations', function () {
    it('creates an OKR objective with key results', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/my/goals', [
            'goal_type' => 'okr_objective',
            'title' => 'Increase Customer Satisfaction',
            'description' => 'Improve NPS score by Q2',
            'category' => 'Customer Success',
            'visibility' => 'team',
            'priority' => 'high',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonths(3)->format('Y-m-d'),
            'key_results' => [
                [
                    'title' => 'Achieve NPS score of 50+',
                    'metric_type' => 'number',
                    'target_value' => 50,
                    'starting_value' => 35,
                    'weight' => 1,
                ],
                [
                    'title' => 'Reduce support tickets by 20%',
                    'metric_type' => 'percentage',
                    'target_value' => 20,
                    'starting_value' => 0,
                    'weight' => 1,
                ],
            ],
        ]);

        $response->assertSuccessful();

        $goal = Goal::where('title', 'Increase Customer Satisfaction')->first();
        expect($goal)->not->toBeNull()
            ->and($goal->goal_type)->toBe(GoalType::OkrObjective)
            ->and($goal->keyResults)->toHaveCount(2);
    });

    it('creates a SMART goal with milestones', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/my/goals', [
            'goal_type' => 'smart_goal',
            'title' => 'Complete Project Management Certification',
            'description' => 'Obtain PMP certification',
            'category' => 'Professional Development',
            'visibility' => 'private',
            'priority' => 'medium',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonths(6)->format('Y-m-d'),
            'milestones' => [
                [
                    'title' => 'Complete online course',
                    'due_date' => now()->addMonths(2)->format('Y-m-d'),
                ],
                [
                    'title' => 'Pass practice exam',
                    'due_date' => now()->addMonths(4)->format('Y-m-d'),
                ],
                [
                    'title' => 'Take certification exam',
                    'due_date' => now()->addMonths(6)->format('Y-m-d'),
                ],
            ],
        ]);

        $response->assertSuccessful();

        $goal = Goal::where('title', 'Complete Project Management Certification')->first();
        expect($goal)->not->toBeNull()
            ->and($goal->goal_type)->toBe(GoalType::SmartGoal)
            ->and($goal->milestones)->toHaveCount(3);
    });

    it('updates a goal', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->draft()->create();

        $response = $this->actingAs($user)->putJson("/api/my/goals/{$goal->id}", [
            'title' => 'Updated Goal Title',
            'priority' => 'critical',
        ]);

        $response->assertSuccessful();

        $goal->refresh();
        expect($goal->title)->toBe('Updated Goal Title')
            ->and($goal->priority)->toBe(GoalPriority::Critical);
    });

    it('lists employee goals with filters', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        Goal::factory()->for($employee)->okr()->active()->count(3)->create();
        Goal::factory()->for($employee)->smart()->completed()->count(2)->create();

        $response = $this->actingAs($user)->getJson('/api/my/goals');
        $response->assertSuccessful();

        $data = $response->json('data');
        expect($data)->toHaveCount(5);

        // Test filter by goal type
        $response = $this->actingAs($user)->getJson('/api/my/goals?goal_type=okr_objective');
        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(3);

        // Test filter by status
        $response = $this->actingAs($user)->getJson('/api/my/goals?status=completed');
        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('shows goal detail with relationships', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->okr()->create();
        GoalKeyResult::factory()->for($goal)->count(3)->create();

        $response = $this->actingAs($user)->getJson("/api/my/goals/{$goal->id}");

        $response->assertSuccessful();
        $data = $response->json('data');

        expect($data['id'])->toBe($goal->id)
            ->and($data['key_results'])->toHaveCount(3);
    });

    it('deletes a draft goal', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->draft()->create();

        $response = $this->actingAs($user)->deleteJson("/api/my/goals/{$goal->id}");

        $response->assertSuccessful();
        expect(Goal::find($goal->id))->toBeNull();
    });

    it('prevents deletion of active goals', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->active()->create();

        $response = $this->actingAs($user)->deleteJson("/api/my/goals/{$goal->id}");

        $response->assertForbidden();
    });
});

describe('Goal Progress', function () {
    it('calculates OKR progress from key results', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->okr()->create([
            'progress_percentage' => 0,
        ]);

        // Create key results with different achievements
        GoalKeyResult::factory()->for($goal)->create([
            'target_value' => 100,
            'starting_value' => 0,
            'current_value' => 50,
            'weight' => 1,
        ]);
        GoalKeyResult::factory()->for($goal)->create([
            'target_value' => 100,
            'starting_value' => 0,
            'current_value' => 100,
            'weight' => 1,
        ]);

        $goal->calculateProgress();
        $goal->refresh();

        // Average of 50% and 100% = 75%
        expect($goal->progress_percentage)->toBe(75.0);
    });

    it('calculates SMART goal progress from milestones', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->smart()->create([
            'progress_percentage' => 0,
        ]);

        // Create 4 milestones, 2 completed
        GoalMilestone::factory()->for($goal)->completed()->count(2)->create();
        GoalMilestone::factory()->for($goal)->count(2)->create();

        $goal->calculateProgress();
        $goal->refresh();

        // 2 of 4 milestones complete = 50%
        expect($goal->progress_percentage)->toBe(50.0);
    });

    it('records progress entry for key result', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->okr()->create();
        $keyResult = GoalKeyResult::factory()->for($goal)->create([
            'target_value' => 100,
            'starting_value' => 0,
            'current_value' => 0,
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/performance/goals/{$goal->id}/key-results/{$keyResult->id}/progress",
            [
                'progress_value' => 75,
                'notes' => 'Good progress this month',
            ]
        );

        $response->assertSuccessful();

        $keyResult->refresh();
        expect($keyResult->current_value)->toBe(75.0)
            ->and($keyResult->progressEntries)->toHaveCount(1);
    });

    it('toggles milestone completion', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $goal = Goal::factory()->for($employee)->smart()->create();
        $milestone = GoalMilestone::factory()->for($goal)->create(['is_completed' => false]);

        $response = $this->actingAs($user)->postJson(
            "/api/performance/goals/{$goal->id}/milestones/{$milestone->id}/toggle"
        );

        $response->assertSuccessful();

        $milestone->refresh();
        expect($milestone->is_completed)->toBeTrue()
            ->and($milestone->completed_at)->not->toBeNull();

        // Toggle back
        $response = $this->actingAs($user)->postJson(
            "/api/performance/goals/{$goal->id}/milestones/{$milestone->id}/toggle"
        );

        $milestone->refresh();
        expect($milestone->is_completed)->toBeFalse();
    });
});

describe('Goal Validation', function () {
    it('requires title and dates', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/my/goals', [
            'goal_type' => 'okr_objective',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'start_date', 'due_date']);
    });

    it('validates due date is after start date', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/my/goals', [
            'goal_type' => 'okr_objective',
            'title' => 'Test Goal',
            'start_date' => now()->addMonth()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date']);
    });

    it('validates goal type enum', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForGoal($tenant);

        $user = createTenantUserForGoalApi($tenant, TenantUserRole::Admin);
        Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/my/goals', [
            'goal_type' => 'invalid_type',
            'title' => 'Test Goal',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['goal_type']);
    });
});
