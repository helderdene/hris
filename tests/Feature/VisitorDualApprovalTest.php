<?php

use App\Enums\TenantUserRole;
use App\Enums\VisitStatus;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use App\Notifications\VisitorApproved;
use App\Services\Visitor\VisitorRegistrationService;
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

    $plan = Plan::factory()->starter()->create();
    $this->tenant = Tenant::factory()->withPlan($plan)->withTrial()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
    $this->withoutVite();

    // Admin user
    $this->admin = User::factory()->create();
    $this->admin->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    // Host employee with user
    $this->hostUser = User::factory()->create();
    $this->hostUser->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    $this->hostEmployee = Employee::factory()->create(['user_id' => $this->hostUser->id]);

    Gate::define('can-manage-organization', fn () => true);
});

describe('Dual Approval Workflow', function () {
    it('admin approves first, then host approves to finalize', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $this->hostEmployee->id]);

        $service = app(VisitorRegistrationService::class);

        // Admin approves
        $service->adminApprove($visit, $this->admin);
        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::PendingApproval);
        expect($visit->approved_at)->not->toBeNull();
        expect($visit->host_approved_at)->toBeNull();

        // Host approves
        $service->hostApprove($visit, $this->hostUser);
        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::Approved);
        expect($visit->host_approved_at)->not->toBeNull();
        expect($visit->qr_token)->not->toBeNull();
    });

    it('host approves first, then admin approves to finalize', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $this->hostEmployee->id]);

        $service = app(VisitorRegistrationService::class);

        // Host approves first
        $service->hostApprove($visit, $this->hostUser);
        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::PendingApproval);
        expect($visit->host_approved_at)->not->toBeNull();
        expect($visit->approved_at)->toBeNull();

        // Admin approves
        $service->adminApprove($visit, $this->admin);
        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::Approved);
        expect($visit->approved_at)->not->toBeNull();
        expect($visit->qr_token)->not->toBeNull();
    });

    it('sends notification only when fully approved', function () {
        Notification::fake();

        $visitor = Visitor::factory()->create(['email' => 'visitor@example.com']);
        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create([
                'visitor_id' => $visitor->id,
                'host_employee_id' => $this->hostEmployee->id,
            ]);

        $service = app(VisitorRegistrationService::class);

        // Admin approves — no notification yet
        $service->adminApprove($visit, $this->admin);
        Notification::assertNothingSent();

        // Host approves — notification sent
        $service->hostApprove($visit, $this->hostUser);
        Notification::assertSentOnDemand(VisitorApproved::class);
    });

    it('either party can reject', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $this->hostEmployee->id]);

        $service = app(VisitorRegistrationService::class);
        $service->hostReject($visit, $this->hostUser, 'Not expecting this visitor.');

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::Rejected);
        expect($visit->rejection_reason)->toBe('Not expecting this visitor.');
    });
});

describe('Host Self-Service API', function () {
    it('host can approve their own visitor visit', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $this->hostEmployee->id]);

        $response = $this->actingAs($this->hostUser)
            ->postJson("{$this->baseUrl}/api/my/visitor-visits/{$visit->id}/approve");

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->host_approved_at)->not->toBeNull();
        expect($visit->host_approved_by)->toBe($this->hostUser->id);
    });

    it('host can reject their own visitor visit', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $this->hostEmployee->id]);

        $response = $this->actingAs($this->hostUser)
            ->postJson("{$this->baseUrl}/api/my/visitor-visits/{$visit->id}/reject", [
                'reason' => 'Not available that day.',
            ]);

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::Rejected);
        expect($visit->rejection_reason)->toBe('Not available that day.');
    });

    it('non-host cannot approve someone elses visit', function () {
        Notification::fake();

        $otherEmployee = Employee::factory()->create();
        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $otherEmployee->id]);

        $response = $this->actingAs($this->hostUser)
            ->postJson("{$this->baseUrl}/api/my/visitor-visits/{$visit->id}/approve");

        $response->assertForbidden();
    });
});
