<?php

use App\Actions\ConvertToEmployeeAction;
use App\Enums\ApplicationStatus;
use App\Enums\OfferStatus;
use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Enums\OnboardingItemStatus;
use App\Enums\OnboardingStatus;
use App\Enums\PreboardingStatus;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingChecklistItem;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use App\Models\PreboardingChecklist;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    Gate::define('can-manage-employees', fn () => true);
    Gate::define('can-manage-organization', fn () => true);
    Gate::define('can-manage-onboarding', fn () => true);
});

/*
|--------------------------------------------------------------------------
| Enum Tests
|--------------------------------------------------------------------------
*/

it('has correct onboarding status labels and colors', function () {
    expect(OnboardingStatus::Pending->label())->toBe('Pending');
    expect(OnboardingStatus::InProgress->label())->toBe('In Progress');
    expect(OnboardingStatus::Completed->label())->toBe('Completed');
    expect(OnboardingStatus::Overdue->label())->toBe('Overdue');
    expect(OnboardingStatus::Completed->color())->toBe('green');
    expect(OnboardingStatus::Overdue->color())->toBe('red');
});

it('has correct onboarding item status labels and colors', function () {
    expect(OnboardingItemStatus::Pending->label())->toBe('Pending');
    expect(OnboardingItemStatus::InProgress->label())->toBe('In Progress');
    expect(OnboardingItemStatus::Completed->label())->toBe('Completed');
    expect(OnboardingItemStatus::Skipped->label())->toBe('Skipped');
    expect(OnboardingItemStatus::Completed->color())->toBe('green');
});

it('has correct onboarding category labels and icons', function () {
    expect(OnboardingCategory::Provisioning->label())->toBe('Account Provisioning');
    expect(OnboardingCategory::Equipment->label())->toBe('Equipment');
    expect(OnboardingCategory::Orientation->label())->toBe('Orientation');
    expect(OnboardingCategory::Training->label())->toBe('Training');
    expect(OnboardingCategory::Provisioning->icon())->toBe('key');
});

it('has correct default roles for categories', function () {
    expect(OnboardingCategory::Provisioning->defaultRole())->toBe(OnboardingAssignedRole::IT);
    expect(OnboardingCategory::Equipment->defaultRole())->toBe(OnboardingAssignedRole::Admin);
    expect(OnboardingCategory::Orientation->defaultRole())->toBe(OnboardingAssignedRole::HR);
    expect(OnboardingCategory::Training->defaultRole())->toBe(OnboardingAssignedRole::HR);
});

/*
|--------------------------------------------------------------------------
| Model Tests
|--------------------------------------------------------------------------
*/

it('creates an onboarding template with items', function () {
    $template = OnboardingTemplate::factory()->create();
    $items = OnboardingTemplateItem::factory()
        ->count(3)
        ->for($template, 'template')
        ->create();

    expect($template->items)->toHaveCount(3);
    expect($template->items->first()->template->id)->toBe($template->id);
});

it('creates an onboarding checklist with items', function () {
    $employee = Employee::factory()->create();
    $checklist = OnboardingChecklist::factory()
        ->for($employee, 'employee')
        ->create();
    $items = OnboardingChecklistItem::factory()
        ->count(5)
        ->for($checklist, 'checklist')
        ->create();

    expect($checklist->items)->toHaveCount(5);
    expect($checklist->employee)->not->toBeNull();
});

it('calculates progress percentage correctly', function () {
    $checklist = OnboardingChecklist::factory()->create();

    OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->completed()
        ->create(['is_required' => true]);

    OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->create(['is_required' => true, 'status' => OnboardingItemStatus::Pending]);

    $checklist->refresh();

    expect($checklist->progress_percentage)->toBe(50);
});

it('returns 100 percent when all required items are completed', function () {
    $checklist = OnboardingChecklist::factory()->create();

    OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->completed()
        ->count(3)
        ->create(['is_required' => true]);

    // Optional item not completed
    OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->create(['is_required' => false, 'status' => OnboardingItemStatus::Pending]);

    $checklist->refresh();

    expect($checklist->progress_percentage)->toBe(100);
});

it('detects overdue items correctly', function () {
    $checklist = OnboardingChecklist::factory()->create();

    $overdueItem = OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->create([
            'due_date' => now()->subDay(),
            'status' => OnboardingItemStatus::Pending,
        ]);

    $futureItem = OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->create([
            'due_date' => now()->addWeek(),
            'status' => OnboardingItemStatus::Pending,
        ]);

    expect($overdueItem->is_overdue)->toBeTrue();
    expect($futureItem->is_overdue)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Service Tests
|--------------------------------------------------------------------------
*/

it('creates a checklist from employee', function () {
    Notification::fake();

    $template = OnboardingTemplate::factory()->default()->create();
    OnboardingTemplateItem::factory()
        ->count(4)
        ->for($template, 'template')
        ->sequence(
            ['category' => OnboardingCategory::Provisioning, 'due_days_offset' => 0],
            ['category' => OnboardingCategory::Equipment, 'due_days_offset' => -1],
            ['category' => OnboardingCategory::Orientation, 'due_days_offset' => 1],
            ['category' => OnboardingCategory::Training, 'due_days_offset' => 7],
        )
        ->create();

    $employee = Employee::factory()->create([
        'hire_date' => now()->addDays(14),
    ]);

    $service = app(OnboardingService::class);
    $checklist = $service->createFromEmployee($employee, $template);

    expect($checklist)->toBeInstanceOf(OnboardingChecklist::class);
    expect($checklist->items)->toHaveCount(4);
    expect($checklist->status)->toBe(OnboardingStatus::Pending);
    expect($checklist->employee_id)->toBe($employee->id);
    expect($checklist->start_date->toDateString())->toBe($employee->hire_date->toDateString());
});

it('creates checklist using default template when none specified', function () {
    Notification::fake();

    $template = OnboardingTemplate::factory()->default()->create();
    OnboardingTemplateItem::factory()
        ->count(2)
        ->for($template, 'template')
        ->create();

    $employee = Employee::factory()->create();

    $service = app(OnboardingService::class);
    $checklist = $service->createFromEmployee($employee);

    expect($checklist->items)->toHaveCount(2);
});

it('calculates due dates from offset correctly', function () {
    Notification::fake();

    $template = OnboardingTemplate::factory()->default()->create();
    OnboardingTemplateItem::factory()
        ->for($template, 'template')
        ->create(['due_days_offset' => -2]); // 2 days before start

    OnboardingTemplateItem::factory()
        ->for($template, 'template')
        ->create(['due_days_offset' => 0]); // On start date

    OnboardingTemplateItem::factory()
        ->for($template, 'template')
        ->create(['due_days_offset' => 5]); // 5 days after start

    $startDate = now()->addDays(14);
    $employee = Employee::factory()->create(['hire_date' => $startDate]);

    $service = app(OnboardingService::class);
    $checklist = $service->createFromEmployee($employee, $template);

    $items = $checklist->items->sortBy('due_date');

    expect($items->first()->due_date->toDateString())->toBe($startDate->copy()->subDays(2)->toDateString());
    expect($items->skip(1)->first()->due_date->toDateString())->toBe($startDate->toDateString());
    expect($items->last()->due_date->toDateString())->toBe($startDate->copy()->addDays(5)->toDateString());
});

it('completes an item', function () {
    $this->actingAs($this->user);

    $item = OnboardingChecklistItem::factory()->create([
        'status' => OnboardingItemStatus::Pending,
    ]);

    $service = app(OnboardingService::class);
    $updated = $service->completeItem($item, $this->user, ['notes' => 'Account created']);

    expect($updated->status)->toBe(OnboardingItemStatus::Completed);
    expect($updated->notes)->toBe('Account created');
    expect($updated->completed_at)->not->toBeNull();
    expect($updated->completed_by)->toBe($this->user->id);
});

it('completes an equipment item with details', function () {
    $this->actingAs($this->user);

    $item = OnboardingChecklistItem::factory()
        ->category(OnboardingCategory::Equipment)
        ->create([
            'status' => OnboardingItemStatus::Pending,
        ]);

    $equipmentDetails = [
        'model' => 'MacBook Pro 14"',
        'serial_number' => 'C02XY1234567',
        'asset_tag' => 'ASSET-001',
    ];

    $service = app(OnboardingService::class);
    $updated = $service->completeItem($item, $this->user, ['equipment_details' => $equipmentDetails]);

    expect($updated->status)->toBe(OnboardingItemStatus::Completed);
    expect($updated->equipment_details)->toBe($equipmentDetails);
});

it('skips a non-required item with reason', function () {
    $this->actingAs($this->user);

    $item = OnboardingChecklistItem::factory()->create([
        'is_required' => false,
        'status' => OnboardingItemStatus::Pending,
    ]);

    $service = app(OnboardingService::class);
    $updated = $service->skipItem($item, $this->user, 'Employee already has equipment');

    expect($updated->status)->toBe(OnboardingItemStatus::Skipped);
    expect($updated->notes)->toContain('Employee already has equipment');
});

it('prevents skipping required items', function () {
    $this->actingAs($this->user);

    $item = OnboardingChecklistItem::factory()->create([
        'is_required' => true,
        'status' => OnboardingItemStatus::Pending,
    ]);

    $service = app(OnboardingService::class);

    expect(fn () => $service->skipItem($item, $this->user, 'Some reason'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('auto-completes checklist when all required items are completed', function () {
    Notification::fake();

    $this->actingAs($this->user);

    $checklist = OnboardingChecklist::factory()->create([
        'status' => OnboardingStatus::InProgress,
    ]);

    // One already completed
    OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->completed()
        ->create(['is_required' => true]);

    // One pending (about to be completed)
    $lastItem = OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->create([
            'is_required' => true,
            'status' => OnboardingItemStatus::Pending,
        ]);

    $service = app(OnboardingService::class);
    $service->completeItem($lastItem, $this->user);

    $checklist->refresh();

    expect($checklist->status)->toBe(OnboardingStatus::Completed);
    expect($checklist->completed_at)->not->toBeNull();
});

it('gets items by role', function () {
    $checklist = OnboardingChecklist::factory()->create();

    OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->count(2)
        ->create(['assigned_role' => OnboardingAssignedRole::IT]);

    OnboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->count(3)
        ->create(['assigned_role' => OnboardingAssignedRole::HR]);

    $service = app(OnboardingService::class);
    $itItems = $service->getItemsForRole($checklist, OnboardingAssignedRole::IT);
    $hrItems = $service->getItemsForRole($checklist, OnboardingAssignedRole::HR);

    expect($itItems)->toHaveCount(2);
    expect($hrItems)->toHaveCount(3);
});

/*
|--------------------------------------------------------------------------
| Integration with ConvertToEmployeeAction
|--------------------------------------------------------------------------
*/

it('creates onboarding checklist when employee is converted', function () {
    Notification::fake();

    // Create default onboarding template
    $template = OnboardingTemplate::factory()->default()->create();
    OnboardingTemplateItem::factory()
        ->count(3)
        ->for($template, 'template')
        ->create();

    // Set up preboarding scenario
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
    $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
        'job_application_id' => $application->id,
        'start_date' => now()->addDays(14),
    ]);
    $preboardingChecklist = PreboardingChecklist::factory()->create([
        'job_application_id' => $application->id,
        'offer_id' => $offer->id,
        'status' => PreboardingStatus::Completed,
        'completed_at' => now(),
    ]);

    // Create candidate user
    User::factory()->create(['email' => $application->candidate->email]);

    // Execute conversion
    $action = new ConvertToEmployeeAction;
    $employee = $action->execute($preboardingChecklist);

    // Verify onboarding checklist was created
    $onboardingChecklist = OnboardingChecklist::where('employee_id', $employee->id)->first();

    expect($onboardingChecklist)->not->toBeNull();
    expect($onboardingChecklist->items)->toHaveCount(3);
    expect($onboardingChecklist->status)->toBe(OnboardingStatus::Pending);
    expect($onboardingChecklist->start_date->toDateString())->toBe($employee->hire_date->toDateString());
});

it('does not create onboarding checklist when no template exists', function () {
    Notification::fake();

    // No template created

    $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
    $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
        'job_application_id' => $application->id,
    ]);
    $preboardingChecklist = PreboardingChecklist::factory()->create([
        'job_application_id' => $application->id,
        'offer_id' => $offer->id,
        'status' => PreboardingStatus::Completed,
    ]);

    $action = new ConvertToEmployeeAction;
    $employee = $action->execute($preboardingChecklist);

    // Employee should still be created
    expect($employee)->toBeInstanceOf(Employee::class);

    // But no onboarding checklist
    expect(OnboardingChecklist::where('employee_id', $employee->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| API Controller Tests
|--------------------------------------------------------------------------
*/

it('completes an item via API', function () {
    $this->actingAs($this->user);

    $item = OnboardingChecklistItem::factory()->create([
        'status' => OnboardingItemStatus::Pending,
    ]);

    $response = $this->postJson("{$this->baseUrl}/api/onboarding-items/{$item->id}/complete", [
        'notes' => 'Email account created',
    ]);

    $response->assertSuccessful();
    $item->refresh();
    expect($item->status)->toBe(OnboardingItemStatus::Completed);
    expect($item->notes)->toBe('Email account created');
});

it('skips an item via API', function () {
    $this->actingAs($this->user);

    $item = OnboardingChecklistItem::factory()->create([
        'is_required' => false,
        'status' => OnboardingItemStatus::Pending,
    ]);

    $response = $this->postJson("{$this->baseUrl}/api/onboarding-items/{$item->id}/skip", [
        'reason' => 'Not applicable for this role',
    ]);

    $response->assertSuccessful();
    $item->refresh();
    expect($item->status)->toBe(OnboardingItemStatus::Skipped);
});

it('assigns an item to a user via API', function () {
    $this->actingAs($this->user);

    $assignee = User::factory()->create();
    $item = OnboardingChecklistItem::factory()->create([
        'status' => OnboardingItemStatus::Pending,
    ]);

    $response = $this->postJson("{$this->baseUrl}/api/onboarding-items/{$item->id}/assign", [
        'assigned_to' => $assignee->id,
    ]);

    $response->assertSuccessful();
    $item->refresh();
    expect($item->assigned_to)->toBe($assignee->id);
});

/*
|--------------------------------------------------------------------------
| Page Controller Tests
|--------------------------------------------------------------------------
*/

it('renders onboarding index page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    $checklist = OnboardingChecklist::factory()->create();

    $response = $this->get("{$this->baseUrl}/onboarding");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Onboarding/Index')
        ->has('checklists.data')
    );
});

it('renders onboarding show page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    $checklist = OnboardingChecklist::factory()->create();
    OnboardingChecklistItem::factory()
        ->count(3)
        ->for($checklist, 'checklist')
        ->create();

    $response = $this->get("{$this->baseUrl}/onboarding/{$checklist->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Onboarding/Show')
        ->has('checklist')
        ->where('checklist.id', $checklist->id)
    );
});

it('renders my onboarding page for employee', function () {
    $this->withoutVite();
    $employee = Employee::factory()->create(['user_id' => $this->user->id]);
    $checklist = OnboardingChecklist::factory()
        ->for($employee, 'employee')
        ->create();
    OnboardingChecklistItem::factory()
        ->count(3)
        ->for($checklist, 'checklist')
        ->create();

    $this->actingAs($this->user);

    $response = $this->get("{$this->baseUrl}/my/onboarding");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('My/Onboarding/Index')
        ->has('checklist')
    );
});

it('renders onboarding tasks page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    $checklist = OnboardingChecklist::factory()->create();
    OnboardingChecklistItem::factory()
        ->count(3)
        ->for($checklist, 'checklist')
        ->create(['assigned_role' => OnboardingAssignedRole::HR]);

    $response = $this->get("{$this->baseUrl}/onboarding-tasks");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Onboarding/Tasks/Index')
        ->has('tasks')
    );
});
