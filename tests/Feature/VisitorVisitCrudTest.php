<?php

use App\Enums\TenantUserRole;
use App\Enums\VisitStatus;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->withTrial()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    $this->admin = User::factory()->create();
    $this->admin->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->employee = User::factory()->create();
    $this->employee->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->withoutMiddleware([
        EnsureActiveSubscription::class,
        EnsureModuleAccess::class,
    ]);
});

describe('Visitor Visit CRUD', function () {
    it('lists visitor visits for admin users', function () {
        VisitorVisit::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson("{$this->baseUrl}/api/visitor-visits");

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('filters visits by status', function () {
        VisitorVisit::factory()->pendingApproval()->count(2)->create();
        VisitorVisit::factory()->approved()->count(1)->create();
        VisitorVisit::factory()->checkedIn()->count(1)->create();

        $response = $this->actingAs($this->admin)
            ->getJson("{$this->baseUrl}/api/visitor-visits?status=pending_approval");

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('creates a pre-registered visit via store', function () {
        Notification::fake();

        $visitor = Visitor::factory()->create();
        $workLocation = WorkLocation::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits", [
                'visitor_id' => $visitor->id,
                'work_location_id' => $workLocation->id,
                'purpose' => 'Business meeting with CEO',
                'expected_at' => now()->addDays(2)->toDateTimeString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('purpose', 'Business meeting with CEO');
        $response->assertJsonPath('status', VisitStatus::PreRegistered->value);

        $this->assertDatabaseHas('visitor_visits', [
            'visitor_id' => $visitor->id,
            'work_location_id' => $workLocation->id,
            'purpose' => 'Business meeting with CEO',
            'status' => VisitStatus::PreRegistered->value,
        ]);
    });

    it('shows a single visit', function () {
        $visit = VisitorVisit::factory()->pendingApproval()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}");

        $response->assertSuccessful();
        $response->assertJsonPath('id', $visit->id);
    });

    it('approves a pending visit (admin only — stays pending until host approves)', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->pendingApproval()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/approve");

        $response->assertSuccessful();
        $response->assertJsonPath('status', VisitStatus::PendingApproval->value);

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::PendingApproval);
        expect($visit->approved_at)->not->toBeNull();
    });

    it('fully approves a host-approved visit when admin approves', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->hostApproved()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/approve");

        $response->assertSuccessful();
        $response->assertJsonPath('status', VisitStatus::Approved->value);

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::Approved);
        expect($visit->approved_at)->not->toBeNull();
        expect($visit->host_approved_at)->not->toBeNull();
    });

    it('rejects a pending visit with reason', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->pendingApproval()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/reject", [
                'reason' => 'No available meeting rooms',
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('status', VisitStatus::Rejected->value);

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::Rejected);
        expect($visit->rejection_reason)->toBe('No available meeting rooms');
        expect($visit->rejected_at)->not->toBeNull();
    });

    it('cannot approve a non-pending visit', function () {
        $visit = VisitorVisit::factory()->approved()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/approve");

        $response->assertUnprocessable();
    });

    it('checks in an approved visit', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->approved()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-in", [
                'badge_number' => 'V-001',
            ]);

        $response->assertSuccessful();
        $response->assertJsonPath('status', VisitStatus::CheckedIn->value);

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::CheckedIn);
        expect($visit->checked_in_at)->not->toBeNull();
        expect($visit->badge_number)->toBe('V-001');
    });

    it('cannot check in a pending visit', function () {
        $visit = VisitorVisit::factory()->pendingApproval()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-in");

        $response->assertUnprocessable();
    });

    it('checks out a checked-in visit', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->checkedIn()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-out");

        $response->assertSuccessful();
        $response->assertJsonPath('status', VisitStatus::CheckedOut->value);

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::CheckedOut);
        expect($visit->checked_out_at)->not->toBeNull();
    });

    it('forbids employee users', function () {
        $visit = VisitorVisit::factory()->pendingApproval()->create();

        $response = $this->actingAs($this->employee)
            ->getJson("{$this->baseUrl}/api/visitor-visits");

        $response->assertForbidden();
    });
});
