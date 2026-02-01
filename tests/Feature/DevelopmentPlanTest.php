<?php

use App\Enums\DevelopmentActivityType;
use App\Enums\DevelopmentItemStatus;
use App\Enums\DevelopmentPlanStatus;
use App\Enums\GoalPriority;
use App\Enums\TenantUserRole;
use App\Models\DevelopmentActivity;
use App\Models\DevelopmentPlan;
use App\Models\DevelopmentPlanCheckIn;
use App\Models\DevelopmentPlanItem;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DevelopmentPlanService;
use Illuminate\Support\Facades\Artisan;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Set up the main domain for the tests
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Create a tenant and bind to context
    $this->tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    app()->instance('tenant', $this->tenant);

    // Create user with tenant access
    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

describe('DevelopmentPlan Model', function () {
    it('can create a development plan', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'title' => 'Q1 Development Plan',
            'status' => DevelopmentPlanStatus::Draft,
        ]);

        expect($plan)->toBeInstanceOf(DevelopmentPlan::class)
            ->and($plan->title)->toBe('Q1 Development Plan')
            ->and($plan->status)->toBe(DevelopmentPlanStatus::Draft);
    });

    it('belongs to an employee', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        expect($plan->employee)->toBeInstanceOf(Employee::class)
            ->and($plan->employee->id)->toBe($this->employee->id);
    });

    it('can calculate progress from items', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
            'progress_percentage' => 50,
        ]);

        DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
            'progress_percentage' => 100,
        ]);

        expect($plan->calculateProgress())->toBe(75.0);
    });

    it('returns zero progress when no items exist', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        expect($plan->calculateProgress())->toBe(0.0);
    });

    it('can submit for approval', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::Draft,
        ]);

        $plan->submit();

        expect($plan->fresh()->status)->toBe(DevelopmentPlanStatus::PendingApproval);
    });

    it('can be approved', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::PendingApproval,
        ]);

        $approver = User::factory()->create();
        $plan->approve($approver, 'Looks good!');

        $plan->refresh();

        expect($plan->status)->toBe(DevelopmentPlanStatus::Approved)
            ->and($plan->approved_by)->toBe($approver->id)
            ->and($plan->approval_notes)->toBe('Looks good!')
            ->and($plan->approved_at)->not->toBeNull();
    });

    it('can be rejected', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::PendingApproval,
        ]);

        $approver = User::factory()->create();
        $plan->reject($approver, 'Please add more details.');

        $plan->refresh();

        expect($plan->status)->toBe(DevelopmentPlanStatus::Draft)
            ->and($plan->approval_notes)->toBe('Please add more details.');
    });

    it('can be started', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::Approved,
        ]);

        $plan->start();

        expect($plan->fresh()->status)->toBe(DevelopmentPlanStatus::InProgress)
            ->and($plan->start_date)->not->toBeNull();
    });

    it('can be completed', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::InProgress,
        ]);

        $plan->complete();

        $plan->refresh();

        expect($plan->status)->toBe(DevelopmentPlanStatus::Completed)
            ->and($plan->completed_at)->not->toBeNull();
    });

    it('detects overdue status', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::InProgress,
            'target_completion_date' => now()->subDays(5),
        ]);

        expect($plan->isOverdue())->toBeTrue();
    });

    it('is not overdue when completed', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::Completed,
            'target_completion_date' => now()->subDays(5),
        ]);

        expect($plan->isOverdue())->toBeFalse();
    });
});

describe('DevelopmentPlanItem Model', function () {
    it('can create an item', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
            'title' => 'Improve Communication',
            'priority' => GoalPriority::High,
            'status' => DevelopmentItemStatus::NotStarted,
        ]);

        expect($item->title)->toBe('Improve Communication')
            ->and($item->priority)->toBe(GoalPriority::High)
            ->and($item->status)->toBe(DevelopmentItemStatus::NotStarted);
    });

    it('calculates proficiency gap', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
            'current_level' => 2,
            'target_level' => 4,
        ]);

        expect($item->getProficiencyGap())->toBe(2);
    });

    it('updates progress from activities', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
        ]);

        DevelopmentActivity::factory()->create([
            'development_plan_item_id' => $item->id,
            'is_completed' => true,
        ]);

        DevelopmentActivity::factory()->create([
            'development_plan_item_id' => $item->id,
            'is_completed' => false,
        ]);

        $item->updateProgress();
        $item->refresh();

        expect($item->progress_percentage)->toBe(50)
            ->and($item->status)->toBe(DevelopmentItemStatus::InProgress);
    });

    it('marks as completed when all activities are done', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
        ]);

        DevelopmentActivity::factory()->create([
            'development_plan_item_id' => $item->id,
            'is_completed' => true,
        ]);

        $item->updateProgress();
        $item->refresh();

        expect($item->progress_percentage)->toBe(100)
            ->and($item->status)->toBe(DevelopmentItemStatus::Completed)
            ->and($item->completed_at)->not->toBeNull();
    });
});

describe('DevelopmentActivity Model', function () {
    it('can be marked as completed', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
        ]);

        $activity = DevelopmentActivity::factory()->create([
            'development_plan_item_id' => $item->id,
            'is_completed' => false,
        ]);

        $activity->markCompleted('Finished the course.');
        $activity->refresh();

        expect($activity->is_completed)->toBeTrue()
            ->and($activity->completion_notes)->toBe('Finished the course.')
            ->and($activity->completed_at)->not->toBeNull();
    });

    it('detects overdue activities', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
        ]);

        $activity = DevelopmentActivity::factory()->create([
            'development_plan_item_id' => $item->id,
            'due_date' => now()->subDays(3),
            'is_completed' => false,
        ]);

        expect($activity->isOverdue())->toBeTrue();
    });
});

describe('DevelopmentPlanService', function () {
    it('creates a plan', function () {
        $service = app(DevelopmentPlanService::class);

        $plan = $service->createPlan(
            $this->employee,
            [
                'title' => 'Test Plan',
                'description' => 'A test development plan',
            ],
            $this->user
        );

        expect($plan)->toBeInstanceOf(DevelopmentPlan::class)
            ->and($plan->title)->toBe('Test Plan')
            ->and($plan->status)->toBe(DevelopmentPlanStatus::Draft)
            ->and($plan->created_by)->toBe($this->user->id);
    });

    it('adds an item to a plan', function () {
        $service = app(DevelopmentPlanService::class);

        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = $service->addItem($plan, [
            'title' => 'Learn TypeScript',
            'description' => 'Improve TypeScript skills',
            'priority' => 'high',
        ]);

        expect($item)->toBeInstanceOf(DevelopmentPlanItem::class)
            ->and($item->title)->toBe('Learn TypeScript')
            ->and($item->priority)->toBe(GoalPriority::High);
    });

    it('adds an activity to an item', function () {
        $service = app(DevelopmentPlanService::class);

        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
        ]);

        $activity = $service->addActivity($item, [
            'activity_type' => 'training',
            'title' => 'Complete online course',
        ]);

        expect($activity)->toBeInstanceOf(DevelopmentActivity::class)
            ->and($activity->title)->toBe('Complete online course')
            ->and($activity->activity_type)->toBe(DevelopmentActivityType::Training);
    });

    it('adds a check-in', function () {
        $service = app(DevelopmentPlanService::class);

        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $checkIn = $service->addCheckIn($plan, [
            'check_in_date' => now()->toDateString(),
            'notes' => 'Discussed progress on goals.',
        ], $this->user);

        expect($checkIn)->toBeInstanceOf(DevelopmentPlanCheckIn::class)
            ->and($checkIn->notes)->toBe('Discussed progress on goals.')
            ->and($checkIn->created_by)->toBe($this->user->id);
    });

    it('calculates statistics', function () {
        $service = app(DevelopmentPlanService::class);

        DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::Approved,
        ]);

        DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::Completed,
        ]);

        $stats = $service->getStatistics($this->employee);

        expect($stats['total'])->toBe(2)
            ->and($stats['active'])->toBe(1)
            ->and($stats['completed'])->toBe(1);
    });
});

describe('API Endpoints', function () {
    it('can list employee development plans', function () {
        DevelopmentPlan::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
        ]);

        $controller = app(\App\Http\Controllers\My\MyDevelopmentPlanController::class);
        $request = \Illuminate\Http\Request::create('/my/development-plans', 'GET');
        $request->setUserResolver(fn () => $this->user);
        app()->instance('request', $request);

        $response = $controller->index($request, 'test-tenant');

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
    });

    it('can create a development plan via service', function () {
        $service = app(DevelopmentPlanService::class);

        $plan = $service->createPlan(
            $this->employee,
            [
                'title' => 'New Development Plan',
                'description' => 'My goals for the quarter',
            ],
            $this->user
        );

        expect($plan)->toBeInstanceOf(DevelopmentPlan::class);

        $this->assertDatabaseHas('development_plans', [
            'title' => 'New Development Plan',
            'employee_id' => $this->employee->id,
        ]);
    });

    it('can view a specific development plan', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'title' => 'My Plan',
        ]);

        $controller = app(\App\Http\Controllers\My\MyDevelopmentPlanController::class);
        $request = \Illuminate\Http\Request::create("/my/development-plans/{$plan->id}", 'GET');
        $request->setUserResolver(fn () => $this->user);
        app()->instance('request', $request);

        $response = $controller->show($request, 'test-tenant', $plan);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
    });

    it('cannot view another employee plan', function () {
        $otherEmployee = Employee::factory()->create();
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $otherEmployee->id,
        ]);

        $controller = app(\App\Http\Controllers\My\MyDevelopmentPlanController::class);
        $request = \Illuminate\Http\Request::create("/my/development-plans/{$plan->id}", 'GET');
        $request->setUserResolver(fn () => $this->user);
        app()->instance('request', $request);

        expect(fn () => $controller->show($request, 'test-tenant', $plan))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('can submit plan for approval via model', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::Draft,
        ]);

        // Add at least one item
        DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
        ]);

        $plan->submit();

        expect($plan->fresh()->status)->toBe(DevelopmentPlanStatus::PendingApproval);
    });

    it('can add an item to a plan via service', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $service = app(DevelopmentPlanService::class);
        $item = $service->addItem($plan, [
            'title' => 'New Skill',
            'priority' => 'high',
        ]);

        expect($item)->toBeInstanceOf(DevelopmentPlanItem::class);
        expect($item->title)->toBe('New Skill');
    });

    it('can add an activity to an item via service', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
        ]);

        $service = app(DevelopmentPlanService::class);
        $activity = $service->addActivity($item, [
            'activity_type' => 'training',
            'title' => 'Complete Training',
        ]);

        expect($activity)->toBeInstanceOf(DevelopmentActivity::class);
        expect($activity->title)->toBe('Complete Training');
    });

    it('can complete an activity via model', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
            'status' => DevelopmentPlanStatus::InProgress,
        ]);

        $item = DevelopmentPlanItem::factory()->create([
            'development_plan_id' => $plan->id,
        ]);

        $activity = DevelopmentActivity::factory()->create([
            'development_plan_item_id' => $item->id,
            'is_completed' => false,
        ]);

        $activity->markCompleted('Done!');

        expect($activity->fresh()->is_completed)->toBeTrue();
        expect($activity->fresh()->completion_notes)->toBe('Done!');
    });

    it('can add a check-in via service', function () {
        $plan = DevelopmentPlan::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $service = app(DevelopmentPlanService::class);
        $checkIn = $service->addCheckIn($plan, [
            'check_in_date' => now()->toDateString(),
            'notes' => 'Great progress this week.',
        ], $this->user);

        expect($checkIn)->toBeInstanceOf(DevelopmentPlanCheckIn::class);
        expect($checkIn->notes)->toBe('Great progress this week.');
    });
});
