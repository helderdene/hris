<?php

use App\Enums\PreboardingItemStatus;
use App\Enums\PreboardingItemType;
use App\Enums\PreboardingStatus;
use App\Enums\TenantUserRole;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Models\PreboardingChecklist;
use App\Models\PreboardingChecklistItem;
use App\Models\PreboardingTemplate;
use App\Models\PreboardingTemplateItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PreboardingService;
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
    Gate::define('can-manage-organization', fn () => true);
});

/*
|--------------------------------------------------------------------------
| Enum Tests
|--------------------------------------------------------------------------
*/

it('has correct preboarding status labels and colors', function () {
    expect(PreboardingStatus::Pending->label())->toBe('Pending');
    expect(PreboardingStatus::Completed->color())->toBe('green');
    expect(PreboardingStatus::Overdue->color())->toBe('red');
    expect(PreboardingStatus::Completed->isTerminal())->toBeTrue();
    expect(PreboardingStatus::InProgress->isTerminal())->toBeFalse();
});

it('has correct preboarding item status transitions', function () {
    expect(PreboardingItemStatus::Pending->allowedTransitions())
        ->toContain(PreboardingItemStatus::Submitted);
    expect(PreboardingItemStatus::Submitted->allowedTransitions())
        ->toContain(PreboardingItemStatus::Approved)
        ->toContain(PreboardingItemStatus::Rejected);
    expect(PreboardingItemStatus::Approved->allowedTransitions())->toBeEmpty();
    expect(PreboardingItemStatus::Rejected->allowedTransitions())
        ->toContain(PreboardingItemStatus::Submitted);
});

it('has correct preboarding item type labels', function () {
    expect(PreboardingItemType::DocumentUpload->label())->toBe('Document Upload');
    expect(PreboardingItemType::FormField->label())->toBe('Form Field');
    expect(PreboardingItemType::Acknowledgment->label())->toBe('Acknowledgment');
});

/*
|--------------------------------------------------------------------------
| Model Tests
|--------------------------------------------------------------------------
*/

it('creates a preboarding template with items', function () {
    $template = PreboardingTemplate::factory()->create();
    $items = PreboardingTemplateItem::factory()
        ->count(3)
        ->for($template, 'template')
        ->create();

    expect($template->items)->toHaveCount(3);
    expect($template->items->first()->template->id)->toBe($template->id);
});

it('creates a preboarding checklist with items', function () {
    $checklist = PreboardingChecklist::factory()->create();
    $items = PreboardingChecklistItem::factory()
        ->count(5)
        ->for($checklist, 'checklist')
        ->create();

    expect($checklist->items)->toHaveCount(5);
    expect($checklist->offer)->not->toBeNull();
    expect($checklist->jobApplication)->not->toBeNull();
});

it('calculates progress percentage correctly', function () {
    $checklist = PreboardingChecklist::factory()->create();

    PreboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->approved()
        ->create(['is_required' => true]);

    PreboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->create(['is_required' => true, 'status' => PreboardingItemStatus::Pending]);

    $checklist->refresh();

    expect($checklist->progress_percentage)->toBe(50);
});

it('returns 100 percent when all required items are approved', function () {
    $checklist = PreboardingChecklist::factory()->create();

    PreboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->approved()
        ->count(3)
        ->create(['is_required' => true]);

    // Optional item not approved
    PreboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->create(['is_required' => false, 'status' => PreboardingItemStatus::Pending]);

    $checklist->refresh();

    expect($checklist->progress_percentage)->toBe(100);
});

/*
|--------------------------------------------------------------------------
| Service Tests
|--------------------------------------------------------------------------
*/

it('creates a checklist from template', function () {
    Notification::fake();

    $template = PreboardingTemplate::factory()->default()->create();
    PreboardingTemplateItem::factory()
        ->count(3)
        ->for($template, 'template')
        ->create();

    $offer = Offer::factory()->create([
        'start_date' => now()->addDays(30),
    ]);

    $service = app(PreboardingService::class);
    $checklist = $service->createFromTemplate($offer, $template);

    expect($checklist)->toBeInstanceOf(PreboardingChecklist::class);
    expect($checklist->items)->toHaveCount(3);
    expect($checklist->status)->toBe(PreboardingStatus::Pending);
    expect($checklist->offer_id)->toBe($offer->id);
});

it('creates checklist using default template when none specified', function () {
    Notification::fake();

    $template = PreboardingTemplate::factory()->default()->create();
    PreboardingTemplateItem::factory()
        ->count(2)
        ->for($template, 'template')
        ->create();

    $offer = Offer::factory()->create();

    $service = app(PreboardingService::class);
    $checklist = $service->createFromTemplate($offer);

    expect($checklist->items)->toHaveCount(2);
});

it('submits a form field item', function () {
    $item = PreboardingChecklistItem::factory()->create([
        'type' => PreboardingItemType::FormField,
        'status' => PreboardingItemStatus::Pending,
    ]);

    $service = app(PreboardingService::class);
    $updated = $service->submitItem($item, ['form_value' => '123-456-789']);

    expect($updated->status)->toBe(PreboardingItemStatus::Submitted);
    expect($updated->form_value)->toBe('123-456-789');
    expect($updated->submitted_at)->not->toBeNull();
});

it('submits an acknowledgment item', function () {
    $item = PreboardingChecklistItem::factory()->create([
        'type' => PreboardingItemType::Acknowledgment,
        'status' => PreboardingItemStatus::Pending,
    ]);

    $service = app(PreboardingService::class);
    $updated = $service->submitItem($item, []);

    expect($updated->status)->toBe(PreboardingItemStatus::Submitted);
});

it('rejects submitting an already approved item', function () {
    $item = PreboardingChecklistItem::factory()->approved()->create();

    $service = app(PreboardingService::class);

    expect(fn () => $service->submitItem($item, []))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('approves a submitted item', function () {
    $this->actingAs($this->user);

    $item = PreboardingChecklistItem::factory()->submitted()->create();

    $service = app(PreboardingService::class);
    $updated = $service->approveItem($item);

    expect($updated->status)->toBe(PreboardingItemStatus::Approved);
    expect($updated->reviewed_at)->not->toBeNull();
    expect($updated->reviewed_by)->toBe($this->user->id);
});

it('rejects a submitted item with reason', function () {
    Notification::fake();

    $this->actingAs($this->user);

    $item = PreboardingChecklistItem::factory()->submitted()->create();
    $item->checklist->load('offer.jobApplication.candidate');

    $service = app(PreboardingService::class);
    $updated = $service->rejectItem($item, 'Document is illegible');

    expect($updated->status)->toBe(PreboardingItemStatus::Rejected);
    expect($updated->rejection_reason)->toBe('Document is illegible');
});

it('auto-completes checklist when all required items are approved', function () {
    Notification::fake();

    $this->actingAs($this->user);

    $checklist = PreboardingChecklist::factory()->create([
        'status' => PreboardingStatus::InProgress,
        'created_by' => $this->user->id,
    ]);

    // One already approved
    PreboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->approved()
        ->create(['is_required' => true]);

    // One submitted (about to be approved)
    $lastItem = PreboardingChecklistItem::factory()
        ->for($checklist, 'checklist')
        ->submitted()
        ->create(['is_required' => true]);

    $service = app(PreboardingService::class);
    $service->approveItem($lastItem);

    $checklist->refresh();

    expect($checklist->status)->toBe(PreboardingStatus::Completed);
    expect($checklist->completed_at)->not->toBeNull();
});

it('allows re-submission of rejected items', function () {
    $item = PreboardingChecklistItem::factory()->rejected()->create([
        'type' => PreboardingItemType::FormField,
    ]);

    $service = app(PreboardingService::class);
    $updated = $service->submitItem($item, ['form_value' => 'corrected-value']);

    expect($updated->status)->toBe(PreboardingItemStatus::Submitted);
    expect($updated->form_value)->toBe('corrected-value');
    expect($updated->rejection_reason)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Offer Relationship Tests
|--------------------------------------------------------------------------
*/

it('offer has preboarding checklist relationship', function () {
    $offer = Offer::factory()->create();
    $checklist = PreboardingChecklist::factory()->create(['offer_id' => $offer->id]);

    expect($offer->preboardingChecklist->id)->toBe($checklist->id);
});

it('job application has preboarding checklist relationship', function () {
    $application = JobApplication::factory()->create();
    $checklist = PreboardingChecklist::factory()->create(['job_application_id' => $application->id]);

    expect($application->preboardingChecklist->id)->toBe($checklist->id);
});

/*
|--------------------------------------------------------------------------
| API Controller Tests
|--------------------------------------------------------------------------
*/

it('submits a form field item via API', function () {
    $this->actingAs($this->user);

    $item = PreboardingChecklistItem::factory()->create([
        'type' => PreboardingItemType::FormField,
        'status' => PreboardingItemStatus::Pending,
    ]);

    $response = $this->postJson("{$this->baseUrl}/api/preboarding-items/{$item->id}/submit", [
        'form_value' => 'SSS-12345678',
    ]);

    $response->assertSuccessful();
    $item->refresh();
    expect($item->status)->toBe(PreboardingItemStatus::Submitted);
    expect($item->form_value)->toBe('SSS-12345678');
});

it('submits an acknowledgment item via API', function () {
    $this->actingAs($this->user);

    $item = PreboardingChecklistItem::factory()->create([
        'type' => PreboardingItemType::Acknowledgment,
        'status' => PreboardingItemStatus::Pending,
    ]);

    $response = $this->postJson("{$this->baseUrl}/api/preboarding-items/{$item->id}/submit", []);

    $response->assertSuccessful();
    $item->refresh();
    expect($item->status)->toBe(PreboardingItemStatus::Submitted);
});

/*
|--------------------------------------------------------------------------
| Overdue Job Test
|--------------------------------------------------------------------------
*/

it('marks overdue checklists', function () {
    $overdue = PreboardingChecklist::factory()->create([
        'status' => PreboardingStatus::InProgress,
        'deadline' => now()->subDay(),
    ]);

    $notOverdue = PreboardingChecklist::factory()->create([
        'status' => PreboardingStatus::InProgress,
        'deadline' => now()->addWeek(),
    ]);

    $completed = PreboardingChecklist::factory()->create([
        'status' => PreboardingStatus::Completed,
        'deadline' => now()->subDay(),
    ]);

    $job = new \App\Jobs\CheckOverduePreboardingJob;
    $job->handle();

    $overdue->refresh();
    $notOverdue->refresh();
    $completed->refresh();

    expect($overdue->status)->toBe(PreboardingStatus::Overdue);
    expect($notOverdue->status)->toBe(PreboardingStatus::InProgress);
    expect($completed->status)->toBe(PreboardingStatus::Completed);
});
